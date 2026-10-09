import { History } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/progress";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { EmptyState, ErrorState } from "@/components/shared";
import { useQuery } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import type { ActivityEntry } from "@/lib/types";
import { useLocale, useT, type MessageKey } from "@/i18n";

export function ActivityTab() {
  const t = useT();
  const locale = useLocale();
  const activity = useQuery<ActivityEntry[]>("activity");

  if (activity.error && !activity.data) return <ErrorState message={activity.error} onRetry={activity.reload} />;
  if (!activity.data) return <Skeleton className="h-64 rounded-xl" />;

  const label = (action: string) => {
    const key = `activity.action.${action}` as MessageKey;
    const text = t(key);
    return text === key ? action : text;
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("activity.title")}</CardTitle>
        <CardDescription>{t("activity.description")}</CardDescription>
      </CardHeader>
      <CardContent>
        {activity.data.length === 0 ? (
          <EmptyState icon={History} title={t("activity.empty")} />
        ) : (
          <Table>
            <THead>
              <Tr className="hover:bg-transparent">
                <Th>{t("activity.date")}</Th>
                <Th>{t("activity.action")}</Th>
                <Th>{t("activity.by")}</Th>
                <Th>{t("common.status")}</Th>
                <Th>{t("activity.details")}</Th>
              </Tr>
            </THead>
            <TBody>
              {activity.data.map((e) => (
                <Tr key={e.id}>
                  <Td className="whitespace-nowrap">{formatDate(e.created_at, locale)}</Td>
                  <Td className="font-medium">{label(e.action)}</Td>
                  <Td>
                    <Badge variant="outline">{t(`activity.actor.${e.actor}`)}</Badge>
                  </Td>
                  <Td>{e.success ? <Badge variant="success">{t("activity.ok")}</Badge> : <Badge variant="destructive">{t("activity.failed")}</Badge>}</Td>
                  <Td className="max-w-[320px] truncate text-xs text-muted-foreground" title={e.details ?? undefined}>
                    {e.details || "—"}
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}
