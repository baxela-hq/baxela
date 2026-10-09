import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "404 — Baxela Storefront",
  description: "The page you are looking for does not exist.",
};

/**
 * Routing-level 404 for URLs that match no route (and for notFound() throws
 * that can't compose a boundary under the top-level app/[locale] layout).
 * It bypasses the app tree entirely, so it brings its own html shell, styles
 * and both UI languages — no locale param exists at this level.
 */
export default function GlobalNotFound() {
  return (
    <html lang="en">
      <body className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background px-6 text-center">
        <p className="text-5xl font-bold text-foreground">404</p>
        <p className="text-base text-secondary-text">
          Page not found · صفحه مورد نظر پیدا نشد
        </p>
        <Link
          href="/"
          className="text-sm font-medium text-accent underline underline-offset-4"
        >
          Back to homepage · بازگشت به صفحه اصلی
        </Link>
      </body>
    </html>
  );
}
