import { useEffect, useState } from "react";
import { AlertTriangle, ArrowLeft, Cpu, HardDrive, MemoryStick, Network, Settings, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label, Select } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import { cn, formatMemory } from "@/lib/utils";
import type { CatalogPlan, CreatorData } from "@/lib/types";
import { useT } from "@/i18n";
import { PageError, useNavigate } from "./AddonApp";

type PayType = "recurring" | "onetime" | "free";

/** auto: each plan goes in the group of its family; manual: in the group chosen. */
type Grouping = "auto" | "manual";

const usd = (value: number) => `$${value.toFixed(2)}`;

export function CreatorPage() {
  const t = useT();
  const navigate = useNavigate();
  const query = useQuery<CreatorData>("addon.creator");
  const d = query.data;

  if (query.error && !d) return <PageError message={query.error} onRetry={query.reload} />;
  if (!d)
    return (
      <div className="grid gap-4 @lg:grid-cols-3">
        <Skeleton className="h-96 rounded-xl @lg:col-span-2" />
        <Skeleton className="h-64 rounded-xl" />
      </div>
    );

  return (
    <div className="flex flex-col gap-4">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2" onClick={() => navigate("products")}>
          <ArrowLeft />
          {t("addon.creator.back")}
        </Button>
      </div>
      {d.projectId === "" && (
        <div className="flex flex-col gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm @sm:flex-row @sm:items-center @sm:justify-between">
          <span className="inline-flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            {t("addon.creator.projectMissing")}
          </span>
          <Button variant="outline" size="sm" onClick={() => navigate("settings")}>
            <Settings />
            {t("addon.goToSettings")}
          </Button>
        </div>
      )}
      <CreatorForms data={d} reload={query.reload} />
    </div>
  );
}

function CreatorForms({ data, reload }: { data: CreatorData; reload: () => Promise<void> }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();

  const orderable = data.plans.filter((p) => p.available);
  const missing = orderable.filter((p) => !p.exists);
  const defaultCurrency = data.currencies.find((c) => c.default) ?? data.currencies[0];

  const [plan, setPlan] = useState(() => (missing[0] ?? orderable[0])?.name ?? "");
  const [name, setName] = useState("");
  const [grouping, setGrouping] = useState<Grouping>("auto");
  const [gid, setGid] = useState(String(data.groups[0]?.id ?? "new"));
  const [newGroup, setNewGroup] = useState("");
  const [payType, setPayType] = useState<PayType>("recurring");
  const [prices, setPrices] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState(false);

  const [markup, setMarkup] = useState("0");
  const [confirmAll, setConfirmAll] = useState(false);
  const [deleteGroup, setDeleteGroup] = useState<{ id: number; name: string } | null>(null);

  const selected = data.plans.find((p) => p.name === plan);

  // The USD price starts at the CubePath cost of the chosen plan.
  useEffect(() => {
    if (!selected) return;
    setPrices(Object.fromEntries(data.currencies.map((c) => [c.id, c.code === "USD" ? selected.monthly.toFixed(2) : "0.00"])));
  }, [selected, data.currencies]);

  // Keep the group selects valid after a group is created or deleted.
  useEffect(() => {
    const ids = data.groups.map((g) => String(g.id));
    const fallback = ids[0] ?? "new";
    setGid((g) => (g === "new" || ids.includes(g) ? g : fallback));
  }, [data.groups]);

  const create = async () => {
    setBusy(true);
    try {
      const res = await callApi<{ productId: number }>(config, "addon.createProduct", {
        plan,
        name: name.trim(),
        grouping,
        gid,
        newGroup: newGroup.trim(),
        paytype: payType,
        prices,
      });
      toast.success(t("addon.creator.created", { id: res.productId }));
      setName("");
      setNewGroup("");
      await reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const createAll = async () => {
    try {
      const res = await callApi<{ created: number }>(config, "addon.createAllProducts", { markup });
      toast.success(t("addon.creator.allCreated", { count: res.created }));
      setConfirmAll(false);
      await reload();
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  const removeGroup = async () => {
    if (!deleteGroup) return;
    try {
      await callApi(config, "addon.deleteGroup", { gid: String(deleteGroup.id) });
      toast.success(t("addon.creator.groupDeleted"));
      setDeleteGroup(null);
      await reload();
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  const groupFields = (
    mode: Grouping,
    onMode: (m: Grouping) => void,
    families: string[],
    value: string,
    onChange: (v: string) => void,
    newName: string,
    onNewName: (v: string) => void,
    id: string,
  ) => {
    const group = data.groups.find((g) => String(g.id) === value);
    return (
      <div className="grid content-start gap-1.5">
        <Label htmlFor={id}>{t("addon.creator.group")}</Label>
        <div className="inline-flex w-fit rounded-md border bg-muted p-0.5">
          {(["auto", "manual"] as Grouping[]).map((m) => (
            <button
              key={m}
              type="button"
              onClick={() => onMode(m)}
              className={cn(
                "cursor-pointer rounded px-3 py-1 text-xs font-medium transition-colors",
                mode === m ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
              )}
            >
              {t(`addon.creator.grouping.${m}`)}
            </button>
          ))}
        </div>
        {mode === "auto" ? (
          <p className="text-xs text-muted-foreground">
            {families.length === 1
              ? t("addon.creator.grouping.autoOne", { group: families[0] })
              : t("addon.creator.grouping.autoMany", { groups: families.join(", ") || "—" })}
          </p>
        ) : (
          <>
            <div className="flex gap-2">
              <Select id={id} value={value} onChange={(e) => onChange(e.target.value)}>
                {data.groups.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name}
                  </option>
                ))}
                <option value="new">{t("addon.creator.newGroup")}</option>
              </Select>
              {group && (
                <Button variant="outline" size="icon" aria-label={t("addon.creator.deleteGroup")} title={t("addon.creator.deleteGroup")} onClick={() => setDeleteGroup(group)}>
                  <Trash2 />
                </Button>
              )}
            </div>
            {value === "new" && <Input value={newName} placeholder={t("addon.creator.newGroupPlaceholder")} onChange={(e) => onNewName(e.target.value)} />}
          </>
        )}
      </div>
    );
  };

  const groupReady = (mode: Grouping, value: string, newName: string) => mode === "auto" || value !== "new" || newName.trim() !== "";
  // The group a family's products go to, by its current name if it was created before.
  const familyOf = (p: CatalogPlan) => data.familyGroups[p.family || "VPS"] ?? (p.family || "VPS");
  const missingFamilies = Array.from(new Set(missing.map(familyOf)));

  return (
    <>
      <div className="grid gap-4 @lg:grid-cols-3">
        <Card className="@lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("addon.creator.single")}</CardTitle>
            <CardDescription>{t("addon.creator.singleHint")}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-1.5">
              <Label htmlFor="cp-plan">{t("addon.creator.plan")}</Label>
              <Select id="cp-plan" value={plan} onChange={(e) => setPlan(e.target.value)}>
                {data.plans.map((p) => (
                  <option key={p.name} value={p.name} disabled={!p.available}>
                    {p.name} · {usd(p.monthly)}
                    {!p.available ? ` (${t("addon.creator.outOfStock")})` : p.exists ? ` (${t("addon.creator.exists")})` : ""}
                  </option>
                ))}
              </Select>
              {selected && <PlanSpecs plan={selected} />}
            </div>

            <div className="grid gap-4 @md:grid-cols-2">
              <div className="grid content-start gap-1.5">
                <Label htmlFor="cp-name">{t("addon.creator.name")}</Label>
                <Input id="cp-name" value={name} placeholder={t("addon.creator.namePlaceholder")} onChange={(e) => setName(e.target.value)} />
              </div>
              {groupFields(grouping, setGrouping, selected ? [familyOf(selected)] : [], gid, setGid, newGroup, setNewGroup, "cp-gid")}
            </div>

            <div className="grid gap-1.5">
              <Label>{t("addon.creator.paytype")}</Label>
              <div className="inline-flex w-fit rounded-md border bg-muted p-0.5">
                {(["recurring", "onetime", "free"] as PayType[]).map((p) => (
                  <button
                    key={p}
                    type="button"
                    onClick={() => setPayType(p)}
                    className={cn(
                      "cursor-pointer rounded px-3 py-1 text-xs font-medium transition-colors",
                      payType === p ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {t(`addon.creator.paytype.${p}`)}
                  </button>
                ))}
              </div>
            </div>

            {payType !== "free" && (
              <div className="grid gap-1.5">
                <Label>{t("addon.creator.monthlyPrice")}</Label>
                <div className="grid gap-2 @sm:grid-cols-3">
                  {data.currencies.map((c) => (
                    <div key={c.id} className="relative">
                      <Input
                        inputMode="decimal"
                        value={prices[c.id] ?? ""}
                        className="pr-14 tabular-nums"
                        onChange={(e) => setPrices((p) => ({ ...p, [c.id]: e.target.value }))}
                      />
                      <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">{c.code}</span>
                    </div>
                  ))}
                </div>
                <p className="text-xs text-muted-foreground">{t("addon.creator.priceHint")}</p>
              </div>
            )}

            <div className="flex justify-end">
              <Button loading={busy} disabled={!selected?.available || !groupReady(grouping, gid, newGroup)} onClick={create}>
                {t("addon.creator.create")}
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card className="self-start">
          <CardHeader>
            <CardTitle>{t("addon.creator.all")}</CardTitle>
            <CardDescription>{t("addon.creator.allHint")}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <div className="grid gap-1.5">
              <Label>{t("addon.creator.group")}</Label>
              <p className="text-xs text-muted-foreground">{t("addon.creator.grouping.autoMany", { groups: missingFamilies.join(", ") || "—" })}</p>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="cp-markup">{t("addon.creator.markup")}</Label>
              <div className="relative">
                <Input id="cp-markup" inputMode="decimal" value={markup} className="pr-8 tabular-nums" onChange={(e) => setMarkup(e.target.value)} />
                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">%</span>
              </div>
              {defaultCurrency && defaultCurrency.code !== "USD" && <p className="text-xs text-muted-foreground">{defaultCurrency.code}: 0.00</p>}
            </div>
            <Button variant="outline" disabled={missing.length === 0} onClick={() => setConfirmAll(true)}>
              {t("addon.creator.allButton")} ({missing.length})
            </Button>
          </CardContent>
        </Card>
      </div>

      <PlansTable plans={data.plans} selected={plan} onUse={setPlan} />

      {confirmAll && (
        <ConfirmDialog
          open
          onClose={() => setConfirmAll(false)}
          onConfirm={createAll}
          title={t("addon.creator.allTitle")}
          description={t("addon.creator.allDescription", { count: missing.length })}
        />
      )}
      {deleteGroup && (
        <ConfirmDialog
          open
          destructive
          onClose={() => setDeleteGroup(null)}
          onConfirm={removeGroup}
          title={t("addon.creator.deleteGroupTitle")}
          description={t("addon.creator.deleteGroupDescription", { name: deleteGroup.name })}
          confirmLabel={t("common.delete")}
        />
      )}
    </>
  );
}

function PlanSpecs({ plan }: { plan: CatalogPlan }) {
  return (
    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
      <span className="inline-flex items-center gap-1.5">
        <Cpu className="h-3.5 w-3.5" />
        {plan.cpu} vCPU
      </span>
      <span className="inline-flex items-center gap-1.5">
        <MemoryStick className="h-3.5 w-3.5" />
        {formatMemory(plan.ramMb)}
      </span>
      <span className="inline-flex items-center gap-1.5">
        <HardDrive className="h-3.5 w-3.5" />
        {plan.storageGb} GB
      </span>
      <span className="inline-flex items-center gap-1.5">
        <Network className="h-3.5 w-3.5" />
        {plan.transferTb} TB
      </span>
    </div>
  );
}

function PlansTable({ plans, selected, onUse }: { plans: CatalogPlan[]; selected: string; onUse: (name: string) => void }) {
  const t = useT();
  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("addon.creator.plansTitle")}</CardTitle>
      </CardHeader>
      <Table>
        <THead>
          <Tr className="hover:bg-transparent">
            <Th>{t("addon.creator.plan")}</Th>
            <Th>{t("reseller.resources")}</Th>
            <Th>{t("addon.page.locations")}</Th>
            <Th className="text-right">{t("addon.creator.cost")}</Th>
            <Th>{t("common.status")}</Th>
            <Th className="text-right">{t("common.actions")}</Th>
          </Tr>
        </THead>
        <TBody>
          {plans.map((p) => (
            <Tr key={p.name} className={cn(p.name === selected && "bg-muted/50")}>
              <Td className="font-mono text-xs font-medium">{p.name}</Td>
              <Td>
                <PlanSpecs plan={p} />
              </Td>
              <Td className="text-xs text-muted-foreground">{p.locations.join(", ") || "—"}</Td>
              <Td className="text-right tabular-nums">{usd(p.monthly)}</Td>
              <Td>
                {!p.available ? (
                  <Badge variant="destructive">{t("addon.creator.outOfStock")}</Badge>
                ) : p.exists ? (
                  <Badge variant="secondary">{t("addon.creator.exists")}</Badge>
                ) : (
                  <Badge variant="success">{t("addon.creator.available")}</Badge>
                )}
              </Td>
              <Td className="text-right">
                <Button variant="ghost" size="sm" disabled={!p.available || p.name === selected} onClick={() => onUse(p.name)}>
                  {t("addon.creator.use")}
                </Button>
              </Td>
            </Tr>
          ))}
        </TBody>
      </Table>
    </Card>
  );
}
