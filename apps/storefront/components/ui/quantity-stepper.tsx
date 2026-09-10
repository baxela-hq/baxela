"use client";

// Shared −/value/+ stepper used by the PDP buy box and the cart page. Decrease
// is disabled once `value <= min` (default 1 at the default min), increase once
// `value >= max`.

interface QuantityStepperProps {
  value: number;
  onDecrease: () => void;
  onIncrease: () => void;
  decreaseLabel: string;
  increaseLabel: string;
  min?: number;
  max?: number;
  disabled?: boolean;
}

export function QuantityStepper({
  value,
  onDecrease,
  onIncrease,
  decreaseLabel,
  increaseLabel,
  min,
  max,
  disabled = false,
}: QuantityStepperProps) {
  return (
    <div className="flex items-center rounded-default border border-border">
      <button
        type="button"
        aria-label={decreaseLabel}
        disabled={disabled || (min !== undefined && value <= min)}
        onClick={onDecrease}
        className="px-4 py-3 text-foreground transition-colors hover:bg-muted disabled:opacity-40"
      >
        −
      </button>
      <span className="px-4 text-sm text-foreground" aria-live="polite">
        {value.toLocaleString()}
      </span>
      <button
        type="button"
        aria-label={increaseLabel}
        disabled={disabled || (max !== undefined && value >= max)}
        onClick={onIncrease}
        className="px-4 py-3 text-foreground transition-colors hover:bg-muted disabled:opacity-40"
      >
        +
      </button>
    </div>
  );
}