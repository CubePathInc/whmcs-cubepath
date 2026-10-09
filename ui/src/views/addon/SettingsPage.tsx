import { useEffect, useState } from "react";
import { Eye, EyeOff, KeyRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { useToast } from "@/components/ui/toast";
import { ErrorState, InfoRow } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import type { AddonSettings } from "@/lib/types";
import { useT } from "@/i18n";

export function SettingsPage() {
  const query = useQuery<AddonSettings>("addon.settings");

  if (query.error && !query.data) return <ErrorState message={query.error} onRetry={query.reload} />;
  if (!query.data)
    return (
      <div className="grid gap-4 @lg:grid-cols-2">
        <Skeleton className="h-64 rounded-xl" />
        <Skeleton className="h-64 rounded-xl" />
      </div>
    );

  return (
    <div className="grid items-start gap-4 @lg:grid-cols-2">
      <TokenCard settings={query.data} reload={query.reload} />
      <ProjectCard settings={query.data} reload={query.reload} />
    </div>
  );
}

function TokenCard({ settings, reload }: { settings: AddonSettings; reload: () => Promise<void> }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const [token, setToken] = useState("");
  const [visible, setVisible] = useState(false);
  const [busy, setBusy] = useState(false);

  const save = async () => {
    setBusy(true);
    try {
      const res = await callApi<{ projectMissing: boolean }>(config, "addon.saveToken", { token: token.trim() });
      if (res.projectMissing) toast.error(t("addon.settings.tokenSavedProjectMissing"));
      else toast.success(t("addon.settings.tokenSaved"));
      setToken("");
      await reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("addon.settings.tokenTitle")}</CardTitle>
        <CardDescription>{t("addon.settings.tokenHint")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div>
          {settings.server ? (
            <InfoRow label={t("addon.settings.storedIn")}>
              <a href={`configservers.php?action=manage&id=${settings.server.id}`} className="hover:underline">
                {settings.server.name}
              </a>
            </InfoRow>
          ) : (
            <p className="text-xs text-muted-foreground">{settings.tokenHint ? t("addon.settings.legacy") : t("addon.settings.newServer")}</p>
          )}
          {settings.tokenHint && (
            <InfoRow label={t("addon.settings.current")}>
              <span className="font-mono">••••{settings.tokenHint}</span>
            </InfoRow>
          )}
        </div>

        <form
          className="flex flex-col gap-3"
          onSubmit={(e) => {
            e.preventDefault();
            if (token.trim()) void save();
          }}
        >
          <div className="grid gap-1.5">
            <Label htmlFor="cp-token">{t("addon.settings.newToken")}</Label>
            <div className="relative">
              <KeyRound className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="cp-token"
                type={visible ? "text" : "password"}
                value={token}
                autoComplete="off"
                className="px-9 font-mono"
                onChange={(e) => setToken(e.target.value)}
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
          </div>
          <div className="flex justify-end">
            <Button type="submit" loading={busy} disabled={!token.trim()}>
              {t("addon.settings.saveToken")}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}

function ProjectCard({ settings, reload }: { settings: AddonSettings; reload: () => Promise<void> }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const [projectId, setProjectId] = useState(settings.projectId);
  const [apply, setApply] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => setProjectId(settings.projectId), [settings.projectId]);

  const save = async () => {
    setBusy(true);
    try {
      const res = await callApi<{ updated: number | null }>(config, "addon.saveProject", { projectId, applyToProducts: apply });
      toast.success(res.updated === null ? t("addon.settings.projectSaved") : t("addon.settings.projectSavedProducts", { count: res.updated }));
      setApply(false);
      await reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("addon.settings.projectTitle")}</CardTitle>
        <CardDescription>{t("addon.settings.projectHint")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {!settings.tokenHint ? (
          <p className="text-xs text-muted-foreground">{t("addon.settings.projectNeedsToken")}</p>
        ) : settings.projectsError !== null ? (
          <p className="break-words text-xs text-destructive">{settings.projectsError}</p>
        ) : (
          <>
            <div className="grid gap-1.5">
              <Label htmlFor="cp-project">{t("addon.settings.projectTitle")}</Label>
              <Select id="cp-project" value={projectId} onChange={(e) => setProjectId(e.target.value)}>
                {projectId === "" && <option value="">{t("addon.settings.selectProject")}</option>}
                {settings.projects.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </Select>
            </div>
            {settings.products > 0 && (
              <label className="flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" checked={apply} onChange={(e) => setApply(e.target.checked)} />
                {t("addon.settings.applyToProducts", { count: settings.products })}
              </label>
            )}
            <div className="flex justify-end">
              <Button loading={busy} disabled={projectId === "" || (projectId === settings.projectId && !apply)} onClick={save}>
                {t("common.save")}
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}
