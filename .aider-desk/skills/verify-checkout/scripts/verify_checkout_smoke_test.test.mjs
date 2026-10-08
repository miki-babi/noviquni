import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { createServer } from "node:http";
import { once } from "node:events";
import { fileURLToPath } from "node:url";
import test from "node:test";

const scriptPath = fileURLToPath(
  new URL("./verify_checkout_smoke_test.mjs", import.meta.url),
);

test("create sends the public contract and redacts checkout_url", async (t) => {
  let received;
  const server = createServer(async (request, response) => {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    received = {
      method: request.method,
      url: request.url,
      headers: request.headers,
      body: JSON.parse(Buffer.concat(chunks).toString("utf8")),
    };
    response.writeHead(201, { "content-type": "application/json" });
    response.end(
      JSON.stringify({
        data: {
          id: "dep_test",
          status: "awaiting_transfer",
          checkout_url: ["https://checkout.verify.et/c/", "sensitive_test_token"].join(""),
          merchant_customer_id: "customer_private_value",
          amount: "123.45",
          metadata: { internal_order_id: "private_order_123" },
        },
        meta: { requestId: "req_test", apiVersion: "2026-06-01" },
      }),
    );
  });
  t.after(() => server.close());
  server.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert(address && typeof address !== "string");

  const child = spawn(process.execPath, [scriptPath, "--allow-loopback-base-url"], {
    env: {
      ...process.env,
      VERIFY_CHECKOUT_API_KEY: ["vchk", "local_test_not_a_secret"].join("_"),
      VERIFY_CHECKOUT_BASE_URL: `http://127.0.0.1:${address.port}`,
      VERIFY_CHECKOUT_CUSTOMER_ID: "customer_test",
      VERIFY_CHECKOUT_IDEMPOTENCY_KEY: "checkout_attempt_test",
      VERIFY_CHECKOUT_RETURN_URL: "http://localhost:3000/payments/return",
      VERIFY_CHECKOUT_SMOKE_MODE: "create",
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let stdout = "";
  let stderr = "";
  child.stdout.setEncoding("utf8");
  child.stderr.setEncoding("utf8");
  child.stdout.on("data", (chunk) => {
    stdout += chunk;
  });
  child.stderr.on("data", (chunk) => {
    stderr += chunk;
  });
  const [code] = await once(child, "close");

  assert.equal(code, 0, stderr);
  assert.equal(received.method, "POST");
  assert.equal(received.url, "/v1/deposits");
  assert.equal(received.headers["verifycheckout-version"], "2026-06-01");
  assert.equal(received.headers["idempotency-key"], "checkout_attempt_test");
  assert.equal(received.body.merchant_customer_id, "customer_test");
  assert.equal(received.body.return_url, "http://localhost:3000/payments/return");
  const output = JSON.parse(stdout);
  assert.match(output.body.data.checkout_url, /^\[redacted:/);
  assert.doesNotMatch(stdout, /sensitive_test_token/);
  assert.doesNotMatch(stdout, /customer_private_value/);
  assert.doesNotMatch(stdout, /123\.45/);
  assert.doesNotMatch(stdout, /private_order_123/);
});

test("refuses to send credentials to a configured remote origin", async () => {
  const child = spawn(process.execPath, [scriptPath], {
    env: {
      ...process.env,
      VERIFY_CHECKOUT_API_KEY: ["vchk", "local_test_not_a_secret"].join("_"),
      VERIFY_CHECKOUT_BASE_URL: "https://example.com",
      VERIFY_CHECKOUT_DEPOSIT_ID: "dep_test",
      VERIFY_CHECKOUT_SMOKE_MODE: "get",
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let stderr = "";
  child.stderr.setEncoding("utf8");
  child.stderr.on("data", (chunk) => {
    stderr += chunk;
  });
  const [code] = await once(child, "close");
  assert.equal(code, 1);
  assert.match(stderr, /restricted to loopback tests/);
});

test("rejects oversized API responses", async (t) => {
  const server = createServer((_request, response) => {
    response.writeHead(200, { "content-type": "application/json" });
    response.end(JSON.stringify({ data: { value: "x".repeat(1024 * 1024) } }));
  });
  t.after(() => server.close());
  server.listen(0, "127.0.0.1");
  await once(server, "listening");
  const address = server.address();
  assert(address && typeof address !== "string");

  const child = spawn(process.execPath, [scriptPath, "--allow-loopback-base-url"], {
    env: {
      ...process.env,
      VERIFY_CHECKOUT_API_KEY: ["vchk", "local_test_not_a_secret"].join("_"),
      VERIFY_CHECKOUT_BASE_URL: `http://127.0.0.1:${address.port}`,
      VERIFY_CHECKOUT_DEPOSIT_ID: "dep_test",
      VERIFY_CHECKOUT_SMOKE_MODE: "get",
    },
    stdio: ["ignore", "pipe", "pipe"],
  });
  let stderr = "";
  child.stderr.setEncoding("utf8");
  child.stderr.on("data", (chunk) => {
    stderr += chunk;
  });
  const [code] = await once(child, "close");
  assert.equal(code, 1);
  assert.match(stderr, /Response exceeded the 1048576-byte safety limit/);
});
