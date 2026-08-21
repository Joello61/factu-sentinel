import { Loader2 } from "lucide-react";
import type { ButtonHTMLAttributes } from "react";

/**
 * Primitive Button (docs/11-frontend-design-system.md, section 21). Seules les variantes
 * réellement utilisées à ce stade sont implémentées ; les autres (outline, ghost,
 * destructive, link) seront ajoutées quand un écran en aura réellement besoin.
 */
type ButtonVariant = "primary" | "secondary" | "destructive";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  loading?: boolean;
}

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary: "bg-primary text-white hover:bg-primary/90",
  secondary: "border border-border text-foreground hover:bg-primary/10",
  destructive: "bg-error text-white hover:bg-error/90",
};

export function Button({
  variant = "primary",
  loading = false,
  disabled,
  className = "",
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      {...props}
      aria-busy={loading || undefined}
      disabled={disabled || loading}
      className={`inline-flex w-full items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${VARIANT_CLASSES[variant]} ${className}`}
    >
      {loading ? (
        <Loader2 aria-hidden="true" className="motion-reduce:animate-none animate-spin" size={16} strokeWidth={2} />
      ) : null}
      {children}
    </button>
  );
}
