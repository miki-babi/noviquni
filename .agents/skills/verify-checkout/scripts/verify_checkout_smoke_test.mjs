#!/usr/bin/env node

const usage = `Verify Checkout smoke test

Usage:
  VERIFY_CHECKOUT_SMOKE_MODE=create node scripts/verify_checkout_smoke_test.mjs
  VERIFY_CHECKOUT_SMOKE_MODE=get node scripts/verify_checkout_smoke_test.mjs
  VERIFY_CHECKOUT_SMOKE_MODE=list node scripts/verify_checkout_smoke_test.mjs
  VERIFY_CHECKOUT_SMOKE_MODE=events node scripts/verify_checkout_smoke_test.mjs

Common required environment:
  VERIFY_CHECKOUT_API_KEY

Create requires:
  VERIFY_CHECKOUT_CUSTOMER_ID
  VERIFY_CHECKOUT_RETURN_URL
  VERIFY_CHECKOUT_IDEMPOTENCY_KEY   Example: checkout_018f_example_attempt
                                     Reuse the same value to replay; mint a new value only for a new attempt.

Create optional:
  VERIFY_CHECKOUT_AMOUNT
  VERIFY_CHECKOUT_PAYMENT_METHOD

Get/events require:
  VERIFY_CHECKOUT_DEPOSIT_ID

Optional common:
  VERIFY_CHECKOUT_API_VERSION    Defaults to 2026-06-01
  VERIFY_CHECKOUT_TIMEOUT_MS     Defaults to 15000

CLI options:
  --show-checkout-url            Print the sensitive hosted URL after create (private terminals only)
  --allow-loopback-base-url      Allow VERIFY_CHECKOUT_BASE_URL for an explicit loopback test server

List optional:
  VERIFY_CHECKOUT_LIST_QUERY     Raw query string, for example status=succeeded&limit=10

This script can create a real deposit. It never opens the hosted checkout or submits a transfer.
`;

const mode = process.env.VERIFY_CHECKOUT_SMOKE_MODE || "help";
if (mode === "help" || process.argv.includes("--help") || process.argv.includes("-h")) {
  console.log(usage);
  process.exit(0);
}

const required = (name) => {
  const value = process.env[name]?.trim();
  if (!value) throw new Error(`${name} is required.\n\n${usage}`);
  return value;
};

const apiKey = required("VERIFY_CHECKOUT_API_KEY");
if (!apiKey.startsWith("vchk_")) {
  throw new Error("VERIFY_CHECKOUT_API_KEY must be the server-side vchk_ secret, not a dashboard fingerprint.");
}

const productionBaseUrl = new URL("https://checkoutapi.verify.et");
const configuredBaseUrl = process.env.VERIFY_CHECKOUT_BASE_URL?.trim();
const baseUrl = new URL(configuredBaseUrl || productionBaseUrl);
const isLoopback = ["localhost", "127.0.0.1", "::1"].includes(baseUrl.hostname);
if (configuredBaseUrl && (!isLoopback || !process.argv.includes("--allow-loopback-base-url"))) {
  throw new Error(
    "VERIFY_CHECKOUT_BASE_URL is restricted to loopback tests and requires --allow-loopback-base-url. Credentials are otherwise sent only to https://checkoutapi.verify.et.",
  );
}
if (configuredBaseUrl && !["http:", "https:"].includes(baseUrl.protocol)) {
  throw new Error("A loopback VERIFY_CHECKOUT_BASE_URL must use HTTP or HTTPS.");
}
if (baseUrl.username || baseUrl.password) {
  throw new Error("VERIFY_CHECKOUT_BASE_URL must not contain credentials.");
}
if (baseUrl.pathname !== "/" || baseUrl.search || baseUrl.hash) {
  throw new Error("VERIFY_CHECKOUT_BASE_URL must be an origin without a path, query, or fragment.");
}

const apiVersion = process.env.VERIFY_CHECKOUT_API_VERSION?.trim() || "2026-06-01";
const timeoutMs = Number(process.env.VERIFY_CHECKOUT_TIMEOUT_MS || "15000");
if (!Number.isInteger(timeoutMs) || timeoutMs < 100 || timeoutMs > 120_000) {
  throw new Error("VERIFY_CHECKOUT_TIMEOUT_MS must be an integer from 100 to 120000.");
}

const MAX_RESPONSE_BYTES = 1024 * 1024;

const readBoundedText = async (response) => {
  const declaredLength = Number(response.headers.get("content-length"));
  if (Number.isFinite(declaredLength) && declaredLength > MAX_RESPONSE_BYTES) {
    throw new Error(`Response exceeded the ${MAX_RESPONSE_BYTES}-byte safety limit.`);
  }
  if (!response.body) return "";

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let size = 0;
  let text = "";
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    size += value.byteLength;
    if (size > MAX_RESPONSE_BYTES) {
      await reader.cancel();
      throw new Error(`Response exceeded the ${MAX_RESPONSE_BYTES}-byte safety limit.`);
    }
    text += decoder.decode(value, { stream: true });
  }
  return text + decoder.decode();
};

const parseBody = async (response) => {
  const responseText = await readBoundedText(response);
  if (!responseText) return null;
  try {
    return JSON.parse(responseText);
  } catch {
    return { unparsed: true };
  }
};

const request = async (path, init = {}) => {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(new URL(path, baseUrl), {
      ...init,
      signal: controller.signal,
      redirect: "error",
      headers: {
        accept: "application/json",
        authorization: `Bearer ${apiKey}`,
        "VerifyCheckout-Version": apiVersion,
        ...(init.body ? { "content-type": "application/json" } : {}),
        ...(init.headers || {}),
      },
    });
    return {
      ok: response.ok,
      status: response.status,
      headers: {
        rateLimit: response.headers.get("ratelimit-limit"),
        rateLimitRemaining: response.headers.get("ratelimit-remaining"),
        rateLimitReset: response.headers.get("ratelimit-reset"),
        retryAfter: response.headers.get("retry-after"),
      },
      body: await parseBody(response),
    };
  } finally {
    clearTimeout(timer);
  }
};

const run = async () => {
  if (mode === "create") {
    const idempotencyKey = required("VERIFY_CHECKOUT_IDEMPOTENCY_KEY");
    if (idempotencyKey.length > 256) {
      throw new Error("VERIFY_CHECKOUT_IDEMPOTENCY_KEY must be 1 to 256 characters.");
    }
    const customerId = required("VERIFY_CHECKOUT_CUSTOMER_ID");
    if (customerId.length > 128) {
      throw new Error("VERIFY_CHECKOUT_CUSTOMER_ID must be 1 to 128 characters.");
    }
    const amount = process.env.VERIFY_CHECKOUT_AMOUNT?.trim();
    if (amount && !/^\d+(\.\d{1,2})?$/.test(amount)) {
      throw new Error("VERIFY_CHECKOUT_AMOUNT must be a nonnegative decimal with at most two places.");
    }
    const returnUrl = new URL(required("VERIFY_CHECKOUT_RETURN_URL"));
    if (returnUrl.protocol !== "https:" && !["localhost", "127.0.0.1", "::1"].includes(returnUrl.hostname)) {
      throw new Error("VERIFY_CHECKOUT_RETURN_URL must use HTTPS unless it is loopback development.");
    }
    if (returnUrl.username || returnUrl.password) {
      throw new Error("VERIFY_CHECKOUT_RETURN_URL must not contain credentials.");
    }
    const paymentMethod = process.env.VERIFY_CHECKOUT_PAYMENT_METHOD?.trim();
    if (paymentMethod && !/^[a-z0-9_:-]{1,64}$/.test(paymentMethod)) {
      throw new Error("VERIFY_CHECKOUT_PAYMENT_METHOD has an invalid provider-code format.");
    }
    const body = {
      merchant_customer_id: customerId,
      ...(amount ? { amount } : {}),
      currency: "ETB",
      ...(paymentMethod ? { payment_method: paymentMethod } : {}),
      return_url: returnUrl.toString(),
    };
    return request("/v1/deposits", {
      method: "POST",
      headers: { "Idempotency-Key": idempotencyKey },
      body: JSON.stringify(body),
    });
  }

  if (mode === "get") {
    const depositId = encodeURIComponent(required("VERIFY_CHECKOUT_DEPOSIT_ID"));
    return request(`/v1/deposits/${depositId}`);
  }

  if (mode === "events") {
    const depositId = encodeURIComponent(required("VERIFY_CHECKOUT_DEPOSIT_ID"));
    return request(`/v1/deposits/${depositId}/events`);
  }

  if (mode === "list") {
    const query = process.env.VERIFY_CHECKOUT_LIST_QUERY?.trim();
    return request(`/v1/deposits${query ? `?${query.replace(/^\?/, "")}` : ""}`);
  }

  throw new Error(`Unknown VERIFY_CHECKOUT_SMOKE_MODE: ${mode}\n\n${usage}`);
};

const copyDefined = (source, keys) =>
  Object.fromEntries(keys.filter((key) => source?.[key] !== undefined).map((key) => [key, source[key]]));

const safeMeta = (meta) =>
  copyDefined(meta, ["requestId", "request_id", "apiVersion", "api_version", "nextCursor", "next_cursor"]);

const safeDeposit = (deposit, { allowCheckoutUrl = false } = {}) => {
  const safe = copyDefined(deposit, [
    "id",
    "status",
    "verification_status",
    "notification_status",
    "expires_at",
    "created_at",
    "updated_at",
  ]);
  if (typeof deposit?.checkout_url === "string") {
    safe.checkout_url = allowCheckoutUrl
      ? deposit.checkout_url
      : "[redacted: pass --show-checkout-url in a private terminal to print]";
  }
  return safe;
};

const safeEvent = (event) =>
  copyDefined(event, ["id", "deposit_id", "type", "sequence", "created_at"]);

const safeBody = (body) => {
  if (!body || typeof body !== "object") return body === null ? null : { unparsed: true };
  const output = {};
  if (body.error && typeof body.error === "object") {
    output.error = copyDefined(body.error, ["code", "type", "retryable"]);
  }
  const allowCheckoutUrl = mode === "create" && process.argv.includes("--show-checkout-url");
  if (Array.isArray(body.data)) {
    output.data = body.data.map(mode === "events" ? safeEvent : (item) => safeDeposit(item));
  } else if (body.data?.items && Array.isArray(body.data.items)) {
    output.data = {
      items: body.data.items.map(mode === "events" ? safeEvent : (item) => safeDeposit(item)),
    };
  } else if (body.data && typeof body.data === "object") {
    output.data = mode === "events" ? safeEvent(body.data) : safeDeposit(body.data, { allowCheckoutUrl });
  }
  if (body.meta && typeof body.meta === "object") output.meta = safeMeta(body.meta);
  if (body.unparsed) output.unparsed = true;
  return output;
};

const prepareOutput = (result) => ({
  ok: result.ok,
  status: result.status,
  headers: result.headers,
  body: safeBody(result.body),
});

run()
  .then((result) => {
    console.log(JSON.stringify(prepareOutput(result), null, 2));
    process.exit(result.ok ? 0 : 1);
  })
  .catch((error) => {
    if (error instanceof Error && error.name === "AbortError") {
      console.error(`Request timed out after ${timeoutMs}ms. A create timeout is ambiguous; retry with the same body and Idempotency-Key.`);
    } else {
      console.error(error instanceof Error ? error.message : String(error));
    }
    process.exit(1);
  });
