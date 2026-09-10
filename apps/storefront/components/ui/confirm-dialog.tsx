"use client";

import { useEffect } from "react";
import { Button } from "@/components/ui/button";

/**
 * Small centered confirmation modal (bordered card over a dimmed
 * backdrop). Escape and backdrop clicks cancel; labels are passed in by
 * the caller so the component stays translation-agnostic.
 */
export function ConfirmDialog({
  title,
  message,
  confirmLabel,
  cancelLabel,
  pending = false,
  onConfirm,
  onCancel,
}: {
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  pending?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  useEffect(() => {
    const onKeydown = (event: KeyboardEvent) => {
      if (event.key === "Escape") onCancel();
    };
    window.addEventListener("keydown", onKeydown);
    return () => window.removeEventListener("keydown", onKeydown);
  }, [onCancel]);

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-primary/40 px-6"
      onClick={onCancel}
      role="presentation"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        onClick={(event) => event.stopPropagation()}
        className="w-full max-w-sm rounded-default border border-border-light bg-white p-6 shadow-sm"
      >
        <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {title}
        </h2>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {message}
        </p>
        <div className="mt-6 grid grid-cols-2 gap-3">
          <Button
            variant="outline"
            className="h-12 text-sm"
            autoFocus
            disabled={pending}
            onClick={onCancel}
          >
            {cancelLabel}
          </Button>
          <Button className="h-12 text-sm" disabled={pending} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
