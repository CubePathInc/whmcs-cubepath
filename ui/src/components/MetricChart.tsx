import { useId, useMemo } from "react";
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import type { Series } from "@/lib/types";

export interface ChartLine {
  series: Series | undefined;
  label: string;
  color: string;
}

interface MetricChartProps {
  lines: ChartLine[];
  format: (value: number) => string;
  transform?: (value: number) => number;
  locale: string;
  height?: number;
  rangeSeconds: number;
  compact?: boolean;
}

export function MetricChart({ lines, format, transform = (v) => v, locale, height = 220, rangeSeconds, compact }: MetricChartProps) {
  const gid = useId().replace(/:/g, "");
  const data = useMemo(() => {
    const rows = new Map<number, Record<string, number>>();
    lines.forEach((line, i) => {
      for (const [ts, value] of line.series?.points ?? []) {
        const row = rows.get(ts) ?? { ts };
        row[`v${i}`] = transform(value);
        rows.set(ts, row);
      }
    });
    return [...rows.values()].sort((a, b) => a.ts - b.ts);
  }, [lines, transform]);

  const tickFormat = (ts: number) => {
    const d = new Date(ts * 1000);
    return rangeSeconds > 86400
      ? d.toLocaleDateString(locale, { day: "2-digit", month: "short" })
      : d.toLocaleTimeString(locale, { hour: "2-digit", minute: "2-digit" });
  };

  return (
    <div style={{ height }} className="w-full">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <defs>
            {lines.map((line, i) => (
              <linearGradient key={i} id={`${gid}-${i}`} x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor={line.color} stopOpacity={0.25} />
                <stop offset="95%" stopColor={line.color} stopOpacity={0.02} />
              </linearGradient>
            ))}
          </defs>
          <CartesianGrid vertical={false} stroke="var(--border)" />
          <XAxis dataKey="ts" type="number" domain={["dataMin", "dataMax"]} tickFormatter={tickFormat} tickLine={false} axisLine={false} minTickGap={40} hide={compact} />
          <YAxis tickFormatter={format} tickLine={false} axisLine={false} width={compact ? 64 : 80} />
          <Tooltip
            cursor={{ stroke: "var(--border)" }}
            content={({ active, payload, label }) =>
              active && payload?.length ? (
                <div className="rounded-lg border bg-card px-3 py-2 text-xs shadow-md">
                  <div className="mb-1 text-muted-foreground">{new Date(Number(label) * 1000).toLocaleString(locale)}</div>
                  {payload.map((p) => {
                    const i = Number(String(p.dataKey).slice(1));
                    return (
                      <div key={String(p.dataKey)} className="flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full" style={{ background: lines[i]?.color }} />
                        <span className="text-muted-foreground">{lines[i]?.label}</span>
                        <span className="ml-auto font-medium tabular-nums">{format(Number(p.value))}</span>
                      </div>
                    );
                  })}
                </div>
              ) : null
            }
          />
          {lines.map((line, i) => (
            <Area
              key={i}
              type="monotone"
              dataKey={`v${i}`}
              stroke={line.color}
              strokeWidth={1.5}
              fill={`url(#${gid}-${i})`}
              isAnimationActive={false}
              dot={false}
            />
          ))}
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
