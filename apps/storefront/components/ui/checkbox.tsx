"use client";

import { useId, useState } from "react";
import { cn } from "@/lib/utils";
import { CheckIcon } from "@/components/ui/icons";

export interface CheckboxProps {
  label?: string;
  checked?: boolean;
  defaultChecked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
  className?: string;
}

export function Checkbox({
  label,
  checked,
  defaultChecked,
  onCheckedChange,
  className,
}: CheckboxProps) {
  const autoId = useId();
  const [internal, setInternal] = useState(defaultChecked ?? false);
  const isControlled = checked !== undefined;
  const value = isControlled ? checked : internal;

  return (
    <label
      htmlFor={autoId}
      className={cn("inline-flex w-fit cursor-pointer select-none items-center gap-3", className)}
    >
      <input
        id={autoId}
        type="checkbox"
        className="sr-only"
        checked={value}
        onChange={(e) => {
          if (!isControlled) setInternal(e.target.checked);
          onCheckedChange?.(e.target.checked);
        }}
      />
      <span
        aria-hidden="true"
        className={cn(
          "grid size-5 shrink-0 place-items-center rounded border transition-colors",
          value ? "border-primary bg-primary" : "border-border bg-white",
        )}
      >
        <CheckIcon
          className={cn(
            "size-3.5 text-white transition-opacity",
            value ? "opacity-100" : "opacity-0",
          )}
        />
      </span>
      {label ? <span className="text-sm text-secondary-text">{label}</span> : null}
    </label>
  );
}
