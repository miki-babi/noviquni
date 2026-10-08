# Debugging, Testing, and Production Readiness

Use this reference when diagnosing an integration, writing tests, or performing the final smoke test.

## Capture safely first

- Merchant route and local payment-attempt ID.
- HTTP method/path/status, stable error code/type/retryable flag, and `meta.requestId`.
- Verify Checkout deposit ID and the create idempotency key's fingerprint—not the raw key if sensitive.
- Deposit `status`, `verification_status`, `notification_status`, and expiry.
- Webhook event ID, delivery ID, event type, sequence, signature outcome, and endpoint response status.
- Rate-limit headers and `Retry-After`.

Never capture bearer keys, webhook secrets, authorization headers, full checkout URLs/tokens, full transfer references, raw receipts, cookies, or unnecessary customer PII.

## HTTP triage

| HTTP | Likely issue | Action |
| --- | --- | --- |
| `400` | Missing/invalid idempotency key, malformed request, unsupported API version | Compare exact body/header contract; pin supported version |
| `401` | Missing, invalid, expired, or revoked key | Confirm server env loading and rotate; never expose key for debugging |
| `402` | Insufficient verification credits | Resolve account credits; do not loop retries |
| `403` | Key lacks required scope | Grant least required `deposits:create` or `deposits:read` scope |
| `404` | Wrong deposit ID or wrong merchant/key ownership | Use persisted ID and same merchant account |
| `409` | Idempotency key reused with different body or operation still in progress | Reuse identical body or allocate a new key only for a genuinely new attempt |
| `413` | Request body too large | Send only strict documented fields |
| `422` | Body/query validation failure | Use `error.details`; fix types/field values, do not blind retry |
| `429` | Rate limit | Honor `Retry-After`, add jitter/backoff, reduce concurrency |
| `500` | Unexpected server failure | Capture request ID; retry only with safe idempotency semantics |
| `503` | No eligible receiving account or temporary initiation unavailability | Check account/provider setup; retry later only if response is retryable |

Branch on `error.code`, not message text. Display merchant-owned customer-safe copy; response `message` is diagnostic, not a stable UX string.

## Common integration failures

- Calling the API from browser/mobile code: move it behind the merchant backend and rotate the leaked key.
- Sending unsupported fields such as merchant-defined `merchant_order_id`, `webhook_url`, or `metadata` in create: remove fields that are absent from the public request schema.
- Treating only `201` as success: accept create replay `200` too.
- Generating a new idempotency key after a timeout: reuse the stored key and identical body. Mint a new key only for a genuinely new attempt.
- Redirecting before persisting `deposit_id`: persist association first.
- Crediting from browser return or webhook event name: retrieve/use authoritative object status.
- Using `verification_status: succeeded` alone: require deposit `status: succeeded`.
- Signature verification after JSON parsing: preserve exact raw bytes.
- Deduplicating by delivery ID: use event ID; deliveries can change on retry.
- Tight infinite polling: bound/back off, honor `Retry-After`, retain reconciliation.
- Showing an expired checkout URL again: create a genuinely new local attempt/deposit.

## Test matrix

If the project already has tests, prefer a small set at the merchant boundary. Do not add a new test stack unless asked.

Useful cases:

- Create body omits unsupported fields; headers include bearer auth, version, and idempotency key.
- `201` create and `200` replay parse the same way.
- Timeout then same-key retry does not create a second deposit.
- Return page ignores success query strings and reads backend state.
- Double-click / duplicate initiate reuses the local attempt.
- Expired `checkout_url` is not reused; a new attempt is created.
- Webhook signature over exact bytes; duplicate event does not double-credit.
- `review_required`, `failed`, `expired`, and `cancelled` never credit.

More cases if the suite already covers HTTP/webhooks: error mapping, `Retry-After`, out-of-order events, concurrent webhook vs poll, `confirmed_before: true`.

## Live checkout smoke test

The bundled script can create a real hosted checkout and inspect it. A create is an external mutation and may consume operational capacity or credits, so run it only when the user explicitly authorizes the test and supplies a valid return URL/customer ID/API key locally.

Keep secrets in an ignored local file or the process secret store:

```dotenv
VERIFY_CHECKOUT_API_KEY=vchk_replace_locally
VERIFY_CHECKOUT_RETURN_URL=https://merchant.example/payments/return
VERIFY_CHECKOUT_CUSTOMER_ID=integration_test_customer
VERIFY_CHECKOUT_IDEMPOTENCY_KEY=checkout_018f_example_attempt
VERIFY_CHECKOUT_AMOUNT=1.00
```

Do not commit that file. From the skill directory, with a Node version supporting `--env-file`:

```bash
VERIFY_CHECKOUT_SMOKE_MODE=create node --env-file=/absolute/path/to/ignored.env scripts/verify_checkout_smoke_test.mjs
```

The script does not open the hosted page automatically and redacts `checkout_url`. When the operator needs the URL for the authorized browser check, run the create command with the explicit `--show-checkout-url` CLI flag in a private terminal. Do not put that choice in an env file or CI command. Use the returned deposit ID for follow-up checks without exposing the secret:

```bash
VERIFY_CHECKOUT_SMOKE_MODE=get \
VERIFY_CHECKOUT_DEPOSIT_ID=dep_example \
node --env-file=/absolute/path/to/ignored.env scripts/verify_checkout_smoke_test.mjs
```

Expected create result:

- HTTP `201` for a new checkout or `200` for same-key replay.
- `body.data.id` and `body.data.checkout_url` present.
- `body.data.status` is an initial nonterminal state.
- `body.meta.apiVersion` is the pinned version.
- Checkout URL loads, displays the expected merchant/provider/amount behavior, and expires at `expires_at`.

Do not submit a real transfer merely to test API creation unless the user separately authorizes that payment and understands its financial effect.

## Production gate

- Secrets are server-only, validated, ignored, redacted, scoped, and rotatable.
- Return origins and receiving accounts are active.
- Stable idempotency and local unique constraints are deployed.
- Webhook signature, duplicate delivery, ordering, and durable acceptance are tested.
- Poll/reconciliation recovery works during webhook outage.
- Exactly-once fulfillment is transactional and observable.
- Timeouts, rate limits, retry caps, and customer-safe errors are implemented.
- Alerts/logs correlate local attempt, deposit ID, request ID, and event ID without secrets/PII.
- A low-risk authorized checkout smoke test succeeds before launch.
