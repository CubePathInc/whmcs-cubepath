import { useState } from "react";
import { Disc3, Eject, Info } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { useToast } from "@/components/ui/toast";
import { ConfirmDialog, EmptyState, ErrorState } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import { formatBytes } from "@/lib/utils";
import type { Iso, Isos, Vps } from "@/lib/types";
import { useT } from "@/i18n";

export function IsoTab({ vps }: { vps: Vps }) {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const isos = useQuery<Isos>("isos");
  const [mount, setMount] = useState<Iso | null>(null);
  const [unmounting, setUnmounting] = useState(false);

  if (isos.error && !isos.data) return <ErrorState message={isos.error} onRetry={isos.reload} />;
  if (!isos.data) return <Skeleton className="h-64 rounded-xl" />;

  const mounted = isos.data.items.find((i) => i.id === isos.data!.mounted || i.is_mounted) ?? null;

  const unmount = async () => {
    setUnmounting(true);
    try {
      await callApi(config, "iso.unmount");
      toast.success(t("iso.unmounted"));
      await isos.reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setUnmounting(false);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("iso.mountedTitle")}</CardTitle>
        </CardHeader>
        <CardContent>
          {mounted ? (
            <div className="flex flex-col gap-3 rounded-lg border p-3 @sm:flex-row @sm:items-center @sm:justify-between">
              <div className="flex items-center gap-3">
                <Disc3 className="h-8 w-8 text-muted-foreground" />
                <div>
                  <div className="text-sm font-medium">{mounted.name}</div>
                  <div className="text-xs text-muted-foreground">{formatBytes(mounted.file_size)}</div>
                </div>
              </div>
              <Button variant="outline" size="sm" loading={unmounting} onClick={unmount}>
                <Eject />
                {t("iso.unmount")}
              </Button>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">{t("iso.none")}</p>
          )}
          <div className="mt-3 flex items-start gap-2 text-xs text-muted-foreground">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            {t("iso.rebootHint")}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("iso.libraryTitle")}</CardTitle>
          <CardDescription>{t("iso.libraryHint")}</CardDescription>
        </CardHeader>
        <CardContent>
          {isos.data.items.length === 0 ? (
            <EmptyState icon={Disc3} title={t("iso.empty")} />
          ) : (
            <div className="grid gap-3 @sm:grid-cols-2 @xl:grid-cols-3">
              {isos.data.items.map((iso) => (
                <div key={iso.id} className="flex flex-col justify-between gap-3 rounded-lg border p-3">
                  <div>
                    <div className="flex items-start justify-between gap-2">
                      <span className="text-sm font-medium">{iso.name}</span>
                      {mounted?.id === iso.id && <Badge variant="success">{t("iso.mounted")}</Badge>}
                    </div>
                    {iso.description && <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">{iso.description}</p>}
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-muted-foreground">{formatBytes(iso.file_size)}</span>
                    <Button variant="outline" size="sm" disabled={mounted?.id === iso.id || vps.status !== "active" && vps.status !== "stopped"} onClick={() => setMount(iso)}>
                      {t("iso.mount")}
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {mount && (
        <ConfirmDialog
          open
          onClose={() => setMount(null)}
          title={t("iso.mountTitle")}
          description={t("iso.mountDescription", { name: mount.name })}
          confirmLabel={t("iso.mount")}
          onConfirm={async () => {
            try {
              await callApi(config, "iso.mount", { id: mount.id });
              toast.success(t("iso.mountedDone"));
              setMount(null);
              await isos.reload();
            } catch (e) {
              toast.error((e as Error).message);
            }
          }}
        />
      )}
    </div>
  );
}
