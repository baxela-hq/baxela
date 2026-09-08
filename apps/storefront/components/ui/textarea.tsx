"use client";

import {
  forwardRef,
  useId,
  type TextareaHTMLAttributes,
} from "react";
import { cn } from "@/lib/utils";

export interface TextareaProps
  extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  helperText?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  function Textarea({ label, helperText, className, id, ...props }, ref) {
    const autoId = useId();
    const textareaId = id ?? autoId;

    return (
      <div className="w-full">
        {label ? (
          <label
            htmlFor={textareaId}
            className="mb-2 block text-sm text-secondary-text"
          >
            {label}
          </label>
        ) : null}
        <textarea
          ref={ref}
          id={textareaId}
          className={cn(
            "min-h-36 w-full rounded-default border border-border bg-white px-4 py-3 text-base text-foreground outline-none transition-colors placeholder:text-secondary-text focus:border-primary",
            className,
          )}
          {...props}
        />
        {helperText ? (
          <p className="mt-2 text-sm text-secondary-text">{helperText}</p>
        ) : null}
      </div>
    );
  },
);
