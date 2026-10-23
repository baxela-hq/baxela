import { expect, request, test } from "@playwright/test";

/**
 * The customer happy path, end to end against the running stack:
 * sign up → email-OTP activation (code read from Mailpit) → sign in →
 * browse → PDP → cart → checkout (address → shipping quote → manual payment)
 * → order placed → order visible in the profile.
 *
 * Selectors intentionally ride on English UI strings and input types —
 * the spec runs with `locale: en-US` and the `en` locale is the reference
 * for the i18n-parity CI check, so these strings are load-bearing.
 */

const MAILPIT = process.env.E2E_MAILPIT_URL ?? "http://localhost:8025";
const STOREFRONT = process.env.E2E_BASE_URL ?? "http://localhost:3000";

type MailpitMessage = { ID: string; To: { Address: string }[] };

/** Fill and re-verify: on a cold dev server React can hydrate AFTER the
 *  fill and reset the input — refill whatever hydration discarded. */
async function stableFill(
  locator: import("@playwright/test").Locator,
  value: string
) {
  await locator.fill(value);
  if ((await locator.inputValue()) !== value) {
    await locator.fill(value);
  }
}

/** Poll Mailpit until the activation email arrives; return its 6-digit code. */
async function activationCode(email: string): Promise<string> {
  for (let attempt = 0; attempt < 30; attempt++) {
    const ctx = await request.newContext();
    try {
      const res = await ctx.get(`${MAILPIT}/api/v1/messages?limit=10`);
      const { messages = [] } = (await res.json()) as { messages?: MailpitMessage[] };
      const hit = messages.find((m) => m.To.some((to) => to.Address === email));

      if (hit) {
        const body = await (
          await ctx.get(`${MAILPIT}/api/v1/message/${hit.ID}`)
        ).json();
        const code = String(body.Text ?? body.HTML ?? "").match(/\b\d{6}\b/)?.[0];
        if (code) return code;
      }
    } finally {
      await ctx.dispose();
    }
    await new Promise((resolve) => setTimeout(resolve, 1_000));
  }
  throw new Error(`Activation email for ${email} never reached Mailpit`);
}

test("customer signs up and completes a manual-payment checkout", async ({ page }) => {
  // -- Sign up ---------------------------------------------------------------
  const email = `e2e-${Date.now()}@example.test`;
  const password = "E2eShopping!2026";

  await page.goto("/en/signup");
  await stableFill(page.getByPlaceholder("Enter your name"), "E2E Shopper");
  await stableFill(page.locator('input[type="email"]'), email);
  const passwords = page.locator('input[type="password"]');
  await stableFill(passwords.nth(0), password);
  await stableFill(passwords.nth(1), password);
  await page.getByRole("button", { name: "Signup" }).click();

  // -- Email-OTP activation, code read from Mailpit ---------------------------
  await expect(page).toHaveURL(/enter-otp/);
  await stableFill(page.getByPlaceholder("Enter the code"), await activationCode(email));
  await page.getByRole("button", { name: "Verify" }).click();
  await expect(page).toHaveURL(/login/);

  // -- Sign in ----------------------------------------------------------------
  await stableFill(page.locator('input[type="email"]'), email);
  await stableFill(page.locator('input[type="password"]'), password);
  await page.getByRole("button", { name: "Login" }).click();
  await expect(page).not.toHaveURL(/login/);

  // -- Browse: home → first product -------------------------------------------
  await page.goto("/en");
  const productLink = page.locator('a[href*="/products/"]').first();
  await productLink.click();
  await expect(page).toHaveURL(/\/products\//);

  // -- PDP: pick a variant (single chip selects the default) -------------------
  const sizeChips = page.locator("button[aria-pressed]");
  if ((await sizeChips.count()) > 1) await sizeChips.first().click();

  // -- Display-currency switch: the price re-renders in the seeded second
  //    currency (IRR, rate 600000) and switches back cleanly ----------------
  const priceLocator = page.locator("p.text-2xl").first();
  const basePrice = await priceLocator.innerText();
  await page.getByRole("button", { name: "Display currency" }).click();
  await page.getByRole("option", { name: "IRR", exact: false }).click();
  await expect
    .poll(async () => priceLocator.innerText(), { timeout: 15_000 })
    .not.toBe(basePrice);
  await page.getByRole("button", { name: "Display currency" }).click();
  await page.getByRole("option", { name: "USD", exact: false }).click();
  await expect
    .poll(async () => priceLocator.innerText(), { timeout: 15_000 })
    .toBe(basePrice);

  await page.getByRole("button", { name: "Add to cart" }).click();
  await page.getByRole("link", { name: "View cart" }).click();
  await expect(page).toHaveURL(/\/cart/);

  // -- Cart → checkout ---------------------------------------------------------
  await page.getByRole("button", { name: "Proceed to checkout" }).click();
  await expect(page).toHaveURL(/\/checkout/);

  // -- Address form (fresh account → no saved addresses) -----------------------
  await expect(page.getByPlaceholder("First and last name")).toBeVisible();
  await page.getByPlaceholder("First and last name").fill("E2E Shopper");
  await page.getByPlaceholder("Phone number").fill("+1 555 000 1234");
  await page.getByPlaceholder("Street address, number, unit").fill("742 Evergreen Terrace");
  await page.getByPlaceholder("City").fill("Springfield");
  await page.getByPlaceholder("Postal code").fill("12345");
  // Domestic US zone is seeded; "Rest of World" catches everything else.
  await page.locator("select").selectOption({ label: "United States" });
  await page.getByRole("button", { name: "Save address" }).click();

  // -- Shipping quote + payment ------------------------------------------------
  const shippingRadio = page.locator('input[type="radio"][name="shipping"]').first();
  await expect(shippingRadio).toBeVisible();
  await shippingRadio.check();

  await page.getByRole("radio", { name: "Manual payment" }).check();

  await page.getByRole("button", { name: "Place order" }).click();

  // -- Success panel carries the order code -------------------------------------
  await expect(page.getByText("Order placed!")).toBeVisible();
  const codeText = await page
    .getByText(/Order code:/)
    .innerText();
  const orderCode = codeText.match(/[A-Z0-9-]+/g)?.pop();
  expect(orderCode, "order code captured from success panel").toBeTruthy();

  // -- The order shows up in the customer's profile -----------------------------
  await page.goto("/en/profile/orders");
  await expect(page.getByRole("link", { name: "My Orders" })).toBeVisible();
  // Scoped to the list: a bare getByText(orderCode) also matches the
  // "Order … confirmed" toast, which auto-dismisses to hidden mid-wait.
  await expect(
    page.getByRole("listitem").filter({ hasText: orderCode }).first()
  ).toBeVisible({ timeout: 30_000 });
});

test("first visit defaults to the locale's currency until the visitor picks one", async ({ page }) => {
  // Fresh context → no baxela-currency cookie: European locales start on
  // the seeded EUR default, others on the USD base; an explicit cookie
  // always wins over the locale default.
  const price = page.locator("p.text-2xl").first();

  const openFirstProduct = async (locale: string) => {
    await page.goto(`/${locale}`);
    const link = page.locator(`a[href*="/${locale}/products/"]`).first();
    await link.click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/products/`));
  };

  await openFirstProduct("de");
  await expect(price, "German PDP defaults to EUR").toContainText("€", {
    timeout: 15_000,
  });

  await openFirstProduct("en");
  await expect(price, "English PDP stays on the USD base").toContainText("$", {
    timeout: 15_000,
  });

  await page.context().addCookies([
    { name: "baxela-currency", value: "USD", url: STOREFRONT },
  ]);
  await openFirstProduct("de");
  await expect(
    price,
    "an explicit cookie choice beats the locale default"
  ).toContainText("$", { timeout: 15_000 });
});
