import { useEffect, type ReactNode } from "react";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
  className?: string;
}

export function Dialog({ open, onClose, title, description, children, footer, className }: DialogProps) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[10000] flex items-center justify-center p-4">
      <div className="absolute inset-0 animate-fade-in bg-black/60" onClick={onClose} />
      <div
        role="dialog"
        aria-modal="true"
        className={cn("relative z-10 grid w-full max-w-lg animate-zoom-in gap-4 rounded-xl border bg-card p-5 text-card-foreground shadow-lg", className)}
      >
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="absolute right-4 top-4 cursor-pointer rounded-sm text-muted-foreground opacity-70 hover:opacity-100"
        >
          <X className="h-4 w-4" />
        </button>
        <div className="flex flex-col gap-1.5 pr-6">
          <h2 className="text-base font-semibold">{title}</h2>
          {description && <p className="text-sm text-muted-foreground">{description}</p>}
        </div>
        {children}
        {footer && <div className="flex flex-col-reverse gap-2 @sm:flex-row @sm:justify-end">{footer}</div>}
      </div>
    </div>
  );
}
