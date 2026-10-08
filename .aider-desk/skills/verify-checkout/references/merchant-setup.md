# Merchant Setup and Integration Handoff

Use this reference before implementation and when handing the completed code back to a merchant. Do not ask the merchant to paste API keys or webhook secrets into chat.

## Choose the completion path

Recommend **webhooks plus polling/reconciliation** for production:

- Webhooks notify the merchant backend even when the payer closes the browser or app.
- The return page polls the merchant's own backend for responsive customer UX.
- A scheduled reconciliation read repairs delayed, missed, or exhausted webhook deliveries.

Polling-only is supported. It needs the API key but no webhook secret. Use it only when the merchant accepts slower detection and operates a durable server-side poll/reconciliation loop; browser polling alone stops when the customer leaves.

## Dashboard setup

The merchant completes these steps at `https://checkout.verify.et`:

1. Sign in and configure the workspace business name and checkout logo.
2. Add at least one active merchant-owned receiving account at `/dashboard/accounts/new`.
3. At `/dashboard/developers`, create an API key. Grant `deposits:create`; also grant `deposits:read` for status lookup, polling, and reconciliation. Copy the one-time `vchk_...` value directly into server secret storage.
4. At `/dashboard/developers`, register and activate the exact return **origin**, such as `https://shop.example.com`. The `return_url` path may vary, but scheme, host, and port must match. Subdomains are separate. HTTP is loopback-only.
5. Recommended: at `/dashboard/developers/webhooks`, add the merchant's stable public HTTPS receiver, select the outcome events it processes, and retain `webhook.test`. Copy the one-time `whsec_...` value directly into server secret storage.
6. Deploy the receiver with raw-body signature verification, then send a dashboard test. The first successful `2xx` test activates the endpoint.

API keys and webhook secrets are different credentials. Never reuse one as the other. Use separate credentials per environment/service when practical.

## Code integration order

1. Add private server config and startup validation. The webhook secret is conditional when polling-only is intentionally selected.
2. Add or extend a local checkout/payment-attempt record. Persist a stable create idempotency key before calling Verify Checkout.
3. Add a server-only Verify Checkout client for create and retrieve/list operations.
4. Add the authenticated merchant initiation endpoint/action. Reuse an existing active local attempt on double-click or ambiguous retry.
5. Persist `data.id`, `data.status`, `data.expires_at`, and the local order/customer mapping before returning `data.checkout_url`.
6. Navigate the customer to hosted checkout. Do not collect receiving-account or reference data in merchant UI.
7. Add the return/status page. Treat the return as untrusted navigation and poll the merchant backend, not Verify Checkout from the browser.
8. Add the selected completion path:
   - Recommended: signed webhook inbox + fulfillment worker/service + reconciliation.
   - Polling-only: durable server poll/reconciliation + bounded customer-status polling.
9. Fulfill only authoritative `status: "succeeded"`, transactionally and once per deposit ID. Hold `review_required`; do not fulfill failure/expiry/cancellation states.
10. Add focused boundary tests and perform an authorized low-risk live checkout smoke test before launch.

## Merchant handoff checklist

State which items are complete and which require the merchant/operator:

- Receiving account active.
- API key created, scoped, stored, and loaded by the server.
- Return origin registered and active.
- Create/replay, redirect, return, status, and expiry paths tested.
- Webhook URL registered, signing secret stored, signature tests passing, and dashboard test delivered—or polling-only choice recorded.
- Exactly-once fulfillment and reconciliation tested.
- Logs redact credentials, checkout tokens, transaction references, and customer data.
- Deployment secret rotation and incident owner documented.

Never claim the integration is live-ready when required dashboard configuration or an authorized end-to-end test is still outstanding.
