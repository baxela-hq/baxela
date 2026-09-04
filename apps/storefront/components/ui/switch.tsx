"use client";

import { cn } from "@/lib/utils";

export interface SwitchProps {
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  label: string;
  disabled?: boolean;
}

/**
 * Toggle switch. The knob is positioned with flex justify-start/end, so the
 * on/off sides mirror automatically in RTL.
 */
export function Switch({
  checked,
  onCheckedChange,
  label,
  disabled,
}: SwitchProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onCheckedChange(!checked)}
      className={cn(
        "inline-flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors",
        checked ? "justify-end bg-accent" : "justify-start bg-border",
        disabled && "pointer-events-none opacity-60",
      )}
    >
      <span
        aria-hidden="true"
        className="size-5 rounded-full bg-white shadow-sm"
      />
    </button>
  );
}
