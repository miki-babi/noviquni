# Verify Checkout Merchant API Contract

Use this reference for HTTP contracts and field semantics. The snapshot below targets API version `2026-06-01`; verify the live OpenAPI document when internet access is available.

## Base, authentication, and common headers

- Production API: `https://checkoutapi.verify.et`
- Hosted checkout: URLs returned under `https://checkout.verify.et/c/...`
- Authentication: `Authorization: Bearer vchk_...`
- Contract pin: `VerifyCheckout-Version: 2026-06-01`
- Create-only requirement: `Idempotency-Key`, 1–256 characters
- Required scopes: `deposits:create` for create; `deposits:read` for get, list, and events
- Useful response headers: `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset`; `Retry-After` on throttling

Use bearer authentication only from the merchant backend.

## Operations

| Method and path | Purpose | Success |
| --- | --- | --- |
| `POST /v1/deposits` | Create hosted fixed- or variable-amount checkout | `201`; idempotent replay is `200` with original body |
| `GET /v1/deposits/{deposit_id}` | Retrieve current authoritative deposit | `200` |
| `GET /v1/deposits` | Reconcile/filter deposits with cursor pagination | `200` |
| `GET /v1/deposits/{deposit_id}/events` | Read timeline oldest first | `200` |

There is no merchant endpoint for submitting the payment reference. The hosted checkout owns provider selection, transfer instructions, customer reference entry, and verification UX.

## Create request

The body is strict: do not send undocumented properties.

```json
{
  "merchant_customer_id": "customer_42",
  "amount": "250.00",
  "currency": "ETB",
  "payment_method": "telebirr",
  "return_url": "https://shop.example.com/payments/return"
}
```

| Field | Type | Required | Merchant meaning |
| --- | --- | --- | --- |
| `merchant_customer_id` | string, 1–128 | yes | Merchant's stable payer/customer identifier |
| `amount` | decimal string or JSON number, max 2 decimals | no | Omit for customer-entered variable amount; responses normalize to string |
| `currency` | literal `ETB` | no | Defaults to `ETB`; currently the only supported currency |
| `payment_method` | lowercase provider code, 1–64 | no | Restricts checkout to an eligible provider |
| `return_url` | absolute URL | yes | Origin must be registered **and active**; scheme, host, and port must match. HTTP only for loopback development |

Supported provider codes currently include `telebirr`, `mpesa`, `cbe`, `boa`, `cbebirr`, `dashen`, `awash`, `siinqee`, and `kaafiebirr`. Treat the live contract/dashboard as authoritative because availability also depends on active receiving accounts.

Checkout URLs expire 60 minutes after creation (`data.expires_at`). Creating a deposit does not consume a verification credit; a credit is consumed when a valid reference starts a verification attempt.

The create request does **not** accept `merchant_order_id`, `metadata`, a webhook URL, a transfer reference, or a checkout token. Verify Checkout generates `merchant_order_id`; retain your own local order-to-deposit mapping.

## Success envelope and deposit

```json
{
  "data": {
    "id": "dep_example",
    "merchant_order_id": "order_1042",
    "merchant_customer_id": "customer_42",
    "status": "awaiting_transfer",
    "verification_status": "not_started",
    "notification_status": "not_ready",
    "amount": "250.00",
    "currency": "ETB",
    "payment_method": "telebirr",
    "checkout_url": "https://checkout.verify.et/c/example_token",
    "support_reference": "VC-1042",
    "payment_options": [],
    "metadata": {},
    "created_at": "2026-08-21T10:00:00.000Z",
    "expires_at": "2026-08-21T11:00:00.000Z"
  },
  "meta": {
    "requestId": "req_example",
    "apiVersion": "2026-06-01"
  }
}
```

Persist these first:

- `data.id`: authoritative deposit identifier and exactly-once fulfillment key.
- Local order/customer relationship: map the merchant's own order/payment attempt to `data.id`.
- `data.checkout_url` and `data.expires_at`: redirect target and 60-minute expiry; avoid routine logging because the URL contains a customer-facing token.
- `data.status`: fulfillment decision.
- `meta.requestId`: support and log correlation.

Other useful fields:

- `merchant_order_id`: Verify Checkout-generated order ID; do not confuse with the merchant's local order ID.
- `support_reference`: safe short reference for support conversations.
- `payment_options`: provider/account choices and allowed amount ranges.
- `assigned_account`: chosen account summary after customer selection.
- `reference`: masked submitted-reference summary.
- `verification`: Verify.et provider request/status summary.
- `notification_status`: platform-to-merchant webhook delivery state, not payment state.
- `failure_reason`: operational explanation when a deposit cannot proceed.

Nested response objects:

| Object | Fields |
| --- | --- |
| `payment_options[]` | `payment_method`, `institution_code`, `institution_name`, masked account identifier, decimal-string `min_amount`, decimal-string `max_amount` |
| `assigned_account` | opaque `id`, `institution_code`, `masked_identifier` |
| `reference` | masked reference and ISO `submitted_at`; the public deposit response does not expose the raw reference |
| `verification` | literal provider `verify_et`, optional `provider_request_id`, `processing_status`, boolean `verified` |
| `metadata` | open JSON object returned with the deposit; the current create request does not accept merchant-supplied metadata |

IDs are opaque strings and timestamps are ISO 8601 UTC date-times. Money is returned as a decimal string with at most two fraction digits; avoid binary floating-point arithmetic for ledger operations.

## Status types

`status` is the business outcome:

- Intermediate: `created`, `assigned`, `awaiting_transfer`, `reference_submitted`, `verification_pending`
- Fulfillable: `succeeded`
- Do not fulfill: `failed`, `expired`, `cancelled`
- Hold for operations: `review_required`

`verification_status` is independent provider-processing detail:

`not_started`, `created`, `queued`, `running`, `succeeded`, `not_found`, `duplicate`, `receiver_mismatch`, `amount_mismatch`, `currency_mismatch`, `transaction_too_old`, `ambiguous`, `provider_error`, `timed_out`, `cancelled`, `review_required`.

`notification_status` describes webhook delivery only:

`not_ready`, `pending`, `delivered`, `retrying`, `exhausted`, `disabled`.

Never derive fulfillment from `verification_status` or `notification_status` alone.

## List and events

`GET /v1/deposits` supports `status`, `verification_status`, `notification_status`, `payment_method`, generated `merchant_order_id`, `created_from`, `created_to`, opaque `cursor`, and `limit` (default 25, max 100).

List response `meta.pagination` contains `nextCursor`, `hasMore`, and `limit`. Pass the returned cursor unchanged while `hasMore` is true.

Deposit events contain `id`, `deposit_id`, `type`, nonnegative `sequence`, `actor_type` (`system`, `merchant`, `customer`, `platform`), optional `reason_code`/`message`, open-ended `data`, and `created_at`. An event timeline is not the same shape as an inbound webhook.

## Error envelope

```json
{
  "error": {
    "code": "invalid_api_key",
    "message": "The API key is invalid or expired.",
    "type": "authentication_error",
    "retryable": false
  },
  "meta": {
    "requestId": "req_example",
    "apiVersion": "2026-06-01"
  }
}
```

The OpenAPI error envelope has `error` and `meta` (`code`, `message`, `type`, `retryable`). It does not include `data: null`. Some narrative docs show a shorter example; follow OpenAPI. Branch on `error.code`, use `retryable` as a retry signal, and log `meta.requestId`.

Public deposit operations commonly return:

- Request/auth: `invalid_request`, `invalid_api_version`, `validation_failed`, `request_body_too_large`, `authentication_required`, `invalid_api_key`, `forbidden`, `resource_not_found`.
- Checkout/account: `insufficient_credits`, `idempotency_conflict`, `order_routing_unavailable`, `order_initiation_unavailable`.
- Platform: `rate_limited`, `internal_server_error`.

Not every code is emitted by every public operation. Handle known codes specifically and retain a safe fallback for future codes.

## Idempotency semantics

Use a stable, opaque key for the local payment attempt, for example `checkout_<attempt UUID>`. The public contract accepts any non-empty value from 1–256 characters; it does not require a timestamp or a particular prefix. A stored local attempt ID/UUID avoids timestamp collisions and makes retries easy to associate.

- One key per local payment/deposit attempt. Persist it on the attempt **before** the create call.
- Retries of the same attempt reuse the stored key. Do not generate a new datetime on timeout, double-click, or lost response.
- A new pay after expiry or a new order mints a new key.
- Retrying the same body with the same key returns the original result (`200` after an original `201`).
- Reusing the key with a different body returns `409 idempotency_conflict`.

## Current sources

- OpenAPI: `https://checkoutapi.verify.et/openapi/public-api.json`
- Guides: `https://checkout.verify.et/docs`
- Hosted checkout guide: `https://checkout.verify.et/docs/hosted-checkout`

Use the live OpenAPI document as the contract authority when narrative examples disagree.
