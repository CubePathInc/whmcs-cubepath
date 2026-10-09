import { useState, type ComponentType, type ReactNode } from "react";
import { AlertCircle, Check, Copy, Eye, EyeOff, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { generatePassword } from "@/lib/utils";
import { useT } from "@/i18n";
import type { VpsStatus } from "@/lib/types";

const TRANSITIONAL: VpsStatus[] = ["deploying", "updating", "resizing", "deleting"];

export function isTransitional(status: string | undefined): boolean {
  return !!status && TRANSITIONAL.includes(status as VpsStatus);
}

export function StatusBadge({ status }: { status: string }) {
  const t = useT();
  const variant =
    status === "active" ? "success" : status === "stopped" ? "secondary" : isTransitional(status) ? "warning" : "destructive";
  const dot =
    status === "active" ? "bg-success" : status === "stopped" ? "bg-muted-foreground" : isTransitional(status) ? "bg-warning" : "bg-destructive";
  const known = ["active", "stopped", "updating", "failed", "deleting", "deploying", "suspended", "resizing", "deleted"];
  return (
    <Badge variant={variant}>
      <span className={`h-1.5 w-1.5 rounded-full ${dot} ${status === "active" || isTransitional(status) ? "animate-pulse" : ""}`} />
      {known.includes(status) ? t(`status.${status}` as "status.active") : status}
    </Badge>
  );
}

export function CopyButton({ value, label }: { value: string; label?: string }) {
  const t = useT();
  const [copied, setCopied] = useState(false);
  return (
    <button
      type="button"
      aria-label={label ?? t("common.copy")}
      title={label ?? t("common.copy")}
      className="inline-flex cursor-pointer items-center text-muted-foreground hover:text-foreground"
      onClick={() => {
        void navigator.clipboard?.writeText(value).then(() => {
          setCopied(true);
          window.setTimeout(() => setCopied(false), 1500);
        });
      }}
    >
      {copied ? <Check className="h-3.5 w-3.5 text-success" /> : <Copy className="h-3.5 w-3.5" />}
    </button>
  );
}

interface ConfirmProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
  title: string;
  description: ReactNode;
  confirmLabel?: string;
  destructive?: boolean;
  children?: ReactNode;
  disabled?: boolean;
}

export function ConfirmDialog({ open, onClose, onConfirm, title, description, confirmLabel, destructive, children, disabled }: ConfirmProps) {
  const t = useT();
  const [busy, setBusy] = useState(false);
  return (
    <Dialog
      open={open}
      onClose={() => !busy && onClose()}
      title={title}
      description={description}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={busy}>
            {t("common.cancel")}
          </Button>
          <Button
            variant={destructive ? "destructive" : "default"}
            loading={busy}
            disabled={disabled}
            onClick={async () => {
              setBusy(true);
              try {
                await onConfirm();
              } finally {
                setBusy(false);
              }
            }}
          >
            {confirmLabel ?? t("common.confirm")}
          </Button>
        </>
      }
    >
      {children}
    </Dialog>
  );
}

export function EmptyState({ icon: Icon, title, description, action }: { icon: ComponentType<{ className?: string }>; title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
      <Icon className="mb-3 h-10 w-10 text-muted-foreground" />
      <h3 className="text-sm font-medium">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-xs text-muted-foreground">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const t = useT();
  return (
    <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
      <AlertCircle className="mb-3 h-10 w-10 text-destructive" />
      <h3 className="text-sm font-medium">{t("common.errorTitle")}</h3>
      <p className="mt-1 max-w-md break-words text-xs text-muted-foreground">{message}</p>
      {onRetry && (
        <Button variant="outline" size="sm" className="mt-4" onClick={onRetry}>
          <RefreshCw />
          {t("common.retry")}
        </Button>
      )}
    </div>
  );
}

/** Password input with show/hide and a generator. */
export function PasswordField({ value, onChange, id }: { value: string; onChange: (v: string) => void; id?: string }) {
  const t = useT();
  const [visible, setVisible] = useState(false);
  return (
    <div className="flex gap-2">
      <div className="relative flex-1">
        <Input
          id={id}
          type={visible ? "text" : "password"}
          value={value}
          autoComplete="new-password"
          onChange={(e) => onChange(e.target.value)}
          className="pr-9 font-mono"
        />
        <button
          type="button"
          aria-label={visible ? t("common.hide") : t("common.show")}
          className="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
          onClick={() => setVisible((v) => !v)}
        >
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
      <Button
        variant="outline"
        onClick={() => {
          onChange(generatePassword());
          setVisible(true);
        }}
      >
        {t("common.generate")}
      </Button>
    </div>
  );
}

/** At least 8 characters with uppercase, lowercase and digits, as the API requires. */
export function passwordProblem(value: string, t: ReturnType<typeof useT>): string | null {
  if (value.length < 8) return t("password.tooShort");
  if (!/[A-Z]/.test(value) || !/[a-z]/.test(value) || !/\d/.test(value)) return t("password.weak");
  return null;
}

export function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b py-2.5 text-sm last:border-0">
      <span className="text-muted-foreground">{label}</span>
      <span className="min-w-0 truncate text-right font-medium">{children}</span>
    </div>
  );
}
