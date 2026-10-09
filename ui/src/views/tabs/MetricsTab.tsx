import { useState } from "react";
import { BarChart3 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { Switch } from "@/components/ui/switch";
import { EmptyState, ErrorState } from "@/components/shared";
import { MetricChart } from "@/components/MetricChart";
import { usePolling, useQuery } from "@/lib/api";
import { formatBytes } from "@/lib/utils";
import type { Metrics } from "@/lib/types";
import { useLocale, useT } from "@/i18n";

export const RANGES = [
  { key: "H1", label: "1h", seconds: 3600 },
  { key: "H6", label: "6h", seconds: 21600 },
  { key: "H24", label: "24h", seconds: 86400 },
  { key: "D7", label: "7d", seconds: 604800 },
  { key: "D30", label: "30d", seconds: 2592000 },
] as const;

export const fmt = {
  percent: (v: number) => `${v.toFixed(v < 10 ? 1 : 0)}%`,
  bytes: (v: number) => formatBytes(v),
  rate: (v: number) => `${formatBytes(v)}/s`,
  mbps: (v: number) => `${v < 10 ? v.toFixed(2) : v.toFixed(0)} Mbps`,
};
export const toPercent = (v: number) => v * 100;
export const toMbps = (v: number) => (v * 8) / 1e6;

export function MetricsTab() {
  const t = useT();
  const locale = useLocale();
  const [range, setRange] = useState<(typeof RANGES)[number]>(RANGES[0]);
  const [live, setLive] = useState(false);
  const metrics = useQuery<Metrics>("metrics", { range: range.key });
  usePolling(live ? metrics.reload : async () => {}, live ? 15000 : 3600000);

  const s = (name: string) => metrics.data?.series.find((x) => x.name === name);
  const empty = metrics.data && metrics.data.series.every((x) => x.points.length === 0);

  const charts = [
    { title: t("metrics.cpu"), lines: [{ series: s("cpu_usage"), label: t("metrics.cpu"), color: "var(--chart-1)" }], format: fmt.percent, transform: toPercent },
    { title: t("metrics.memory"), lines: [{ series: s("memory_usage"), label: t("metrics.memoryUsed"), color: "var(--chart-1)" }], format: fmt.bytes },
    {
      title: t("metrics.network"),
      lines: [
        { series: s("network_receive_usage"), label: t("metrics.in"), color: "var(--chart-1)" },
        { series: s("network_transmit_usage"), label: t("metrics.out"), color: "var(--chart-2)" },
      ],
      format: fmt.mbps,
      transform: toMbps,
    },
    {
      title: t("metrics.disk"),
      lines: [
        { series: s("disk_read_usage"), label: t("metrics.read"), color: "var(--chart-1)" },
        { series: s("disk_write_usage"), label: t("metrics.write"), color: "var(--chart-2)" },
      ],
      format: fmt.rate,
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex rounded-md border bg-card p-0.5">
          {RANGES.map((r) => (
            <Button key={r.key} size="sm" variant={r.key === range.key ? "secondary" : "ghost"} className="h-7" onClick={() => setRange(r)}>
              {r.label}
            </Button>
          ))}
        </div>
        <label className="flex items-center gap-2 text-xs text-muted-foreground">
          <Switch checked={live} onCheckedChange={setLive} aria-label={t("metrics.live")} />
          {t("metrics.live")}
        </label>
      </div>

      {metrics.error && !metrics.data ? (
        <ErrorState message={metrics.error} onRetry={metrics.reload} />
      ) : !metrics.data ? (
        <div className="grid gap-4 @lg:grid-cols-2">
          {charts.map((c) => (
            <Skeleton key={c.title} className="h-72 rounded-xl" />
          ))}
        </div>
      ) : metrics.data.unavailable || empty ? (
        <Card>
          <EmptyState icon={BarChart3} title={t("metrics.emptyTitle")} description={t(metrics.data.unavailable ? "metrics.unavailable" : "metrics.empty")} />
        </Card>
      ) : (
        <div className="grid gap-4 @lg:grid-cols-2">
          {charts.map((c) => (
            <Card key={c.title}>
              <CardHeader className="flex-row items-center justify-between">
                <CardTitle>{c.title}</CardTitle>
                <div className="flex gap-3 text-xs text-muted-foreground">
                  {c.lines.length > 1 &&
                    c.lines.map((l) => (
                      <span key={l.label} className="inline-flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full" style={{ background: l.color }} />
                        {l.label}
                      </span>
                    ))}
                </div>
              </CardHeader>
              <CardContent>
                <MetricChart lines={c.lines} format={c.format} transform={c.transform} locale={locale} rangeSeconds={range.seconds} />
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
