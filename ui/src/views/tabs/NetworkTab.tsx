import { useState } from "react";
import { Globe, Pencil } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog } from "@/components/ui/dialog";
import { Input, Label } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/progress";
import { Table, TBody, THead, Td, Th, Tr } from "@/components/ui/table";
import { useToast } from "@/components/ui/toast";
import { CopyButton, EmptyState, ErrorState, InfoRow } from "@/components/shared";
import { callApi, useConfig, useQuery } from "@/lib/api";
import type { IpAddress, Network } from "@/lib/types";
import { useT } from "@/i18n";

const HOSTNAME = /^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}\.?$/;

export function NetworkTab() {
  const t = useT();
  const config = useConfig();
  const toast = useToast();
  const network = useQuery<Network>("network");
  const [editing, setEditing] = useState<IpAddress | null>(null);
  const [value, setValue] = useState("");
  const [saving, setSaving] = useState(false);

  if (network.error && !network.data) return <ErrorState message={network.error} onRetry={network.reload} />;
  if (!network.data) return <Skeleton className="h-48 rounded-xl" />;

  const invalid = value.trim() !== "" && !HOSTNAME.test(value.trim());

  const save = async () => {
    if (!editing) return;
    setSaving(true);
    try {
      await callApi(config, "rdns", { ip: editing.address, value: value.trim() });
      toast.success(t("network.rdnsSaved"));
      setEditing(null);
      await network.reload();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("network.publicIps")}</CardTitle>
          <CardDescription>{t("network.publicIpsHint")}</CardDescription>
        </CardHeader>
        <CardContent>
          {network.data.ips.length === 0 ? (
            <EmptyState icon={Globe} title={t("network.noIps")} />
          ) : (
            <Table>
              <THead>
                <Tr className="hover:bg-transparent">
                  <Th>{t("network.address")}</Th>
                  <Th>{t("network.type")}</Th>
                  <Th>{t("network.rdns")}</Th>
                  <Th className="text-right">{t("common.actions")}</Th>
                </Tr>
              </THead>
              <TBody>
                {network.data.ips.map((ip) => (
                  <Tr key={ip.address}>
                    <Td>
                      <span className="inline-flex items-center gap-1.5 font-mono text-xs">
                        {ip.address}
                        {ip.type === "IPv4" && ip.netmask && <span className="text-muted-foreground">/{ip.netmask}</span>}
                        <CopyButton value={ip.address} />
                      </span>
                    </Td>
                    <Td>
                      <div className="flex gap-1.5">
                        <Badge variant="outline">{ip.type}</Badge>
                        {ip.is_primary && <Badge variant="secondary">{t("network.primary")}</Badge>}
                      </div>
                    </Td>
                    <Td className="font-mono text-xs">{ip.reverse_dns || <span className="text-muted-foreground">—</span>}</Td>
                    <Td className="text-right">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setEditing(ip);
                          setValue(ip.reverse_dns ?? "");
                        }}
                      >
                        <Pencil />
                        {t("network.editRdns")}
                      </Button>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )}
        </CardContent>
      </Card>

      {network.data.private && (
        <Card>
          <CardHeader>
            <CardTitle>{t("network.private")}</CardTitle>
          </CardHeader>
          <CardContent>
            <InfoRow label={t("network.privateName")}>{network.data.private.name}</InfoRow>
            <InfoRow label={t("network.privateRange")}>
              {network.data.private.ip_range}/{network.data.private.prefix}
            </InfoRow>
            <InfoRow label={t("network.privateIp")}>{network.data.private.assigned_ip}</InfoRow>
          </CardContent>
        </Card>
      )}

      <Dialog
        open={!!editing}
        onClose={() => !saving && setEditing(null)}
        title={t("network.editRdns")}
        description={editing ? t("network.rdnsFor", { ip: editing.address }) : undefined}
        footer={
          <>
            <Button variant="outline" onClick={() => setEditing(null)} disabled={saving}>
              {t("common.cancel")}
            </Button>
            <Button onClick={save} loading={saving} disabled={invalid}>
              {t("common.save")}
            </Button>
          </>
        }
      >
        <div className="grid gap-2">
          <Label htmlFor="cp-rdns">{t("network.rdns")}</Label>
          <Input id="cp-rdns" value={value} placeholder="server.example.com" onChange={(e) => setValue(e.target.value)} />
          <p className={invalid ? "text-xs text-destructive" : "text-xs text-muted-foreground"}>
            {invalid ? t("network.rdnsInvalid") : t("network.rdnsHint")}
          </p>
        </div>
      </Dialog>
    </div>
  );
}
