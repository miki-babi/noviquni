# Merchant Code Structure

Suggested shape for a Verify Checkout integration. Fit the merchant's existing files, naming, and helpers. Improve structure when it is cheap; do not restructure the project or add layers the merchant did not ask for. Do not clone the Verify Checkout monorepo.

## Suggested layout

If the merchant already has payments/orders code, extend it. If you are adding new files, these responsibilities are enough:

```text
verify-checkout/client     # HTTP, auth, version, timeout, envelopes (or the project's HTTP helper)
payments/initiate          # create-or-reuse local attempt, persist deposit id, return checkout URL
payments/fulfill           # credit/order completion keyed by deposit id
webhooks/verify-checkout   # raw-body HMAC and durable accept
```

Storefront (same app or a separate frontend):

- authenticated create action/route that returns `{ checkoutUrl }` after persist
- return page that ignores query success flags
- status poll against the merchant backend

Do not introduce a new framework or queue to match this tree. In Nest, Laravel, Django, or Next.js, put the work in the usual module/service/job locations.

## Prefer when it fits

These help correctness. Skip or shrink them when the merchant's style is simpler.

- One shared helper for Verify Checkout calls, if the project does not already have a generic HTTP client you can reuse.
- Parse success `{ data, meta }` and error `{ error, meta }`. Branch on `error.code` and `error.retryable` when present.
- Build create bodies with only documented fields. Omit `undefined`; never send `merchant_order_id`, `metadata`, or `webhook_url`.
- Persist the local attempt and a stable idempotency key once, then create, then persist `data.id` before returning the URL. Prefer `checkout_<opaque attempt ID or UUID>` and avoid holding a DB transaction open across the remote call when that is easy.
- Reuse that stored key on double-click and retries. Mint a new key only for a new attempt.
- Keep money as decimal strings or the merchant's existing money type.
- Customer UI uses merchant copy, not raw API `message`.
- Validate the API key is present and server-only. Reject client-exposed prefixes such as `NEXT_PUBLIC_` or `VITE_` for secrets.

## TypeScript skeleton

Illustration only. Translate into the merchant's language and existing helpers. A single module is fine.

```ts
type DepositStatus =
  | "created"
  | "assigned"
  | "awaiting_transfer"
  | "reference_submitted"
  | "verification_pending"
  | "succeeded"
  | "failed"
  | "expired"
  | "cancelled"
  | "review_required";

type Deposit = {
  id: string;
  status: DepositStatus;
  amount?: string;
  currency: string;
  checkout_url?: string;
  expires_at: string;
};

export async function createDeposit(
  input: {
    merchant_customer_id: string;
    return_url: string;
    amount?: string;
    currency?: "ETB";
    payment_method?: string;
  },
  idempotencyKey: string,
): Promise<Deposit> {
  const body: Record<string, string> = {
    merchant_customer_id: input.merchant_customer_id,
    return_url: input.return_url,
  };
  if (input.amount !== undefined) body.amount = input.amount;
  if (input.currency !== undefined) body.currency = input.currency;
  if (input.payment_method !== undefined) body.payment_method = input.payment_method;

  const response = await verifyCheckoutFetch("/v1/deposits", {
    method: "POST",
    idempotencyKey,
    json: body,
  });
  if (response.status !== 200 && response.status !== 201) {
    throw toMerchantError(await readError(response));
  }
  // Use the project's runtime validator here; do not trust an unchecked cast.
  const parsed = parseCreateDepositEnvelope(await response.json());
  return parsed.data;
}

export async function initiateCheckout(params: {
  customerId: string;
  localOrderId: string;
  amount?: string;
}): Promise<{ checkoutUrl: string; depositId: string }> {
  const attempt = await findOrCreateAttempt({
    localOrderId: params.localOrderId,
    customerId: params.customerId,
    amount: params.amount,
    // Mint once from the persisted attempt identity. Reuse on every retry.
    newIdempotencyKey: () => `checkout_${crypto.randomUUID()}`,
  });
  const deposit = await createDeposit(
    {
      merchant_customer_id: params.customerId,
      return_url: config.returnUrl,
      amount: params.amount,
      currency: "ETB",
    },
    attempt.idempotencyKey,
  );
  await saveDepositAssociation(attempt.id, deposit);
  if (!deposit.checkout_url) throw new Error("checkout_url missing");
  return { checkoutUrl: deposit.checkout_url, depositId: deposit.id };
}

export async function fulfillIfSucceeded(deposit: Deposit): Promise<"credited" | "already" | "ignored"> {
  if (deposit.status !== "succeeded") return "ignored";
  if (!deposit.amount) throw new Error("succeeded deposit amount missing");
  try {
    await creditOnce({ externalId: deposit.id, amount: deposit.amount, currency: deposit.currency });
    return "credited";
  } catch (error) {
    if (isUniqueConflict(error)) return "already";
    throw error;
  }
}
```

`verifyCheckoutFetch` should set `Authorization: Bearer`, `VerifyCheckout-Version`, `Content-Type`, a bounded timeout, and the stored `Idempotency-Key` on create. `readError` parses `{ error, meta }` and does not assume `data: null`.

## Stack notes

| Stack | Where the work usually lives |
| --- | --- |
| Next.js / Remix | Server action or route handler; webhook route reads raw `arrayBuffer()`; no public env secrets |
| Express / Fastify / Hono | Service or route helper; raw body on the webhook path only |
| NestJS | Payments module/service; preserve raw body on the webhook controller |
| Laravel | Service class; `$request->getContent()`; queue a job if the app already queues |
| Django / FastAPI | View/endpoint; read raw body before JSON |
| Mobile | Backend only. App opens the hosted URL in a system or secure browser |

## Tests

If the repo already tests payments or HTTP clients, add a few cases: create body/headers, `201`/`200` replay, webhook bytes, and no double credit. Do not add a new test framework or a large matrix unless the user asks.
