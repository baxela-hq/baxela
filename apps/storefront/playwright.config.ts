import { defineConfig, devices } from "@playwright/test";

/**
 * E2E tests run against a live compose stack (the `frontend` + `mail`
 * profiles), never a mocked browser environment:
 *
 *   docker compose --profile frontend --profile mail up -d
 *   pnpm test:e2e
 *
 * The stack must be seeded (core, media, catalog, payment, shipping, tax,
 * setting module seeders) with the manual payment method active — the spec
 * completes checkout through it without touching a real gateway.
 */
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  timeout: 120_000,
  expect: { timeout: 15_000 },
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: process.env.CI ? "github" : "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost:3000",
    locale: "en-US",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
