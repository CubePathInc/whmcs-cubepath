import { useState } from "react";
import {
  Activity,
  BarChart3,
  Disc3,
  HardDriveDownload,
  LayoutDashboard,
  Loader2,
  MapPin,
  Network,
  Power,
  PowerOff,
  RefreshCw,
  RotateCw,
  Settings,
  Shield,
  Wrench,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { Tabs, type TabItem } from "@/components/ui/tabs";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, CopyButton, ErrorState, StatusBadge, isTransitional } from "@/components/shared";
import { OsIcon } from "@/components/OsIcon";
import { callApi, useConfig, usePolling, useQuery } from "@/lib/api";
import type { Overview } from "@/lib/types";
import { useT } from "@/i18n";
import { OverviewTab } from "./tabs/OverviewTab";
import { MetricsTab } from "./tabs/MetricsTab";
import { NetworkTab } from "./tabs/NetworkTab";
import { FirewallTab } from "./tabs/FirewallTab";
import { BackupsTab } from "./tabs/BackupsTab";
import { IsoTab } from "./tabs/IsoTab";
import { ReinstallTab } from "./tabs/ReinstallTab";
import { SettingsTab } from "./tabs/SettingsTab";
import { ActivityTab } from "./tabs/ActivityTab";

type TabKey = "overview" | "metrics" | "network" | "firewall" | "backups" | "iso" | "reinstall" | "settings" | "activity";
type PowerAction = "start" | "stop" | "reboot";

const STORAGE_KEY = "cubepath.panel.tab";

function initialTab(): TabKey {
  try {
    const saved = sessionStorage.getItem(STORAGE_KEY);
    if (saved) return saved as TabKey;
  } catch {
    /* storage unavailable */
  }
  return "overview";
}

export function ServicePanel() {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const [tab, setTab] = useState<TabKey>(initialTab);
  const [power, setPower] = useState<PowerAction | null>(null);
  const overview = useQuery<Overview>("overview");
  const vps = overview.data?.vps ?? null;
  const transitional = isTransitional(vps?.status);
  // Poll fast while the VPS is changing state, slowly otherwise.
  usePolling(overview.reload, transitional ? 5000 : 30000);

  const changeTab = (key: TabKey) => {
    setTab(key);
    try {
      sessionStorage.setItem(STORAGE_KEY, key);
    } catch {
      /* storage unavailable */
    }
  };

  if (overview.loading && !overview.data) return <PanelSkeleton />;
  if (overview.error && !overview.data) return <ErrorState message={overview.error} onRetry={overview.reload} />;
  if (!overview.data) return null;

  const tabs: TabItem<TabKey>[] = [
    { key: "overview", label: t("tab.overview"), icon: LayoutDashboard },
    { key: "metrics", label: t("tab.metrics"), icon: BarChart3 },
    { key: "network", label: t("tab.network"), icon: Network },
    { key: "firewall", label: t("tab.firewall"), icon: Shield },
    { key: "backups", label: t("tab.backups"), icon: HardDriveDownload },
    { key: "iso", label: t("tab.iso"), icon: Disc3 },
    { key: "reinstall", label: t("tab.reinstall"), icon: Wrench },
    { key: "settings", label: t("tab.settings"), icon: Settings },
    { key: "activity", label: t("tab.activity"), icon: Activity },
  ];
  const current = tabs.some((x) => x.key === tab) ? tab : "overview";

  const runPower = async (action: PowerAction) => {
    try {
      await callApi(config, "power", { action });
      toast.success(t(`power.${action}.done`));
      setPower(null);
      window.setTimeout(() => void overview.reload(), 1500);
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  const canPower = vps && !transitional && vps.status !== "suspended" && vps.status !== "failed";

  return (
    <div className="flex flex-col gap-4">
      <Card className="p-4 @sm:p-5">
        <div className="flex flex-col gap-4 @lg:flex-row @lg:items-center @lg:justify-between">
          <div className="flex min-w-0 items-center gap-3">
            <OsIcon name={vps?.template?.template_name ?? ""} className="h-11 w-11 shrink-0" />
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="truncate text-lg font-semibold @sm:text-xl">
                  {vps?.label || vps?.name || overview.data.service.domain || overview.data.service.product}
                </h2>
                {vps && <StatusBadge status={vps.status} />}
                {transitional && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
              </div>
              <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {vps?.ipv4 && (
                  <span className="inline-flex items-center gap-1.5 font-mono">
                    {vps.ipv4}
                    <CopyButton value={vps.ipv4} />
                  </span>
                )}
                {vps?.location && (
                  <span className="inline-flex items-center gap-1">
                    <MapPin className="h-3.5 w-3.5" />
                    {vps.location.description}
                  </span>
                )}
                {vps?.template && <span>{vps.template.os_name}</span>}
                <span>{overview.data.service.product}</span>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" disabled={!canPower || vps?.status === "active"} onClick={() => setPower("start")}>
              <Power />
              {t("power.start")}
            </Button>
            <Button variant="outline" size="sm" disabled={!canPower || vps?.status !== "active"} onClick={() => setPower("reboot")}>
              <RotateCw />
              {t("power.reboot")}
            </Button>
            <Button variant="outline" size="sm" disabled={!canPower || vps?.status !== "active"} onClick={() => setPower("stop")}>
              <PowerOff />
              {t("power.stop")}
            </Button>
            <Button variant="ghost" size="icon-sm" aria-label={t("common.refresh")} title={t("common.refresh")} onClick={() => void overview.reload()}>
              <RefreshCw className={overview.loading ? "animate-spin" : ""} />
            </Button>
          </div>
        </div>
      </Card>

      {!vps ? (
        <Card className="p-6 text-center text-sm text-muted-foreground">{overview.data.notice ?? t("panel.notProvisioned")}</Card>
      ) : (
        <>
          <Tabs items={tabs} value={current} onChange={changeTab} />
          <div className="min-h-[300px]">
            {current === "overview" && <OverviewTab data={overview.data} />}
            {current === "metrics" && <MetricsTab />}
            {current === "network" && <NetworkTab />}
            {current === "firewall" && <FirewallTab />}
            {current === "backups" && <BackupsTab />}
            {current === "iso" && <IsoTab vps={vps} />}
            {current === "reinstall" && <ReinstallTab vps={vps} onDone={overview.reload} />}
            {current === "settings" && <SettingsTab vps={vps} onDone={overview.reload} />}
            {current === "activity" && <ActivityTab />}
          </div>
        </>
      )}

      {power && (
        <ConfirmDialog
          open
          onClose={() => setPower(null)}
          onConfirm={() => runPower(power)}
          title={t(`power.${power}.title`)}
          description={t(`power.${power}.description`)}
          confirmLabel={t(`power.${power}`)}
          destructive={power === "stop"}
        />
      )}
    </div>
  );
}

function PanelSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <Skeleton className="h-24 w-full rounded-xl" />
      <Skeleton className="h-10 w-full" />
      <div className="grid gap-4 @md:grid-cols-3">
        <Skeleton className="h-32 rounded-xl" />
        <Skeleton className="h-32 rounded-xl" />
        <Skeleton className="h-32 rounded-xl" />
      </div>
    </div>
  );
}
