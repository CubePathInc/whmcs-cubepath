import { useState } from "react";
import { ExternalLink, Package, PackagePlus, RefreshCw, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, EmptyState, ErrorState } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import type { AddonProduct } from "@/lib/types";
import { useT } from "@/i18n";
import { PageHeader, useNavigate } from "./AddonApp";

export function ProductsPage() {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const navigate = useNavigate();
  const query = useQuery<AddonProduct[]>("addon.products");
  const [syncing, setSyncing] = useState(false);
  const [deleting, setDeleting] = useState<AddonProduct | null>(null);

  const remove = async () => {
    if (!deleting) return;
    try {
      await callApi(config, "addon.deleteProduct", { id: deleting.id });
      toast.success(t("addon.products.deleted", { name: deleting.name }));
      setDeleting(null);
      await query.reload();
    } catch (e) {
      toast.error((e as Error).message);
    }
  };

  const sync = async () => {
    setSyncing(true);
    try {
      await callApi(config, "addon.syncProducts");
      toast.success(t("addon.products.synced"));
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setSyncing(false);
    }
  };

  if (query.error && !query.data) return <ErrorState message={query.error} onRetry={query.reload} />;
  if (!query.data) return <Skeleton className="h-96 rounded-xl" />;

  const products = query.data;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title={t("addon.products.title")}
        description={`${t("addon.products.hint")} ${t("addon.products.syncHint")}`}
        actions={
          <>
            <Button variant="outline" size="icon" aria-label={t("common.refresh")} onClick={() => void query.reload()}>
              <RefreshCw className={query.loading ? "animate-spin" : ""} />
            </Button>
            <Button variant="outline" loading={syncing} disabled={products.length === 0} onClick={sync}>
              {!syncing && <RefreshCw />}
              {t("addon.products.sync")}
            </Button>
            <Button onClick={() => navigate("creator")}>
              <PackagePlus />
              {t("addon.page.creator")}
            </Button>
          </>
        }
      />

      <Card>
        {products.length === 0 ? (
          <EmptyState icon={Package} title={t("addon.products.empty")} description={t("addon.products.emptyHint")} />
        ) : (
          <Table>
            <THead>
              <Tr className="hover:bg-transparent">
                <Th>ID</Th>
                <Th>{t("addon.products.name")}</Th>
                <Th>{t("addon.products.group")}</Th>
                <Th>{t("addon.products.plan")}</Th>
                <Th>{t("addon.products.paytype")}</Th>
                <Th className="text-right">{t("addon.products.services")}</Th>
                <Th className="text-right">{t("common.actions")}</Th>
              </Tr>
            </THead>
            <TBody>
              {products.map((p) => (
                <Tr key={p.id}>
                  <Td className="text-xs text-muted-foreground">#{p.id}</Td>
                  <Td>
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">{p.name}</span>
                      {p.hidden && <Badge variant="secondary">{t("addon.products.hidden")}</Badge>}
                      {p.retired && <Badge variant="warning">{t("addon.products.retired")}</Badge>}
                    </div>
                  </Td>
                  <Td className="text-muted-foreground">{p.group || "—"}</Td>
                  <Td className="font-mono text-xs">{p.plan}</Td>
                  <Td>
                    <Badge variant="outline">{t(`addon.creator.paytype.${p.paytype}`)}</Badge>
                  </Td>
                  <Td className="text-right tabular-nums">{p.services}</Td>
                  <Td className="text-right">
                    <div className="flex justify-end gap-1">
                      <Button variant="ghost" size="sm" onClick={() => window.location.assign(`configproducts.php?action=edit&id=${p.id}`)}>
                        <ExternalLink />
                        {t("addon.products.edit")}
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive hover:text-destructive"
                        disabled={p.services > 0}
                        aria-label={t("common.delete")}
                        title={p.services > 0 ? t("addon.products.deleteHasServices") : t("common.delete")}
                        onClick={() => setDeleting(p)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}
      </Card>

      {deleting && (
        <ConfirmDialog
          open
          destructive
          onClose={() => setDeleting(null)}
          onConfirm={remove}
          title={t("addon.products.deleteTitle")}
          description={t("addon.products.deleteDescription", { name: deleting.name })}
          confirmLabel={t("common.delete")}
        />
      )}
    </div>
  );
}
