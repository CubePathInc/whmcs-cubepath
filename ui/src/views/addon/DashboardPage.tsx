import { Boxes, ChevronRight, MapPin, Package, Server, Settings, type LucideIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { ErrorState, InfoRow } from "@/components/shared";
import { useQuery } from "@/lib/api";
import type { AddonDashboard } from "@/lib/types";
import { useT } from "@/i18n";
import { useNavigate, type AddonPage } from "./AddonApp";

export function DashboardPage() {
  const t = useT();
  const navigate = useNavigate();
  const query = useQuery<AddonDashboard>("addon.dashboard");
  const d = query.data;

  if (query.error && !d) return <ErrorState message={query.error} onRetry={query.reload} />;
  if (!d)
    return (
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-4 @lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-20 rounded-xl" />
          ))}
        </div>
        <Skeleton className="h-56 rounded-xl" />
      </div>
    );

  const stats: { label: string; value: number; icon: LucideIcon; page: AddonPage }[] = [
    { label: t("addon.dashboard.services"), value: d.services, icon: Server, page: "vps" },
    { label: t("addon.dashboard.products"), value: d.products, icon: Package, page: "products" },
    { label: t("addon.dashboard.locations"), value: d.locations, icon: MapPin, page: "locations" },
    { label: t("addon.dashboard.templates"), value: d.templates, icon: Boxes, page: "templates" },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 @lg:grid-cols-4">
        {stats.map((s) => (
          <button key={s.label} type="button" onClick={() => navigate(s.page)} className="cursor-pointer text-left">
            <Card className="p-4 transition-colors hover:bg-muted/50">
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                {s.label}
                <s.icon className="h-4 w-4" />
              </div>
              <div className="mt-1 text-2xl font-semibold tabular-nums">{s.value}</div>
            </Card>
          </button>
        ))}
      </div>

      <div className="grid gap-4 @lg:grid-cols-2">
        <Card>
          <CardHeader className="flex-row items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
              <CardTitle>{t("addon.dashboard.connection")}</CardTitle>
              <CardDescription>{t("addon.dashboard.connectionHint")}</CardDescription>
            </div>
            <Button variant="outline" size="sm" onClick={() => navigate("settings")}>
              <Settings />
              {t("addon.page.settings")}
            </Button>
          </CardHeader>
          <CardContent>
            <InfoRow label={t("addon.dashboard.apiStatus")}>
              {d.apiError ? (
                <Badge variant="destructive" title={d.apiError}>
                  <span className="h-1.5 w-1.5 rounded-full bg-destructive" />
                  {t("addon.dashboard.disconnected")}
                </Badge>
              ) : (
                <Badge variant="success">
                  <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-success" />
                  {t("addon.dashboard.connected")}
                </Badge>
              )}
            </InfoRow>
            <InfoRow label={t("addon.dashboard.server")}>
              {d.server ? (
                <a href={`configservers.php?action=manage&id=${d.server.id}`} className="hover:underline">
                  {d.server.name}
                </a>
              ) : d.legacyToken ? (
                t("addon.dashboard.legacyToken")
              ) : (
                <span className="text-destructive">{t("addon.dashboard.notConfigured")}</span>
              )}
            </InfoRow>
            <InfoRow label={t("addon.dashboard.project")}>
              {d.projectId ? d.projectName ?? d.projectId : <span className="text-destructive">{t("addon.dashboard.notConfigured")}</span>}
            </InfoRow>
            {d.apiError && <p className="mt-3 break-words text-xs text-destructive">{d.apiError}</p>}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("addon.dashboard.catalog")}</CardTitle>
            <CardDescription>{t("addon.dashboard.catalogHint")}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col">
            {(
              [
                ["creator", t("addon.dashboard.plans"), d.plans],
                ["locations", t("addon.dashboard.locations"), d.locations],
                ["templates", t("addon.dashboard.templates"), d.templates],
                ["products", t("addon.dashboard.products"), d.products],
              ] as [AddonPage, string, number][]
            ).map(([page, label, value]) => (
              <button
                key={page}
                type="button"
                onClick={() => navigate(page)}
                className="flex cursor-pointer items-center justify-between gap-4 border-b py-2.5 text-sm last:border-0 hover:text-foreground"
              >
                <span className="text-muted-foreground">{label}</span>
                <span className="inline-flex items-center gap-1 font-medium tabular-nums">
                  {value}
                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                </span>
              </button>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
