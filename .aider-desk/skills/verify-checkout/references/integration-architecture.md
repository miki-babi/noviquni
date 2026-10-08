# Merchant Integration Architecture

Use this reference when implementing or refactoring a merchant integration. For storefront pay/return/poll behavior see [merchant-product-ux.md](merchant-product-ux.md). For a suggested file shape see [merchant-code-quality.md](merchant-code-quality.md); adapt it, do not impose it.

## Fit the merchant's system

First identify the merchant's existing stack and keep its established boundaries. These responsibilities can live in existing files:

- `verifyCheckoutClient`: HTTP/auth/version/idempotency/timeout/response parsing.
- Deposit service: maps a local payment attempt to a Verify Checkout deposit and applies state-transition rules.
- Checkout initiation endpoint: authenticates the merchant's customer, creates/reuses the local attempt, calls Verify Checkout, and returns/redirects to the hosted URL.
- Return endpoint/page: treats browser return as untrusted navigation, loads local state, and asks the backend for the current deposit.
- Webhook endpoint: raw-body verification and durable event acceptance.
- Fulfillment worker/service: exactly-once customer credit/order completion.
- Reconciliation job: repairs missed/delayed webhooks by reading deposit state.

Do not introduce new frameworks or queues just to match this vocabulary. Adapt it to the merchant's conventions.

## Environment and secrets

Before coding, the merchant workspace needs an active receiving account, an active registered return origin matching `return_url`, and a dashboard-created API key with only the required scopes. A typical service that creates deposits and reconciles them needs `deposits:create` and `deposits:read`. The secret is shown once; copy it directly into the server secret store. Configure the webhook endpoint separately at `/dashboard/developers/webhooks` when webhooks are used. See [merchant-setup.md](merchant-setup.md) for the exact handoff.

Use names that fit the project; recommended private server variables are:

```dotenv
VERIFY_CHECKOUT_API_KEY=vchk_replace_locally
VERIFY_CHECKOUT_API_VERSION=2026-06-01
VERIFY_CHECKOUT_WEBHOOK_SECRET=whsec_replace_locally
VERIFY_CHECKOUT_RETURN_URL=https://merchant.example/payments/verify-checkout/return
```

- Put placeholders, never real values, in `.env.example`.
- Ensure `.env`, `.env.local`, and production-secret exports are ignored.
- Validate required values at process startup and reject placeholder/empty values.
- Never use client-exposed prefixes such as `NEXT_PUBLIC_`, `VITE_`, or Expo public variables for secrets.
- Prefer the deployment platform's secret manager in staging/production.
- Pin credential-bearing requests to `https://checkoutapi.verify.et`; do not let repository or tenant input select the API origin.
- Use separate least-privilege keys per service/environment and rotate without downtime.

Only require the webhook secret when webhooks are enabled. Do not reuse an API key as a webhook secret.

## Local persistence

Extend the merchant's existing payment/order model when possible. Aim to store:

- local payment attempt/order ID;
- local customer ID;
- Verify Checkout `deposit_id` with a unique constraint;
- create `idempotency_key` with a unique constraint;
- normalized create-request fingerprint or immutable request fields;
- checkout expiry;
- current deposit status and last remote update time;
- fulfillment timestamp/result with a unique exactly-once constraint;
- last webhook event ID/sequence or a separate inbox table;
- remote `requestId`/last stable error code for support.

For webhook inboxes, uniquely index event ID. Delivery ID identifies an attempt and can change on retries.

## Hosted checkout flow

1. Authenticate/authorize the merchant customer and validate the local order.
2. Within a transaction, create or reuse one local payment attempt. New attempts mint a stable 1–256 character `Idempotency-Key`, preferably derived from an opaque attempt ID/UUID; retries reuse the stored value.
3. Call `POST /v1/deposits` from the backend with the immutable request.
4. Parse both `201` and `200` as successful creation/replay.
5. Persist `data.id`, status, expiry, and the local association before handing out the URL.
6. Redirect the browser to `data.checkout_url` or return it to a trusted client that immediately navigates there.
7. On browser return, ignore success-like query parameters. Load the local attempt and retrieve/reconcile its deposit.
8. Update local state monotonically and fulfill only when the authoritative deposit status is `succeeded`.

Do not create another deposit on a double-click, client retry, application timeout, or lost response. Serialize local initiation or rely on a unique local attempt/idempotency constraint.

## Exactly-once fulfillment

Webhook delivery is at least once, but merchant fulfillment must be exactly once.

- Use Verify Checkout `deposit_id` as the unique external ledger/event key.
- In one database transaction, lock/find the local attempt, confirm it belongs to the expected customer/order, check it is not fulfilled, insert the unique credit/order transition, and mark fulfilled.
- Treat unique-constraint conflict as already processed, not a reason to credit again.
- Validate amount/currency against the merchant's local expectation for fixed deposits.
- For variable deposits, apply the merchant's product limits and credit the authoritative returned amount.
- If webhook verification evidence reports `confirmed_before: true`, apply the merchant's duplicate-payment/review policy before irreversible credit.

Avoid holding a database transaction open across the external API call.

## Webhooks, polling, and reconciliation

Preferred robust shape:

- Webhook updates backend truth promptly even when the customer closes the page.
- Browser polls the merchant backend—not Verify Checkout directly—for visible pending state.
- Merchant backend may retrieve `GET /v1/deposits/{id}` on demand with short caching/coalescing.
- A reconciliation job scans stale nonterminal/review records and authoritative status after webhook delays or endpoint outages.

For a polling-only merchant, use bounded exponential backoff with jitter (for example 2s, 4s, 8s, then cap around 15–30s), stop frequent customer polling at a UX deadline, and continue slower server-side reconciliation until expiry or an operational resolution. Honor `429 Retry-After` and do not retry non-retryable errors.

`review_required` is a hold state that may later resolve. Stop promising an immediate result, but retain webhook/reconciliation coverage.

## Timeouts and retries

- Put a bounded timeout on API requests.
- Retry safe GETs with capped exponential backoff and jitter for network failures, `429`, and retryable `5xx` responses.
- Retry create only with the identical body and identical idempotency key.
- Do not automatically retry `401`, `403`, `402`, validation failures, or idempotency conflicts until the cause changes.
- Prevent retry storms by limiting attempts/concurrency and observing `Retry-After`.

## Framework notes

Use whatever the merchant already uses. Typical homes:

- Next.js/React/Remix: call Verify Checkout in route handlers/server actions; client components call the merchant backend.
- Express/Fastify/Hono: HTTP helper plus raw-body handling on the webhook route.
- Laravel/PHP: service class; `$request->getContent()` for signature verification; queue only if the app already queues.
- Django/FastAPI: server-side client; `request.body()` before JSON decoding.
- Mobile: never embed the API key. The app calls the merchant backend and opens the returned hosted URL in an external browser or secure browser surface.
