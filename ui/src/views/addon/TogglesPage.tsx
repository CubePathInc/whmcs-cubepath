import { useEffect, useMemo, useState } from "react";
import { Boxes, MapPin, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { Switch } from "@/components/ui/switch";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { EmptyState } from "@/components/shared";
import { OsIcon } from "@/components/OsIcon";
import { callApi, useConfig, useQuery } from "@/lib/api";
import type { ToggleData } from "@/lib/types";

type ToggleItem = ToggleData["items"][number];
import { useT } from "@/i18n";
import { PageError, PageHeader } from "./AddonApp";

/** Enable or disable the locations or templates offered on the order form. */
export function TogglesPage({ kind }: { kind: "locations" | "templates" }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const query = useQuery<ToggleData>("addon.toggles", { kind });
  const [disabled, setDisabled] = useState<Set<string>>(new Set());
  const [search, setSearch] = useState("");
  const [saving, setSaving] = useState(false);

  const saved = useMemo(() => new Set(query.data?.disabled ?? []), [query.data]);
  useEffect(() => setDisabled(new Set(saved)), [saved]);

  const items = query.data?.items ?? [];
  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return q ? items.filter((i) => i.label.toLowerCase().includes(q) || i.value.toLowerCase().includes(q)) : items;
  }, [items, search]);

  if (query.error && !query.data) return <PageError message={query.error} onRetry={query.reload} />;
  if (!query.data) return <Skeleton className="h-96 rounded-xl" />;

  // Only values still offered count; stale ones stay saved but are not shown.
  const known = new Set(items.map((i) => i.value));
  const dirty = items.some((i) => disabled.has(i.value) !== saved.has(i.value));
  const enabledCount = items.filter((i) => !disabled.has(i.value)).length;

  const toggle = (value: string, on: boolean) =>
    setDisabled((set) => {
      const next = new Set(set);
      if (on) next.delete(value);
      else next.add(value);
      return next;
    });

  const setAll = (list: ToggleItem[], enable: boolean) =>
    setDisabled((set) => {
      const next = new Set(set);
      list.forEach((i) => (enable ? next.delete(i.value) : next.add(i.value)));
      return next;
    });

  // Templates are split into operating systems and applications.
  const sections: { key: string; title?: string; all: ToggleItem[]; shown: ToggleItem[] }[] =
    kind === "templates"
      ? [
          { key: "os", title: t("addon.toggles.osTitle"), all: items.filter((i) => i.type !== "app"), shown: filtered.filter((i) => i.type !== "app") },
          { key: "app", title: t("addon.toggles.appsTitle"), all: items.filter((i) => i.type === "app"), shown: filtered.filter((i) => i.type === "app") },
        ]
      : [{ key: "locations", all: items, shown: filtered }];

  const save = async () => {
    setSaving(true);
    try {
      const keep = [...saved].filter((v) => !known.has(v));
      await callApi(config, "addon.saveToggles", { kind, disabled: [...keep, ...[...disabled].filter((v) => known.has(v))] });
      toast.success(t("addon.toggles.saved"));
      await query.reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t(`addon.toggles.${kind}Title`)}
        description={t(`addon.toggles.${kind}Hint`)}
        actions={
          <>
            {dirty && (
              <Button variant="ghost" onClick={() => setDisabled(new Set(saved))}>
                {t("common.discard")}
              </Button>
            )}
            <Button loading={saving} disabled={!dirty} onClick={save}>
              {t("common.saveChanges")}
            </Button>
          </>
        }
      />

      <div className="flex flex-col gap-3 @sm:flex-row @sm:items-center @sm:justify-between">
        <div className="relative w-full @sm:max-w-xs">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} placeholder={t("addon.toggles.search")} className="pl-9" onChange={(e) => setSearch(e.target.value)} />
        </div>
        <span className="text-xs text-muted-foreground">
          {t("addon.toggles.enabledCount", { enabled: enabledCount, total: items.length })}
          {dirty && <span className="ml-2 text-foreground">· {t("addon.toggles.unsaved")}</span>}
        </span>
      </div>

      {sections.map((section) => (
        <Card key={section.key}>
          <div className="flex flex-col gap-3 border-b p-4 @sm:flex-row @sm:items-center @sm:justify-between">
            <div className="flex items-center gap-2">
              {section.title && <h3 className="font-semibold">{section.title}</h3>}
              <span className="text-xs text-muted-foreground">
                {t("addon.toggles.enabledCount", {
                  enabled: section.all.filter((i) => !disabled.has(i.value)).length,
                  total: section.all.length,
                })}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={() => setAll(section.shown, true)}>
                {t("addon.toggles.enableAll")}
              </Button>
              <Button variant="outline" size="sm" onClick={() => setAll(section.shown, false)}>
                {t("addon.toggles.disableAll")}
              </Button>
            </div>
          </div>

          {section.shown.length === 0 ? (
            <EmptyState icon={kind === "templates" ? Boxes : MapPin} title={t("addon.toggles.noMatches")} />
          ) : (
            <Table>
              <THead>
                <Tr className="hover:bg-transparent">
                  <Th className="w-16">{t("common.enabled")}</Th>
                  <Th>{t("addon.toggles.name")}</Th>
                  <Th>{t("addon.toggles.value")}</Th>
                </Tr>
              </THead>
              <TBody>
                {section.shown.map((item) => {
                  const enabled = !disabled.has(item.value);
                  return (
                    <Tr key={item.value} className={enabled ? "" : "opacity-60"}>
                      <Td>
                        <Switch checked={enabled} aria-label={item.label} onCheckedChange={(on) => toggle(item.value, on)} />
                      </Td>
                      <Td>
                        <div className="flex items-center gap-3">
                          {kind === "templates" ? (
                            <OsIcon name={item.value} className="h-7 w-7 shrink-0" />
                          ) : (
                            <MapPin className="h-4 w-4 shrink-0 text-muted-foreground" />
                          )}
                          <span className="font-medium">{item.label}</span>
                        </div>
                      </Td>
                      <Td className="font-mono text-xs text-muted-foreground">{item.value}</Td>
                    </Tr>
                  );
                })}
              </TBody>
            </Table>
          )}
        </Card>
      ))}
    </div>
  );
}
