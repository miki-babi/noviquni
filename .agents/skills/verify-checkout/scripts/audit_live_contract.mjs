#!/usr/bin/env node

const source = "https://checkoutapi.verify.et/openapi/public-api.json";
const MAX_OPENAPI_BYTES = 2 * 1024 * 1024;

const fail = (message) => {
  throw new Error(`Contract audit failed: ${message}`);
};

const expect = (condition, message) => {
  if (!condition) fail(message);
};

const expectSameValues = (actual, expected, label) => {
  const actualValues = [...(actual || [])].sort();
  const expectedValues = [...expected].sort();
  expect(
    JSON.stringify(actualValues) === JSON.stringify(expectedValues),
    `${label} changed: expected ${expectedValues.join(", ")}; received ${actualValues.join(", ")}`,
  );
};

const response = await fetch(source, {
  headers: { accept: "application/json" },
  redirect: "error",
  signal: AbortSignal.timeout(15_000),
});
expect(response.ok, `${source} returned HTTP ${response.status}`);

const declaredLength = Number(response.headers.get("content-length"));
expect(
  !Number.isFinite(declaredLength) || declaredLength <= MAX_OPENAPI_BYTES,
  `OpenAPI response exceeded the ${MAX_OPENAPI_BYTES}-byte safety limit`,
);
expect(response.body, "OpenAPI response body is missing");
const reader = response.body.getReader();
const decoder = new TextDecoder();
let receivedBytes = 0;
let responseText = "";
while (true) {
  const { done, value } = await reader.read();
  if (done) break;
  receivedBytes += value.byteLength;
  if (receivedBytes > MAX_OPENAPI_BYTES) {
    await reader.cancel();
    fail(`OpenAPI response exceeded the ${MAX_OPENAPI_BYTES}-byte safety limit`);
  }
  responseText += decoder.decode(value, { stream: true });
}
responseText += decoder.decode();
let document;
try {
  document = JSON.parse(responseText);
} catch {
  fail("OpenAPI response is not valid JSON");
}
expect(document.openapi === "3.1.0", `unexpected OpenAPI version ${document.openapi}`);
expect(document.info?.version === "2026-06-01", `unexpected API version ${document.info?.version}`);
expect(
  document.servers?.some((server) => server.url === "https://checkoutapi.verify.et"),
  "production server URL is missing",
);

for (const [method, path] of [
  ["post", "/v1/deposits"],
  ["get", "/v1/deposits"],
  ["get", "/v1/deposits/{deposit_id}"],
  ["get", "/v1/deposits/{deposit_id}/events"],
]) {
  expect(document.paths?.[path]?.[method], `${method.toUpperCase()} ${path} is missing`);
}

const create = document.paths["/v1/deposits"].post;
const idempotency = create.parameters?.find(
  (parameter) => parameter.name === "Idempotency-Key" && parameter.in === "header",
);
expect(idempotency?.required === true, "create Idempotency-Key is not required");
expect(idempotency?.schema?.minLength === 1, "idempotency minimum length changed");
expect(idempotency?.schema?.maxLength === 256, "idempotency maximum length changed");

const bearer = document.components?.securitySchemes?.bearerAuth;
expect(bearer?.type === "http" && bearer?.scheme === "bearer", "bearer auth changed");

const schemas = document.components?.schemas || {};
const createRequest = schemas.CreateDepositRequest;
expect(createRequest?.additionalProperties === false, "create request is no longer strict");
expect(createRequest?.required?.includes("merchant_customer_id"), "merchant_customer_id requirement changed");
expect(createRequest?.required?.includes("return_url"), "return_url requirement changed");
expect(createRequest?.properties?.currency?.const === "ETB", "currency contract changed");
expectSameValues(
  Object.keys(createRequest?.properties || {}),
  ["merchant_customer_id", "amount", "currency", "payment_method", "return_url"],
  "create request fields",
);

const depositStatuses = schemas.DepositStatus?.enum || [];
expectSameValues(
  depositStatuses,
  [
    "created",
    "assigned",
    "awaiting_transfer",
    "reference_submitted",
    "verification_pending",
    "succeeded",
    "failed",
    "expired",
    "cancelled",
    "review_required",
  ],
  "deposit statuses",
);
expectSameValues(
  schemas.VerificationStatus?.enum,
  [
    "not_started",
    "created",
    "queued",
    "running",
    "succeeded",
    "not_found",
    "duplicate",
    "receiver_mismatch",
    "amount_mismatch",
    "currency_mismatch",
    "transaction_too_old",
    "ambiguous",
    "provider_error",
    "timed_out",
    "cancelled",
    "review_required",
  ],
  "verification statuses",
);
expectSameValues(
  schemas.NotificationStatus?.enum,
  ["not_ready", "pending", "delivered", "retrying", "exhausted", "disabled"],
  "notification statuses",
);

const errorEnvelope = schemas.ApiErrorEnvelope;
expect(errorEnvelope?.required?.includes("error"), "error envelope no longer requires error");
expect(errorEnvelope?.required?.includes("meta"), "error envelope no longer requires meta");
expect(!errorEnvelope?.properties?.data, "error envelope unexpectedly contains data");
for (const field of ["code", "message", "type", "retryable"]) {
  expect(schemas.ApiError?.required?.includes(field), `ApiError no longer requires ${field}`);
}

const webhook = document.webhooks?.depositEvent?.post;
expect(webhook, "depositEvent webhook contract is missing");
const webhookHeaders = new Map(
  (webhook.parameters || []).map((parameter) => [parameter.name, parameter]),
);
for (const name of [
  "VerifyCheckout-Event",
  "VerifyCheckout-Event-Id",
  "VerifyCheckout-Delivery-Id",
  "VerifyCheckout-Timestamp",
  "VerifyCheckout-Signature",
  "VerifyCheckout-Api-Version",
]) {
  expect(webhookHeaders.get(name)?.required === true, `required webhook header ${name} changed`);
}
expect(
  webhookHeaders.get("VerifyCheckout-Signature")?.schema?.pattern === "^v1=[a-f0-9]{64}$",
  "webhook signature format changed",
);
expectSameValues(
  schemas.WebhookEventType?.enum,
  [
    "deposit.created",
    "deposit.assigned",
    "deposit.reference_submitted",
    "deposit.verification_pending",
    "deposit.succeeded",
    "deposit.manually_succeeded",
    "deposit.failed",
    "deposit.manually_failed",
    "deposit.expired",
    "deposit.cancelled",
    "deposit.review_required",
    "deposit.review_resolved",
    "webhook.test",
  ],
  "webhook event types",
);

console.log(
  JSON.stringify(
    {
      ok: true,
      source,
      apiVersion: document.info.version,
      operations: 4,
      depositStatuses: depositStatuses.length,
      webhookEventTypes: schemas.WebhookEventType.enum.length,
    },
    null,
    2,
  ),
);
