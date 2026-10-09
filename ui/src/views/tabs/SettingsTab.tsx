import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { useToast } from "@/components/ui/toast";
import { PasswordField, passwordProblem } from "@/components/shared";
import { callApi, useConfig } from "@/lib/api";
import { cn } from "@/lib/utils";
import type { Vps } from "@/lib/types";
import { useT } from "@/i18n";

export function SettingsTab({ vps, onDone }: { vps: Vps; onDone: () => Promise<void> }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const [label, setLabel] = useState(vps.label || vps.name);
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState<"label" | "password" | null>(null);

  const problem = password ? passwordProblem(password, t) : null;
  const labelInvalid = !/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,62}$/.test(label.trim());

  const run = async (kind: "label" | "password", action: string, data: Record<string, unknown>, done: string) => {
    setBusy(kind);
    try {
      await callApi(config, action, data);
      toast.success(done);
      if (kind === "password") setPassword("");
      await onDone();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="grid gap-4 @lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>{t("settings.labelTitle")}</CardTitle>
          <CardDescription>{t("settings.labelHint")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div className="grid gap-1.5">
            <Label htmlFor="cp-label">{t("settings.label")}</Label>
            <Input id="cp-label" value={label} maxLength={63} onChange={(e) => setLabel(e.target.value)} />
            {labelInvalid && <p className="text-xs text-destructive">{t("settings.labelInvalid")}</p>}
          </div>
          <div className="flex justify-end">
            <Button
              disabled={labelInvalid || label.trim() === (vps.label || vps.name)}
              loading={busy === "label"}
              onClick={() => run("label", "label", { label: label.trim() }, t("settings.labelSaved"))}
            >
              {t("common.save")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("settings.passwordTitle")}</CardTitle>
          <CardDescription>{t("settings.passwordHint")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div className="grid gap-1.5">
            <Label htmlFor="cp-password">{t("settings.newPassword")}</Label>
            <PasswordField id="cp-password" value={password} onChange={setPassword} />
            {problem && <p className={cn("text-xs text-destructive")}>{problem}</p>}
          </div>
          <div className="flex justify-end">
            <Button
              disabled={!password || !!problem || vps.status !== "active"}
              loading={busy === "password"}
              onClick={() => run("password", "password", { password }, t("settings.passwordSaved"))}
            >
              {t("settings.changePassword")}
            </Button>
          </div>
          {vps.status !== "active" && <p className="text-xs text-muted-foreground">{t("settings.passwordNeedsRunning")}</p>}
        </CardContent>
      </Card>
    </div>
  );
}
