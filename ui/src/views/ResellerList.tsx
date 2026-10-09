import { useMemo, useState } from "react";
import { Cpu, ExternalLink, HardDrive, MemoryStick, Network, RefreshCw, Search, Server } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, CopyButton, EmptyState, ErrorState, StatusBadge, isTransitional } from "@/components/shared";
import { OsIcon } from "@/components/OsIcon";
import { callApi, useConfig, usePolling, useQuery } from "@/lib/api";
import { formatMemory } from "@/lib/utils";
import type { ResellerServer } from "@/lib/types";
import { useT } from "@/i18n";

type BulkAction = "start" | "stop" | "reboot" | "suspend" | "unsuspend";
const PAGE_SIZE = 25;

export function ResellerList() {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const servers = useQuery<ResellerServer[]>("servers.list");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(0);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [bulk, setBulk] = useState<BulkAction | "">("");
  const [confirm, setConfirm] = useState(false);

  const transitional = servers.data?.some((s) => isTransitional(s.vps?.status)) ?? false;
  usePolling(servers.reload, transitional ? 8000 : 60000);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return (servers.data ?? []).filter((s) => {
      const vpsStatus = s.vps?.status ?? "unlinked";
      if (status !== "all" && vpsStatus !== status) return false;
      if (!q) return true;
      return [s.domain, s.client, s.email, s.product, s.vps?.ipv4, s.vps?.name, String(s.serviceId)]
        .filter(Boolean)
        .some((v) => String(v).toLowerCase().includes(q));
    });
  }, [servers.data, search, status]);

  if (servers.error && !servers.data) return <ErrorState message={servers.error} onRetry={servers.reload} />;
  if (!servers.data)
    return (
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-4 @lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-20 rounded-xl" />
          ))}
        </div>
        <Skeleton className="h-96 rounded-xl" />
      </div>
    );

  const all = servers.data;
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const current = Math.min(page, pages - 1);
  const rows = filtered.slice(current * PAGE_SIZE, current * PAGE_SIZE + PAGE_SIZE);
  const allOnPage = rows.length > 0 && rows.every((r) => selected.has(r.serviceId));

  const stats = [
    { label: t("reseller.total"), value: all.length },
    { label: t("status.active"), value: all.filter((s) => s.vps?.status === "active").length },
    { label: t("status.stopped"), value: all.filter((s) => s.vps?.status === "stopped").length },
    { label: t("reseller.attention"), value: all.filter((s) => !s.vps || ["failed", "suspended"].includes(s.vps.status)).length },
  ];

  const toggle = (id: number) =>
    setSelected((set) => {
      const next = new Set(set);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const serviceUrl = (s: ResellerServer) =>
    (config.serviceUrl ?? "clientsservices.php?userid={userid}&id={id}").replace("{userid}", String(s.clientId)).replace("{id}", String(s.serviceId));

  const runBulk = async () => {
    if (!bulk) return;
    try {
      const res = await callApi<{ ok: number; failed: { serviceId: number; error: string }[] }>(config, "servers.bulk", {
        action: bulk,
        ids: [...selected],
      });
      if (res.failed.length === 0) toast.success(t("reseller.bulkDone", { count: res.ok }));
      else toast.error(t("reseller.bulkPartial", { ok: res.ok, failed: res.failed.length }) + " " + res.failed.map((f) => `#${f.serviceId}: ${f.error}`).join("; "));
      setConfirm(false);
      setSelected(new Set());
      setBulk("");
      window.setTimeout(() => void servers.reload(), 1500);
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 @lg:grid-cols-4">
        {stats.map((s) => (
          <Card key={s.label} className="p-4">
            <div className="text-xs text-muted-foreground">{s.label}</div>
            <div className="mt-1 text-2xl font-semibold tabular-nums">{s.value}</div>
          </Card>
        ))}
      </div>

      <Card>
        <div className="flex flex-col gap-3 border-b p-4 @sm:flex-row @sm:items-center @sm:justify-between">
          <div className="relative w-full @sm:max-w-xs">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              placeholder={t("reseller.search")}
              className="pl-9"
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(0);
              }}
            />
          </div>
          <div className="flex items-center gap-2">
            <Select
              value={status}
              className="w-auto"
              onChange={(e) => {
                setStatus(e.target.value);
                setPage(0);
              }}
            >
              <option value="all">{t("reseller.allStatuses")}</option>
              {["active", "stopped", "deploying", "suspended", "failed"].map((s) => (
                <option key={s} value={s}>
                  {t(`status.${s}` as "status.active")}
                </option>
              ))}
              <option value="unlinked">{t("reseller.unlinked")}</option>
            </Select>
            <Button variant="outline" size="icon" aria-label={t("common.refresh")} onClick={() => void servers.reload()}>
              <RefreshCw className={servers.loading ? "animate-spin" : ""} />
            </Button>
          </div>
        </div>

        {filtered.length === 0 ? (
          <EmptyState icon={Server} title={all.length === 0 ? t("reseller.empty") : t("reseller.noMatches")} description={all.length === 0 ? t("reseller.emptyHint") : undefined} />
        ) : (
          <Table>
            <THead>
              <Tr className="hover:bg-transparent">
                <Th className="w-10">
                  <input
                    type="checkbox"
                    aria-label={t("reseller.selectAll")}
                    checked={allOnPage}
                    onChange={() =>
                      setSelected((set) => {
                        const next = new Set(set);
                        rows.forEach((r) => (allOnPage ? next.delete(r.serviceId) : next.add(r.serviceId)));
                        return next;
                      })
                    }
                  />
                </Th>
                <Th>ID</Th>
                <Th>{t("reseller.server")}</Th>
                <Th>{t("reseller.resources")}</Th>
                <Th>{t("reseller.client")}</Th>
                <Th>{t("common.status")}</Th>
                <Th className="text-right">{t("common.actions")}</Th>
              </Tr>
            </THead>
            <TBody>
              {rows.map((s) => (
                <Tr key={s.serviceId}>
                  <Td>
                    <input type="checkbox" aria-label={`#${s.serviceId}`} checked={selected.has(s.serviceId)} onChange={() => toggle(s.serviceId)} />
                  </Td>
                  <Td className="text-xs text-muted-foreground">#{s.serviceId}</Td>
                  <Td>
                    <div className="flex items-center gap-3">
                      <OsIcon name={s.vps?.template?.template_name ?? ""} className="h-9 w-9 shrink-0" />
                      <div className="min-w-0">
                        <div className="truncate font-medium">{s.vps?.label || s.vps?.name || s.domain || s.product}</div>
                        <div className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                          {s.vps?.ipv4 ?? "—"}
                          {s.vps?.ipv4 && <CopyButton value={s.vps.ipv4} />}
                          {s.vps?.location && <span className="font-sans">· {s.vps.location.location_name}</span>}
                        </div>
                      </div>
                    </div>
                  </Td>
                  <Td>
                    {s.vps?.plan ? (
                      <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                          <MemoryStick className="h-3.5 w-3.5" />
                          {formatMemory(s.vps.plan.ram)}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                          <HardDrive className="h-3.5 w-3.5" />
                          {s.vps.plan.storage} GB
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                          <Cpu className="h-3.5 w-3.5" />
                          {s.vps.plan.cpu} vCPU
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                          <Network className="h-3.5 w-3.5" />
                          {s.vps.plan.bandwidth} TB
                        </span>
                      </div>
                    ) : (
                      <span className="text-xs text-muted-foreground">{s.product}</span>
                    )}
                  </Td>
                  <Td>
                    <div className="max-w-[220px] truncate text-sm">{s.client}</div>
                    <div className="max-w-[220px] truncate text-xs text-muted-foreground">{s.email}</div>
                  </Td>
                  <Td>
                    <div className="flex flex-col items-start gap-1">
                      {s.vps ? <StatusBadge status={s.vps.status} /> : <Badge variant="outline">{t("reseller.unlinked")}</Badge>}
                      {s.serviceStatus !== "Active" && <span className="text-xs text-muted-foreground">WHMCS: {s.serviceStatus}</span>}
                    </div>
                  </Td>
                  <Td className="text-right">
                    <Button variant="ghost" size="sm" onClick={() => (window.top ?? window).location.assign(serviceUrl(s))}>
                      <ExternalLink />
                      {t("reseller.manage")}
                    </Button>
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}

        <div className="flex flex-col gap-3 border-t p-4 @sm:flex-row @sm:items-center @sm:justify-between">
          <div className="flex items-center gap-2">
            <Select value={bulk} className="w-auto" onChange={(e) => setBulk(e.target.value as BulkAction | "")}>
              <option value="">{t("reseller.withSelected", { count: selected.size })}</option>
              <option value="start">{t("power.start")}</option>
              <option value="reboot">{t("power.reboot")}</option>
              <option value="stop">{t("power.stop")}</option>
              <option value="suspend">{t("reseller.suspend")}</option>
              <option value="unsuspend">{t("reseller.unsuspend")}</option>
            </Select>
            <Button disabled={!bulk || selected.size === 0} onClick={() => setConfirm(true)}>
              {t("reseller.apply")}
            </Button>
          </div>
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            {t("reseller.page", { page: current + 1, pages })}
            <Button variant="outline" size="sm" disabled={current === 0} onClick={() => setPage(current - 1)}>
              ‹
            </Button>
            <Button variant="outline" size="sm" disabled={current >= pages - 1} onClick={() => setPage(current + 1)}>
              ›
            </Button>
          </div>
        </div>
      </Card>

      {confirm && bulk && (
        <ConfirmDialog
          open
          destructive={bulk === "stop" || bulk === "suspend"}
          onClose={() => setConfirm(false)}
          onConfirm={runBulk}
          title={t("reseller.bulkTitle")}
          description={t("reseller.bulkDescription", { action: t(bulk === "suspend" || bulk === "unsuspend" ? `reseller.${bulk}` : `power.${bulk}`), count: selected.size })}
        />
      )}
    </div>
  );
}
