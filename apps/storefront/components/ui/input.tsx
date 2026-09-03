"use client";

import {
  forwardRef,
  useId,
  useState,
  type InputHTMLAttributes,
  type ReactNode,
} from "react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import { EyeIcon, EyeOffIcon } from "@/components/ui/icons";

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  icon?: ReactNode;
  helperText?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { label, icon, helperText, className, type = "text", id, ...props },
  ref,
) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const [show, setShow] = useState(false);
  const isPassword = type === "password";
  const resolvedType = isPassword && show ? "text" : type;
  const tCommon = useTranslations("shared.common");

  return (
    <div className="w-full">
      {label ? (
        <label htmlFor={inputId} className="mb-2 block text-sm text-secondary-text">
          {label}
        </label>
      ) : null}
      <div className="relative">
        {icon ? (
          <span className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-secondary-text [&>svg]:size-5">
            {icon}
          </span>
        ) : null}
        <input
          ref={ref}
          id={inputId}
          type={resolvedType}
          className={cn(
            "h-14 w-full rounded-default border border-border bg-white text-base text-foreground outline-none transition-colors placeholder:text-secondary-text focus:border-primary",
            icon ? "ps-12" : "ps-4",
            isPassword ? "pe-16" : "pe-4",
            className,
          )}
          {...props}
        />
        {isPassword ? (
          <button
            type="button"
            onClick={() => setShow((v) => !v)}
            className="absolute end-3.5 top-1/2 inline-flex -translate-y-1/2 items-center gap-1 text-sm font-medium text-accent"
          >
            {show ? <EyeOffIcon className="size-4" /> : <EyeIcon className="size-4" />}
            <span>{show ? tCommon("form.actions.hide") : tCommon("form.actions.show")}</span>
          </button>
        ) : null}
      </div>
      {helperText ? <p className="mt-2 text-sm text-secondary-text">{helperText}</p> : null}
    </div>
  );
});
