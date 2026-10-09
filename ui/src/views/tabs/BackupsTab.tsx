import { useEffect, useState } from "react";
import { ArchiveRestore, HardDriveDownload, Plus, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label, Select } from "@/components/ui/input";
import { Progress, Skeleton } from "@/components/ui/progress";
import { Switch } from "@/components/ui/switch";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, EmptyState, ErrorState } from "@/components/shared";
import { callApi, useConfig, usePolling, useQuery } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import type { Backup, BackupSettings, Backups } from "@/lib/types";
import { useLocale, useT } from "@/i18n";

const RUNNING = ["pending", "running", "in_progress", "restoring", "queued"];

export function BackupsTab() {
  const t = useT();
  const locale = useLocale();
  const config = useConfig();
  const toast = useToast();
  const backups = useQuery<Backups>("backups");
  const [settings, setSettings] = useState<BackupSettings | null>(null);
  const [savingSettings, setSavingSettings] = useState(false);
  const [creating, setCreating] = useState(false);
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [restore, setRestore] = useState<Backup | null>(null);
  const [remove, setRemove] = useState<Backup | null>(null);

  const running = backups.data?.backups.some((b) => RUNNING.includes(b.status)) ?? false;
  usePolling(backups.reload, running ? 5000 : 60000);

  useEffect(() => {
    if (backups.data) setSettings(backups.data.settings);
  }, [backups.data]);

  if (backups.error && !backups.data) return <ErrorState message={backups.error} onRetry={backups.reload} />;
  if (!backups.data || !settings) return <Skeleton className="h-64 rounded-xl" />;

  const settingsDirty = JSON.stringify(settings) !== JSON.stringify(backups.data.settings);

  const run = async (action: string, data: Record<string, unknown>, success: string) => {
    try {
      await callApi(config, action, data);
      toast.success(success);
      await backups.reload();
      return true;
    } catch (e) {
      toast.error((e as Error).message);
      return false;
    }
  };

  const statusBadge = (b: Backup) => {
    if (b.status === "completed") return <Badge variant="success">{t("backups.status.completed")}</Badge>;
    if (b.status === "failed") return <Badge variant="destructive">{t("backups.status.failed")}</Badge>;
    if (RUNNING.includes(b.status))
      return (
        <div className="flex min-w-[90px] flex-col gap-1">
          <Badge variant="warning">{t("backups.status.running")}</Badge>
          {b.progress > 0 && b.progress < 100 && <Progress value={b.progress} className="h-1" />}
        </div>
      );
    return <Badge variant="outline">{b.status}</Badge>;
  };

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader className="flex-row items-start justify-between gap-4">
          <div className="flex flex-col gap-1">
            <CardTitle>{t("backups.scheduleTitle")}</CardTitle>
            <CardDescription>{t("backups.scheduleHint")}</CardDescription>
          </div>
          <label className="flex shrink-0 items-center gap-2 text-sm font-medium">
            <Switch
              checked={settings.enabled}
              disabled={backups.data.locked && !settings.enabled}
              onCheckedChange={(enabled) => setSettings({ ...settings, enabled })}
              aria-label={t("backups.automatic")}
            />
            {settings.enabled ? t("common.enabled") : t("common.disabled")}
          </label>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {backups.data.locked && !settings.enabled && (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted px-4 py-3 text-sm">
              <span>{t("backups.notIncluded")}</span>
              {backups.data.upgradeUrl && (
                <Button size="sm" variant="outline" onClick={() => window.open(backups.data!.upgradeUrl!, "_top")}>
                  {t("backups.order")}
                </Button>
              )}
            </div>
          )}
          <div className="grid gap-4 @sm:grid-cols-3">
            <div className="grid gap-1.5">
              <Label>{t("backups.hour")}</Label>
              <Select
                value={settings.schedule_hour}
                disabled={!settings.enabled}
                onChange={(e) => setSettings({ ...settings, schedule_hour: Number(e.target.value) })}
              >
                {Array.from({ length: 24 }, (_, h) => (
                  <option key={h} value={h}>
                    {String(h).padStart(2, "0")}:00 UTC
                  </option>
                ))}
              </Select>
            </div>
            <div className="grid gap-1.5">
              <Label>{t("backups.retention")}</Label>
              <Select
                value={settings.retention_days}
                disabled={!settings.enabled}
                onChange={(e) => setSettings({ ...settings, retention_days: Number(e.target.value) })}
              >
                {Array.from({ length: 7 }, (_, i) => i + 1).map((d) => (
                  <option key={d} value={d}>
                    {t("backups.days", { count: d })}
                  </option>
                ))}
              </Select>
            </div>
            <div className="grid gap-1.5">
              <Label>{t("backups.maxBackups")}</Label>
              <Select
                value={settings.max_backups}
                disabled={!settings.enabled}
                onChange={(e) => setSettings({ ...settings, max_backups: Number(e.target.value) })}
              >
                {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </Select>
            </div>
          </div>
          <div className="flex justify-end">
            <Button
              disabled={!settingsDirty}
              loading={savingSettings}
              onClick={async () => {
                setSavingSettings(true);
                await run("backups.settings", { ...settings }, t("backups.settingsSaved"));
                setSavingSettings(false);
              }}
            >
              {t("common.saveChanges")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex-row items-center justify-between gap-4">
          <div className="flex flex-col gap-1">
            <CardTitle>{t("backups.listTitle")}</CardTitle>
            <CardDescription>
              {backups.data.settings.enabled ? t("backups.listHint", { count: backups.data.total }) : t("backups.enableFirst")}
            </CardDescription>
          </div>
          <Button size="sm" onClick={() => setCreating(true)} disabled={running || !backups.data.settings.enabled} title={backups.data.settings.enabled ? undefined : t("backups.enableFirst")}>
            <Plus />
            {t("backups.create")}
          </Button>
        </CardHeader>
        <CardContent>
          {backups.data.backups.length === 0 ? (
            <EmptyState icon={HardDriveDownload} title={t("backups.empty")} description={t(backups.data.settings.enabled ? "backups.emptyHint" : "backups.enableFirst")} />
          ) : (
            <Table>
              <THead>
                <Tr className="hover:bg-transparent">
                  <Th>{t("backups.date")}</Th>
                  <Th>{t("backups.type")}</Th>
                  <Th>{t("backups.size")}</Th>
                  <Th>{t("common.status")}</Th>
                  <Th>{t("backups.notes")}</Th>
                  <Th className="text-right">{t("common.actions")}</Th>
                </Tr>
              </THead>
              <TBody>
                {backups.data.backups.map((b) => (
                  <Tr key={b.id}>
                    <Td className="whitespace-nowrap">{formatDate(b.completed_at || b.created_at, locale)}</Td>
                    <Td>
                      <Badge variant="outline">{b.backup_type === "automatic" ? t("backups.automatic") : t("backups.manual")}</Badge>
                    </Td>
                    <Td className="whitespace-nowrap tabular-nums">{b.size_gb != null ? `${b.size_gb.toFixed(2)} GB` : "—"}</Td>
                    <Td title={b.error_message ?? undefined}>{statusBadge(b)}</Td>
                    <Td className="max-w-[220px] truncate text-xs text-muted-foreground">{b.notes || "—"}</Td>
                    <Td className="whitespace-nowrap text-right">
                      <Button variant="ghost" size="sm" disabled={b.status !== "completed" || running} onClick={() => setRestore(b)}>
                        <ArchiveRestore />
                        {t("backups.restore")}
                      </Button>
                      <Button variant="ghost" size="icon-sm" aria-label={t("common.delete")} disabled={RUNNING.includes(b.status)} onClick={() => setRemove(b)}>
                        <Trash2 className="text-destructive" />
                      </Button>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog
        open={creating}
        onClose={() => !busy && setCreating(false)}
        title={t("backups.create")}
        description={t("backups.createHint")}
        footer={
          <>
            <Button variant="outline" onClick={() => setCreating(false)} disabled={busy}>
              {t("common.cancel")}
            </Button>
            <Button
              loading={busy}
              onClick={async () => {
                setBusy(true);
                if (await run("backups.create", { notes: notes.trim() }, t("backups.created"))) {
                  setCreating(false);
                  setNotes("");
                }
                setBusy(false);
              }}
            >
              {t("backups.create")}
            </Button>
          </>
        }
      >
        <div className="grid gap-1.5">
          <Label htmlFor="cp-backup-notes">{t("backups.notes")}</Label>
          <Input id="cp-backup-notes" value={notes} maxLength={200} placeholder={t("backups.notesPlaceholder")} onChange={(e) => setNotes(e.target.value)} />
        </div>
      </Dialog>

      {restore && (
        <ConfirmDialog
          open
          destructive
          onClose={() => setRestore(null)}
          title={t("backups.restoreTitle")}
          description={t("backups.restoreDescription", { date: formatDate(restore.completed_at || restore.created_at, locale) })}
          confirmLabel={t("backups.restore")}
          onConfirm={async () => {
            if (await run("backups.restore", { id: restore.id }, t("backups.restoring"))) setRestore(null);
          }}
        />
      )}
      {remove && (
        <ConfirmDialog
          open
          destructive
          onClose={() => setRemove(null)}
          title={t("backups.deleteTitle")}
          description={t("backups.deleteDescription")}
          confirmLabel={t("common.delete")}
          onConfirm={async () => {
            if (await run("backups.delete", { id: remove.id }, t("backups.deleted"))) setRemove(null);
          }}
        />
      )}
    </div>
  );
}
