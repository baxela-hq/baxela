"use client";

import { useEffect } from "react";
import * as Sentry from "@sentry/nextjs";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";

/**
 * Error boundary for failures inside the [locale] segment — it still sits
 * inside the locale layout's providers, so translations and theme work.
 * Handled errors don't reach global handlers, so capture explicitly.
 */
export default function LocaleError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const t = useTranslations("shared.common");

  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 px-6 text-center">
      <p className="text-2xl font-semibold text-foreground">
        {t("messages.error.general")}
      </p>
      <Button onClick={() => reset()}>{t("form.actions.retry")}</Button>
    </div>
  );
}
