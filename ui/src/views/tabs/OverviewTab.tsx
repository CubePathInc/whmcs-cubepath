import { useState } from "react";
import { ArrowDownToLine, ArrowUpFromLine, Cpu, Eye, HardDrive, MemoryStick, Network } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { useToast } from "@/components/ui/toast";
import { CopyButton, InfoRow } from "@/components/shared";
import { MetricChart } from "@/components/MetricChart";
import { callApi, useConfig, usePolling, useQuery } from "@/lib/api";
import { formatBytes, formatDay, formatMemory } from "@/lib/utils";
import type { Metrics, Overview } from "@/lib/types";
import { useLocale, useT } from "@/i18n";
import { fmt, toMbps, toPercent } from "./MetricsTab";

export function OverviewTab({ data }: { data: Overview }) {
  const t = useT();
  const locale = useLocale();
  const config = useConfig();
  const toast = useToast();
  const vps = data.vps!;
  const bw = data.bandwidth;
  const [password, setPassword] = useState<string | null>(null);
  const live = useQuery<Metrics>("metrics", { range: "H1", metrics: ["CPU_USAGE", "NETWORK_RECEIVE", "NETWORK_TRANSMIT"] });
  usePolling(live.reload, 30000);
  const s = (name: string) => live.data?.series.find((x) => x.name === name);

  const bwPct = bw && bw.includedBytes > 0 ? (bw.totalBytes / bw.includedBytes) * 100 : 0;

  const specs = [
    { icon: Cpu, label: t("overview.cpu"), value: vps.plan ? `${vps.plan.cpu} vCPU` : "—" },
    { icon: MemoryStick, label: t("overview.memory"), value: vps.plan ? formatMemory(vps.plan.ram) : "—" },
    { icon: HardDrive, label: t("overview.storage"), value: vps.plan ? `${vps.plan.storage} GB NVMe` : "—" },
    { icon: Network, label: t("overview.traffic"), value: vps.plan ? `${vps.plan.bandwidth} TB` : "—" },
  ];

  const revealPassword = async () => {
    try {
      const res = await callApi<{ password: string }>(config, "credentials");
      setPassword(res.password);
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 @lg:grid-cols-4">
        {specs.map(({ icon: Icon, label, value }) => (
          <Card key={label} className="p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <Icon className="h-4 w-4" />
              {label}
            </div>
            <div className="mt-2 text-lg font-semibold tabular-nums">{value}</div>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 @lg:grid-cols-3">
        <Card className="@lg:col-span-2">
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle>{t("overview.cpuLastHour")}</CardTitle>
          </CardHeader>
          <CardContent>
            <MetricChart
              lines={[{ series: s("cpu_usage"), label: t("metrics.cpu"), color: "var(--chart-1)" }]}
              format={fmt.percent}
              transform={toPercent}
              locale={locale}
              rangeSeconds={3600}
              height={160}
              compact
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("overview.bandwidth")}</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {bw ? (
              <>
                <div>
                  <div className="flex items-baseline justify-between text-sm">
                    <span className="font-semibold tabular-nums">{formatBytes(bw.totalBytes)}</span>
                    <span className="text-xs text-muted-foreground">
                      {bw.includedBytes > 0 ? t("overview.ofIncluded", { total: formatBytes(bw.includedBytes, 0) }) : t("overview.thisMonth")}
                    </span>
                  </div>
                  <Progress value={bwPct} className="mt-2" indicatorClassName={bwPct > 90 ? "bg-destructive" : undefined} />
                </div>
                <div className="grid grid-cols-2 gap-3 text-xs">
                  <div className="rounded-lg border p-2.5">
                    <div className="flex items-center gap-1.5 text-muted-foreground">
                      <ArrowDownToLine className="h-3.5 w-3.5" />
                      {t("metrics.in")}
                    </div>
                    <div className="mt-1 text-sm font-medium tabular-nums">{formatBytes(bw.inBytes)}</div>
                  </div>
                  <div className="rounded-lg border p-2.5">
                    <div className="flex items-center gap-1.5 text-muted-foreground">
                      <ArrowUpFromLine className="h-3.5 w-3.5" />
                      {t("metrics.out")}
                    </div>
                    <div className="mt-1 text-sm font-medium tabular-nums">{formatBytes(bw.outBytes)}</div>
                  </div>
                </div>
              </>
            ) : (
              <p className="text-xs text-muted-foreground">{t("overview.bandwidthUnavailable")}</p>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 @lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>{t("overview.access")}</CardTitle>
          </CardHeader>
          <CardContent>
            <InfoRow label={t("overview.ipv4")}>
              {vps.ipv4 ? (
                <span className="inline-flex items-center gap-1.5 font-mono">
                  {vps.ipv4} <CopyButton value={vps.ipv4} />
                </span>
              ) : (
                "—"
              )}
            </InfoRow>
            {vps.ipv6 && (
              <InfoRow label={t("overview.ipv6")}>
                <span className="inline-flex items-center gap-1.5 font-mono text-xs">
                  {vps.ipv6} <CopyButton value={vps.ipv6} />
                </span>
              </InfoRow>
            )}
            <InfoRow label={t("overview.username")}>
              <span className="font-mono">{vps.user || "root"}</span>
            </InfoRow>
            <InfoRow label={t("overview.password")}>
              {password !== null ? (
                <span className="inline-flex items-center gap-1.5 font-mono">
                  {password || "—"} {password && <CopyButton value={password} />}
                </span>
              ) : (
                <Button variant="ghost" size="sm" className="h-6 px-2" onClick={revealPassword}>
                  <Eye />
                  {t("common.show")}
                </Button>
              )}
            </InfoRow>
            {vps.ipv4 && (
              <InfoRow label="SSH">
                <span className="inline-flex items-center gap-1.5 font-mono text-xs">
                  ssh {vps.user || "root"}@{vps.ipv4} <CopyButton value={`ssh ${vps.user || "root"}@${vps.ipv4}`} />
                </span>
              </InfoRow>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("overview.server")}</CardTitle>
          </CardHeader>
          <CardContent>
            <InfoRow label={t("overview.hostname")}>{vps.name}</InfoRow>
            <InfoRow label={t("overview.os")}>{vps.template?.os_name ?? "—"}</InfoRow>
            <InfoRow label={t("overview.location")}>{vps.location?.description ?? "—"}</InfoRow>
            <InfoRow label={t("overview.plan")}>{vps.plan?.plan_name ?? "—"}</InfoRow>
            {vps.private_ip && <InfoRow label={t("overview.privateIp")}>{vps.private_ip}</InfoRow>}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("overview.billing")}</CardTitle>
          </CardHeader>
          <CardContent>
            <InfoRow label={t("overview.product")}>{data.service.product}</InfoRow>
            <InfoRow label={t("overview.billingCycle")}>{data.service.billingcycle}</InfoRow>
            <InfoRow label={t("overview.amount")}>{data.service.amount}</InfoRow>
            <InfoRow label={t("overview.registered")}>{formatDay(data.service.regdate, locale)}</InfoRow>
            <InfoRow label={t("overview.nextDue")}>{formatDay(data.service.nextduedate, locale)}</InfoRow>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("overview.networkLastHour")}</CardTitle>
        </CardHeader>
        <CardContent>
          <MetricChart
            lines={[
              { series: s("network_receive_usage"), label: t("metrics.in"), color: "var(--chart-1)" },
              { series: s("network_transmit_usage"), label: t("metrics.out"), color: "var(--chart-2)" },
            ]}
            format={fmt.mbps}
            transform={toMbps}
            locale={locale}
            rangeSeconds={3600}
            height={160}
            compact
          />
        </CardContent>
      </Card>
    </div>
  );
}
