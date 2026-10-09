import { forwardRef, type ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "accent" | "outline";
  size?: "default" | "sm";
  fullWidth?: boolean;
}

const variantClasses: Record<NonNullable<ButtonProps["variant"]>, string> = {
  primary: "bg-primary text-primary-foreground hover:bg-primary/90",
  accent: "bg-accent text-accent-foreground hover:bg-accent/90",
  outline: "border border-border bg-background text-foreground hover:bg-muted",
};

const sizeClasses: Record<NonNullable<ButtonProps["size"]>, string> = {
  default: "h-14 px-6 text-base",
  sm: "h-10 px-5 text-sm",
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  function Button(
    {
      variant = "primary",
      size = "default",
      fullWidth = true,
      className,
      type = "button",
      ...props
    },
    ref,
  ) {
    return (
      <button
        ref={ref}
        type={type}
        className={cn(
          "inline-flex items-center justify-center rounded-default font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:pointer-events-none disabled:opacity-60",
          sizeClasses[size],
          fullWidth && "w-full",
          variantClasses[variant],
          className,
        )}
        {...props}
      />
    );
  },
);
