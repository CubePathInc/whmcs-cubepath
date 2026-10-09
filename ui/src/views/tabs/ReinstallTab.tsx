import { useMemo, useState } from "react";
import { AlertTriangle, Check, Wrench } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, EmptyState, ErrorState, PasswordField, passwordProblem } from "@/components/shared";
import { OsIcon } from "@/components/OsIcon";
import { callApi, useConfig, useQuery } from "@/lib/api";
import { cn, generatePassword } from "@/lib/utils";
import type { TemplateOption, Vps } from "@/lib/types";
import { useT } from "@/i18n";

export function ReinstallTab({ vps, onDone }: { vps: Vps; onDone: () => Promise<void> }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const templates = useQuery<TemplateOption[]>("templates");
  const [selected, setSelected] = useState<string | null>(null);
  const [password, setPassword] = useState(() => generatePassword());
  const [confirming, setConfirming] = useState(false);
  const [typed, setTyped] = useState("");
  const [filter, setFilter] = useState("");

  const list = useMemo(
    () => (templates.data ?? []).filter((x) => x.label.toLowerCase().includes(filter.toLowerCase())),
    [templates.data, filter],
  );

  if (templates.error && !templates.data) return <ErrorState message={templates.error} onRetry={templates.reload} />;
  if (!templates.data) return <Skeleton className="h-64 rounded-xl" />;

  const problem = passwordProblem(password, t);
  const busy = vps.status !== "active" && vps.status !== "stopped";
  const confirmWord = vps.name;

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("reinstall.title")}</CardTitle>
          <CardDescription>{t("reinstall.description")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {templates.data.length > 8 && (
            <Input placeholder={t("reinstall.search")} value={filter} onChange={(e) => setFilter(e.target.value)} className="@sm:max-w-xs" />
          )}
          {templates.data.length === 0 ? (
            <EmptyState icon={Wrench} title={t("reinstall.noTemplates")} />
          ) : (
            <div className="grid gap-2 @sm:grid-cols-2 @lg:grid-cols-3 @xl:grid-cols-4">
              {list.map((tpl) => {
                const active = selected === tpl.value;
                const current = vps.template?.template_name === tpl.value;
                return (
                  <button
                    type="button"
                    key={tpl.value}
                    onClick={() => setSelected(tpl.value)}
                    className={cn(
                      "flex cursor-pointer items-center gap-3 rounded-lg border bg-card p-3 text-left transition-colors hover:bg-accent",
                      active && "border-primary ring-1 ring-primary",
                    )}
                  >
                    <OsIcon name={tpl.value} className="h-9 w-9 shrink-0" />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium">{tpl.label}</div>
                      {current && <div className="text-xs text-muted-foreground">{t("reinstall.current")}</div>}
                    </div>
                    {active && <Check className="h-4 w-4 shrink-0" />}
                  </button>
                );
              })}
            </div>
          )}

          <div className="grid gap-1.5 @sm:max-w-md">
            <Label htmlFor="cp-reinstall-password">{t("reinstall.password")}</Label>
            <PasswordField id="cp-reinstall-password" value={password} onChange={setPassword} />
            <p className={cn("text-xs", problem ? "text-destructive" : "text-muted-foreground")}>{problem ?? t("reinstall.passwordHint")}</p>
          </div>

          <div className="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
            <span>{t("reinstall.warning")}</span>
          </div>

          <div className="flex justify-end">
            <Button variant="destructive" disabled={!selected || !!problem || busy} onClick={() => setConfirming(true)}>
              {t("reinstall.button")}
            </Button>
          </div>
        </CardContent>
      </Card>

      {confirming && selected && (
        <ConfirmDialog
          open
          destructive
          onClose={() => {
            setConfirming(false);
            setTyped("");
          }}
          title={t("reinstall.confirmTitle")}
          description={t("reinstall.confirmDescription", { os: templates.data.find((x) => x.value === selected)?.label ?? selected })}
          confirmLabel={t("reinstall.button")}
          disabled={typed.trim() !== confirmWord}
          onConfirm={async () => {
            try {
              await callApi(config, "reinstall", { template: selected, password });
              toast.success(t("reinstall.started"));
              setConfirming(false);
              setTyped("");
              await onDone();
            } catch (e) {
              toast.error((e as Error).message);
            }
          }}
        >
          <div className="grid gap-1.5">
            <Label htmlFor="cp-reinstall-confirm">{t("reinstall.typeToConfirm", { name: confirmWord })}</Label>
            <Input id="cp-reinstall-confirm" value={typed} autoComplete="off" onChange={(e) => setTyped(e.target.value)} />
          </div>
        </ConfirmDialog>
      )}
    </div>
  );
}
