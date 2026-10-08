# Verify Checkout Merchant Webhooks

Use this reference when setting up, implementing, testing, rotating, or debugging merchant webhooks.

## Dashboard setup

1. Expose a stable public HTTPS endpoint owned by the merchant. Localhost requires a deliberate development tunnel; protect or remove it after testing.
2. In the Verify Checkout dashboard, add the endpoint and select events.
3. Copy the one-time `whsec_...` secret directly into the merchant's secret store.
4. Deploy the receiver, then send the dashboard test delivery.
5. The first successful `2xx` test activates the endpoint.

Outcome events and `webhook.test` are selected by default; lifecycle events are opt-in. Subscribe only to events the merchant processes, while retaining the required test event.

## Headers and signing

Required headers:

- `VerifyCheckout-Event`
- `VerifyCheckout-Event-Id` — stable event ID for deduplication
- `VerifyCheckout-Delivery-Id` — this delivery attempt
- `VerifyCheckout-Timestamp` — original Unix timestamp used in signing
- `VerifyCheckout-Signature` — `v1=<64 lowercase hex chars>`
- `VerifyCheckout-Api-Version` — currently `2026-06-01`
- `Content-Type: application/json`
- Optional `VerifyCheckout-Test: true`

The signed bytes are:

```text
<timestamp>.<exact raw request body bytes>
```

Compute HMAC-SHA256 with the endpoint secret. Use the timestamp string exactly as received in the HMAC input. For replay protection, normalize a 13-digit timestamp to milliseconds only for the age comparison; do not alter it for signature calculation.

## Secure receiver sequence

1. Accept only `POST` with `Content-Type: application/json`. Reject other methods/media types before processing.
2. Enforce a request-body limit before buffering (256 KiB unless the public contract documents a larger payload). Return `413` when exceeded.
3. Read raw bytes before any JSON parser, decompression rewrite, normalization, or reserialization.
4. Require the expected signature format and API version.
5. Reject timestamps outside a five-minute tolerance.
6. Compute HMAC-SHA256 and compare decoded bytes in constant time.
7. Parse JSON only after signature success and validate the webhook schema.
8. Require header event ID to match body `id`, and header event type to match body `type`.
9. Insert the event into a durable inbox with a unique event-ID constraint.
10. Return `2xx` after durable acceptance; enqueue/process business effects separately when possible.
11. Treat a duplicate insert as already accepted and return `2xx`.

Do not return `2xx` before the event is durable if a crash would lose it. Do not perform slow provider calls or customer crediting in the request path when a queue/job mechanism already exists.

## Node verification primitive

```ts
import { createHmac, timingSafeEqual } from "node:crypto";

export function verifyCheckoutSignature(
  rawBody: Buffer,
  timestamp: string,
  signature: string,
  secret: string,
  nowMs = Date.now(),
): boolean {
  if (!/^\d{10,13}$/.test(timestamp) || !/^v1=[a-f0-9]{64}$/.test(signature)) return false;
  const timestampNumber = Number(timestamp);
  const timestampMs = timestamp.length >= 13 ? timestampNumber : timestampNumber * 1000;
  if (!Number.isSafeInteger(timestampNumber) || Math.abs(nowMs - timestampMs) > 300_000) return false;

  const expected = createHmac("sha256", secret)
    .update(timestamp)
    .update(".")
    .update(rawBody)
    .digest();
  const received = Buffer.from(signature.slice(3), "hex");
  return received.length === expected.length && timingSafeEqual(received, expected);
}
```

Framework ordering matters: in Express, mount `express.raw({ type: "application/json", limit: "256kb" })` for this route before a global JSON parser. In Fetch/Hono/Next-style runtimes, reject an excessive `Content-Length` when present and enforce the same limit while reading the stream; do not rely only on the header. Retain the exact accepted bytes before parsing.

## Event body

```json
{
  "id": "evt_example",
  "type": "deposit.succeeded",
  "api_version": "2026-06-01",
  "sequence": 5,
  "created_at": "2026-08-21T10:04:12.000Z",
  "data": {
    "object": {
      "id": "dep_example",
      "merchant_order_id": "order_1042",
      "merchant_customer_id": "customer_42",
      "environment": "live",
      "status": "succeeded",
      "amount": "250.00",
      "currency": "ETB",
      "payment_method": "telebirr",
      "verification": {
        "provider": "verify_et",
        "status": "succeeded",
        "verified": true,
        "amount_match": true,
        "currency_match": true,
        "receiver_match": true,
        "receiver_match_confidence": "high",
        "confirmed_before": false
      },
      "metadata": {},
      "created_at": "2026-08-21T10:00:00.000Z"
    }
  }
}
```

`environment` is currently always `live` as a compatibility field; do not branch merchant business logic on it.

The webhook deposit requires `id`, Verify Checkout-generated `merchant_order_id`, `environment`, `status`, `currency`, `metadata`, and `created_at`. It may also include `merchant_customer_id`, decimal-string `amount`, `payment_method`, `expires_at`, masked `assigned_account`, `transaction` (`reference`, optional `verified_at`), and `verification`. Verification requires `provider`, `status`, and `verified`, and may include provider request ID, amount/currency/receiver matches, receiver confidence (`none`, `low`, `medium`, `high`), and `confirmed_before`.

## Event types

Payment/outcome:

- `deposit.succeeded`
- `deposit.manually_succeeded`
- `deposit.failed`
- `deposit.manually_failed`
- `deposit.expired`
- `deposit.review_required`

Lifecycle/operations:

- `deposit.created`
- `deposit.assigned`
- `deposit.reference_submitted`
- `deposit.verification_pending`
- `deposit.cancelled`
- `deposit.review_resolved`
- `webhook.test`

Make decisions from `data.object.status`, not the event name or arrival order. For `webhook.test`, verify and durably acknowledge without triggering payment logic.

## Delivery semantics

- Delivery is at least once: duplicates are normal.
- Events may arrive out of order. Use `sequence` for diagnostics, but retrieve the deposit when current ordering matters.
- Any `2xx` is accepted as delivery success. Non-`2xx`/network failures may be retried.
- Verify Checkout records delivery success, not whether the merchant credited a wallet.
- Deduplicate events by `VerifyCheckout-Event-Id`/body `id`; retain delivery IDs for attempt diagnostics only.

## Secret rotation

Rotate the endpoint secret in the dashboard, add the newly revealed value to the merchant secret store, and deploy/reload the receiver promptly. During that controlled rollout, the receiver may accept both the old and new merchant-held secrets so in-flight deliveries are not rejected; record which version verified. Send a test delivery, then remove the old secret after the merchant's bounded rollout window. Never log either secret, and do not assume the dashboard can reveal either value again.

## Test cases

- Valid signature and timestamp.
- Altered body, timestamp, or signature.
- Stale and future timestamps beyond tolerance.
- Malformed hex and wrong signature version.
- Header/body event mismatch.
- Duplicate event with a different delivery ID.
- Out-of-order succeeded/earlier lifecycle events.
- `webhook.test` produces no fulfillment.
- Queue/database failure returns non-`2xx` so delivery can retry.
- Oversized request is rejected with `413` before full buffering or signature work.
