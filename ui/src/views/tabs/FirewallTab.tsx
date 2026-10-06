import { useEffect, useState } from "react";
import { AlertTriangle, Plus, Shield, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { Switch } from "@/components/ui/switch";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { EmptyState, ErrorState } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import type { Firewall, FirewallRule } from "@/lib/types";
import { useT } from "@/i18n";

const PRESETS: { label: string; rule: FirewallRule }[] = [
  { label: "SSH", rule: { direction: "in", protocol: "tcp", port: "22", source: null, comment: "SSH" } },
  { label: "HTTP", rule: { direction: "in", protocol: "tcp", port: "80", source: null, comment: "HTTP" } },
  { label: "HTTPS", rule: { direction: "in", protocol: "tcp", port: "443", source: null, comment: "HTTPS" } },
  { label: "RDP", rule: { direction: "in", protocol: "tcp", port: "3389", source: null, comment: "RDP" } },
  { label: "Ping", rule: { direction: "in", protocol: "icmp", port: null, source: null, comment: "Ping" } },
];

const PORT = /^(\d{1,5}(-\d{1,5})?)(,\s*\d{1,5}(-\d{1,5})?)*$/;
const SOURCE = /^[0-9a-fA-F:.]+(\/\d{1,3})?$/;

function ruleError(rule: FirewallRule): string | null {
  if ((rule.protocol === "tcp" || rule.protocol === "udp") && rule.port && !PORT.test(rule.port.trim())) return "port";
  if (rule.source && !SOURCE.test(rule.source.trim())) return "source";
  return null;
}

/** Whether inbound rules leave SSH (22) or RDP (3389) reachable. */
function keepsRemoteAccess(rules: FirewallRule[]): boolean {
  return rules.some((r) => {
    if (r.direction !== "in" || r.protocol !== "tcp") return false;
    if (!r.port || r.port.trim() === "") return true;
    return r.port.split(",").some((item) => {
      const [a, b] = item.trim().split("-").map(Number);
      const hi = b ?? a;
      return (a <= 22 && 22 <= hi) || (a <= 3389 && 3389 <= hi);
    });
  });
}

export function FirewallTab() {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const firewall = useQuery<Firewall>("firewall");
  const [enabled, setEnabled] = useState(false);
  const [rules, setRules] = useState<FirewallRule[]>([]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (firewall.data) {
      setEnabled(firewall.data.enabled);
      setRules(firewall.data.rules);
    }
  }, [firewall.data]);

  if (firewall.error && !firewall.data) return <ErrorState message={firewall.error} onRetry={firewall.reload} />;
  if (!firewall.data) return <Skeleton className="h-64 rounded-xl" />;

  const max = firewall.data.maxRules;
  const dirty = enabled !== firewall.data.enabled || JSON.stringify(rules) !== JSON.stringify(firewall.data.rules);
  const errors = rules.map(ruleError);
  const hasErrors = errors.some(Boolean);
  const lockout = enabled && !keepsRemoteAccess(rules);

  const update = (i: number, patch: Partial<FirewallRule>) => setRules((list) => list.map((r, j) => (j === i ? { ...r, ...patch } : r)));
  const add = (rule: FirewallRule) => rules.length < max && setRules((list) => [...list, { ...rule }]);

  const save = async () => {
    setSaving(true);
    try {
      const clean = rules.map((r) => ({
        ...r,
        port: r.protocol === "tcp" || r.protocol === "udp" ? r.port?.trim() || null : null,
        source: r.source?.trim() || null,
        comment: r.comment?.trim() || null,
      }));
      await callApi(config, "firewall.save", { enabled, rules: clean });
      toast.success(t("firewall.saved"));
      await firewall.reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader className="flex-row items-start justify-between gap-4">
          <div className="flex flex-col gap-1">
            <CardTitle>{t("firewall.title")}</CardTitle>
            <CardDescription>{t("firewall.description")}</CardDescription>
          </div>
          <label className="flex shrink-0 items-center gap-2 text-sm font-medium">
            <Switch checked={enabled} onCheckedChange={setEnabled} aria-label={t("firewall.enable")} />
            {enabled ? t("common.enabled") : t("common.disabled")}
          </label>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {lockout && (
            <div className="flex items-start gap-2 rounded-lg border border-warning/50 bg-warning/10 p-3 text-xs">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
              <span>{t("firewall.lockoutWarning")}</span>
            </div>
          )}

          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs text-muted-foreground">{t("firewall.quickAdd")}</span>
            {PRESETS.map((p) => (
              <Button key={p.label} variant="outline" size="sm" disabled={rules.length >= max} onClick={() => add(p.rule)}>
                <Plus />
                {p.label}
              </Button>
            ))}
          </div>

          {rules.length === 0 ? (
            <EmptyState icon={Shield} title={t("firewall.noRules")} description={t("firewall.noRulesHint")} />
          ) : (
            <Table>
              <THead>
                <Tr className="hover:bg-transparent">
                  <Th>{t("firewall.direction")}</Th>
                  <Th>{t("firewall.protocol")}</Th>
                  <Th>{t("firewall.port")}</Th>
                  <Th>{t("firewall.source")}</Th>
                  <Th>{t("firewall.comment")}</Th>
                  <Th />
                </Tr>
              </THead>
              <TBody>
                {rules.map((rule, i) => (
                  <Tr key={i} className="hover:bg-transparent">
                    <Td className="py-2">
                      <Select className="w-[120px]" value={rule.direction} onChange={(e) => update(i, { direction: e.target.value as FirewallRule["direction"] })}>
                        <option value="in">{t("firewall.in")}</option>
                        <option value="out">{t("firewall.out")}</option>
                      </Select>
                    </Td>
                    <Td className="py-2">
                      <Select className="w-[96px]" value={rule.protocol} onChange={(e) => update(i, { protocol: e.target.value as FirewallRule["protocol"] })}>
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="icmp">ICMP</option>
                        <option value="gre">GRE</option>
                      </Select>
                    </Td>
                    <Td className="py-2">
                      <Input
                        value={rule.protocol === "tcp" || rule.protocol === "udp" ? rule.port ?? "" : ""}
                        disabled={rule.protocol !== "tcp" && rule.protocol !== "udp"}
                        placeholder={t("firewall.allPorts")}
                        style={{ minWidth: 110 }}
                        onChange={(e) => update(i, { port: e.target.value })}
                        className={errors[i] === "port" ? "border-destructive" : undefined}
                      />
                    </Td>
                    <Td className="min-w-[150px] py-2">
                      <Input
                        value={rule.source ?? ""}
                        placeholder={t("firewall.anySource")}
                        onChange={(e) => update(i, { source: e.target.value })}
                        className={errors[i] === "source" ? "border-destructive" : undefined}
                      />
                    </Td>
                    <Td className="min-w-[150px] py-2">
                      <Input value={rule.comment ?? ""} maxLength={100} onChange={(e) => update(i, { comment: e.target.value })} />
                    </Td>
                    <Td className="py-2 text-right">
                      <Button variant="ghost" size="icon-sm" aria-label={t("common.delete")} onClick={() => setRules((list) => list.filter((_, j) => j !== i))}>
                        <Trash2 className="text-destructive" />
                      </Button>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )}

          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={rules.length >= max}
                onClick={() => add({ direction: "in", protocol: "tcp", port: "", source: null, comment: null })}
              >
                <Plus />
                {t("firewall.addRule")}
              </Button>
              <span className="text-xs text-muted-foreground">{t("firewall.ruleCount", { count: rules.length, max })}</span>
            </div>
            <div className="flex gap-2">
              {dirty && (
                <Button variant="ghost" onClick={() => firewall.data && (setEnabled(firewall.data.enabled), setRules(firewall.data.rules))}>
                  {t("common.discard")}
                </Button>
              )}
              <Button onClick={save} loading={saving} disabled={!dirty || hasErrors}>
                {t("common.saveChanges")}
              </Button>
            </div>
          </div>
          {hasErrors && <p className="text-xs text-destructive">{t("firewall.invalidRules")}</p>}
        </CardContent>
      </Card>

      {firewall.data.external.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{t("firewall.externalTitle")}</CardTitle>
            <CardDescription>{t("firewall.externalHint")}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            {firewall.data.external.map((g) => (
              <Badge key={g.id} variant="outline">
                {g.name}
              </Badge>
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
