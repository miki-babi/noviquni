# Merchant Product and Storefront UX

Use this reference when wiring Verify Checkout into the merchant website or app. Verify Checkout hosts payment instructions and reference collection. The merchant hosts pay, return, pending, paid, failed, expired, and review.

## Ownership

| Surface | Owner |
| --- | --- |
| Pay button, order/wallet UI, return page, status poll | Merchant |
| Provider list, receiving-account instructions, reference form, verification wait | Hosted checkout |
| Transaction-reference check | Verify.et via Verify Checkout |
| Ledger credit, order fulfillment, refunds | Merchant |

Do not build a merchant-side provider picker, account-number copy UI, reference input, or Verify.et client.

## Pay CTA

1. Customer must be authenticated (or otherwise identified) so `merchant_customer_id` is stable. Prefer an opaque internal identifier rather than email, phone, name, or other direct PII.
2. Validate the local order/wallet intent before calling the merchant backend.
3. Prefer disabling the button while create is in flight. A double-click should reuse the same local attempt and its stored `Idempotency-Key`.
4. The backend returns `{ checkoutUrl }` only after `deposit_id` is persisted. The client only navigates: `window.location.assign(checkoutUrl)` or equivalent.
5. Never put the API key, webhook secret, or create-deposit call in browser or mobile bundles.

## Redirect and hosted page

The customer completes transfer and reference submission on `https://checkout.verify.et/c/...`. Treat `checkout_url` as customer-facing but sensitive: do not log it routinely, publish it, or reuse it for another order. The merchant backend stores `data.id`; it does not parse the token from the URL.

Checkout links expire 60 minutes after creation (`expires_at`). After expiry, create a new local attempt and deposit. Do not reopen the old URL.

Creating a checkout does not consume a verification credit. A credit is consumed when a valid reference starts a verification attempt.

## Return page

`return_url` is untrusted navigation. Ignore success-like query parameters, fragments, and referrer.

1. Identify the local attempt from the merchant session, signed cookie, or path id—not from Verify Checkout query params.
2. Load local state. If needed, the backend retrieves `GET /v1/deposits/{id}` and updates monotonically.
3. Render merchant copy from local/authoritative status. Poll the **merchant** status API while nonterminal.
4. Never credit a wallet or mark an order paid in the return-page request solely because the browser came back.

Register and **activate** the return origin in the dashboard. Scheme, host, and port of every `return_url` must match. Subdomains are separate origins. HTTP is loopback-only.

## Customer-visible states

Map deposit `status` to merchant copy. Do not show raw API `message` or internal codes.

| Deposit status | Storefront |
| --- | --- |
| `created`, `assigned`, `awaiting_transfer` | Waiting for payment. Keep polling. Offer support reference if available. |
| `reference_submitted`, `verification_pending` | Verifying payment. Keep polling; do not promise instant credit. |
| `succeeded` | Paid. Show order/wallet result after exactly-once fulfillment. |
| `failed`, `cancelled` | Not paid. Offer a new attempt if the product allows it. |
| `expired` | Link expired. Start a new attempt; do not reuse `checkout_url`. |
| `review_required` | Hold. Do not credit. Tell the customer it is under review. Keep webhook/reconciliation. |

Intermediate hosted progress (`verification_status`) may inform support logs. It must not drive fulfillment. `notification_status` is webhook delivery, not payment.

## Polling UX

- Browser polls merchant `GET /payments/{attemptId}` (name as the project does), not `checkoutapi.verify.et`.
- Back off if easy (for example 2s, 4s, 8s, then cap around 15–30s). Stop frequent polling at a UX deadline; server reconciliation can continue.
- Avoid hammering the merchant status API.
- When `succeeded`, stop polling and show the fulfilled result. When `failed`/`expired`/`cancelled`, stop and offer the next product action. When `review_required`, stop promising immediacy but keep a slower refresh or “check later.”

## Mobile

- The app calls the merchant backend. Open `checkout_url` in the system browser or a secure browser surface (SFSafariViewController / Chrome Custom Tabs), not a webview that can steal cookies if avoidable.
- `return_url` must use a registered origin the app can resume: HTTPS page or documented app link on that origin.
- Do not embed `vchk_` or `whsec_` in the binary or Expo/React Native public env.

## Product shapes

**Order / cart:** bind one unpaid order to one active attempt. On `succeeded`, mark paid and fulfill inventory/entitlement once. On expiry, leave the order unpaid and allow a new attempt.

**Wallet top-up:** fixed or variable amount. For variable, credit the authoritative returned `amount` after product min/max checks. For fixed, reject mismatches against the local expected amount.

**Subscription / entitlement:** treat the deposit as payment for a specific invoice/period. Do not extend access on `review_required`.

## Operational UX (not payer-facing)

- `402` / `insufficient_credits` is a merchant-account problem. Do not retry-loop. Do not leak billing or credit balance to the payer.
- `503` / no payment options usually means no active receiving account for the requested method. Fix dashboard setup.
- Inactive or mismatched return origin rejects create. Activate the exact origin before testing the pay button.
