import { useEffect, useMemo, useState, type ReactNode } from "react";
import { Check, ChevronDown, Eye, EyeOff, Globe, HardDriveDownload, KeyRound, Network, RefreshCw, Server } from "lucide-react";
import { Input, Label, Select } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import type { StoreConfig, StoreValue } from "@/lib/types";
import { useT } from "@/i18n";
import { billingCycle, fieldValue, setField } from "./form";

const OPTION = (id: number) => `configoption[${id}]`;
const CUSTOM = (id: number) => `customfield[${id}]`;

/** Icons in assets/icons, by OS family or app template name (cubepath-dashboard lib/osIcons.tsx). */
const ICONS: Record<string, string> = {
  ubuntu: "ubuntu-linux.svg",
  debian: "debian-linux.svg",
  windows: "microsoft-windows.svg",
  windowsserver: "microsoft-windows.svg",
  centos: "centos.svg",
  alma: "alma-linux.svg",
  almalinux: "alma-linux.svg",
  rocky: "rocky-linux.svg",
  rockylinux: "rocky-linux.svg",
  fedora: "fedora.svg",
  opensuse: "opensuse.svg",
  suse: "opensuse.svg",
  k8s: "kubernetes.svg",
  kubernetes: "kubernetes.svg",
  minikube: "kubernetes.svg",
  wordpress: "wordpress.svg",
  bookstack: "bookstack.svg",
  coolify: "coolify.svg",
  gitlab: "gitlab.svg",
  wireguard: "wireguard.svg",
  cpanel: "cpanel.svg",
  nextcloud: "nextcloud.svg",
  proxmox: "proxmox.svg",
  pbs: "proxmox.svg",
  pdm: "proxmox.svg",
  pmg: "proxmox.svg",
  n8n: "n8n.svg",
  hestiacp: "hestia.svg",
  nocodb: "nocodb.svg",
  portainer: "portainer.svg",
  easypanel: "easypanel.svg",
  ollama: "ollama.svg",
  supabase: "supabase.svg",
  mautic: "mautic.svg",
  immich: "immich.svg",
  minecraft: "minecraft.svg",
  uptimekuma: "uptime-kuma.svg",
  jellyfin: "jellyfin.svg",
  adguard: "adguard-home.svg",
  minio: "minio.svg",
  grafana: "grafana.svg",
  dokploy: "dokploy.svg",
  netronome: "netronome.png",
  pterodactyl: "pterodactyl.svg",
  openclaw: "openclaw.svg",
  pulse: "pulse.svg",
  hermes: "hermes.svg",
  sentry: "sentry.svg",
  deplo: "deplo.svg",
};

const COUNTRIES: [RegExp, string][] = [
  [/spain|españa|madrid|barcelona/i, "es"],
  [/netherlands|amsterdam/i, "nl"],
  [/germany|frankfurt|falkenstein|nuremberg/i, "de"],
  [/france|paris/i, "fr"],
  [/united kingdom|london|\buk\b/i, "gb"],
  [/texas|florida|virginia|california|new york|illinois|oregon|ashburn|usa|united states/i, "us"],
];

const SSH_KEY = /^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256) AAAA[0-9A-Za-z+/]+={0,3}([ \t][^\x00-\x1f]*)?$/;

function iconFor(assets: string, key: string | undefined) {
  const k = (key || "").toLowerCase();
  const file = ICONS[k] ?? ICONS[k.split(/[-\s_]/)[0]];
  return file ? `${assets}icons/${file}` : null;
}

function Icon({ src, className }: { src: string | null; className?: string }) {
  const [broken, setBroken] = useState(false);
  if (!src || broken) return <Server className={cn("text-muted-foreground", className)} />;
  return <img src={src} alt="" className={cn("object-contain", className)} onError={() => setBroken(true)} />;
}

/** "Ubuntu 24.04 LTS" => ["Ubuntu", "24.04 LTS"] */
function splitVersion(label: string): [string, string] {
  const m = label.match(/^(.*?)\s+(\d[\w.\s-]*)$/);
  return m ? [m[1], m[2]] : [label, ""];
}

const PASSWORD_CHARS = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789#%+-_";

/** 20 random characters with upper and lower case letters, a digit and a symbol. */
function generatePassword(): string {
  const bytes = new Uint32Array(20);
  for (;;) {
    crypto.getRandomValues(bytes);
    const password = Array.from(bytes, (b) => PASSWORD_CHARS[b % PASSWORD_CHARS.length]).join("");
    if (/[A-Z]/.test(password) && /[a-z]/.test(password) && /\d/.test(password) && /[#%+\-_]/.test(password)) return password;
  }
}

export function StoreConfigurator({ config }: { config: StoreConfig }) {
  const t = useT();
  const { location, template, network, backups } = config.options;
  const backupsSub = backups?.values[0];

  const initial = (option: { id: number; values: StoreValue[] } | undefined) => {
    if (!option) return 0;
    const current = Number(fieldValue(OPTION(option.id)));
    return option.values.some((v) => v.id === current) ? current : option.values[0]?.id ?? 0;
  };

  const [locationId, setLocationId] = useState(() => initial(location));
  const [templateId, setTemplateId] = useState(() => initial(template));
  const [networkId, setNetworkId] = useState(() => initial(network));
  const [backupsOn, setBackupsOn] = useState(() => backups !== undefined && fieldValue(OPTION(backups.id)) !== "");
  const [hostname, setHostname] = useState(() => fieldValue("hostname"));
  const [password, setPassword] = useState(() => fieldValue("rootpw"));
  const [showPassword, setShowPassword] = useState(false);
  const [sshKey, setSshKey] = useState(() => (config.fields.sshKey ? fieldValue(CUSTOM(config.fields.sshKey)) : ""));
  const [cloudInit, setCloudInit] = useState(() => (config.fields.cloudInit ? fieldValue(CUSTOM(config.fields.cloudInit)) : ""));
  const [advanced, setAdvanced] = useState(() => cloudInit.trim() !== "");
  const [cycle, setCycle] = useState(billingCycle);

  const selectedTemplate = template.values.find((v) => v.id === templateId);
  const [tab, setTab] = useState<"os" | "app">(selectedTemplate?.kind === "app" ? "app" : "os");
  const windows = (selectedTemplate?.value ?? "").toLowerCase().startsWith("windows");

  // Keep the native fields in step with the cards.
  useEffect(() => setField(OPTION(location.id), String(locationId)), [location.id, locationId]);
  useEffect(() => setField(OPTION(template.id), String(templateId)), [template.id, templateId]);
  useEffect(() => {
    if (network) setField(OPTION(network.id), String(networkId));
  }, [network, networkId]);
  useEffect(() => {
    if (backups) setField(OPTION(backups.id), backupsOn);
  }, [backups, backupsOn]);
  useEffect(() => setField("hostname", hostname), [hostname]);
  useEffect(() => setField("rootpw", password), [password]);
  useEffect(() => {
    if (config.fields.sshKey) setField(CUSTOM(config.fields.sshKey), windows ? "" : sshKey);
  }, [config.fields.sshKey, sshKey, windows]);
  useEffect(() => {
    if (config.fields.cloudInit) setField(CUSTOM(config.fields.cloudInit), windows ? "" : cloudInit);
  }, [config.fields.cloudInit, cloudInit, windows]);

  // Prices follow the billing cycle chosen in the cart.
  useEffect(() => {
    const onChange = (e: Event) => {
      if ((e.target as HTMLElement | null)?.getAttribute?.("name") === "billingcycle") setCycle(billingCycle());
    };
    document.addEventListener("change", onChange);
    return () => document.removeEventListener("change", onChange);
  }, []);

  const price = (subId: number | undefined) => (subId ? config.prices[subId]?.[cycle] ?? 0 : 0);
  const money = (amount: number, signed = false) => {
    const sign = amount < 0 ? "-" : signed ? "+" : "";
    return `${sign}${config.currency.prefix}${Math.abs(amount).toFixed(2)}${config.currency.suffix}`;
  };
  const perCycle = t(`store.cycle.${cycle}`);

  // OS cards: one per family with a version picker, newest version first.
  const osFamilies = useMemo(() => {
    const families = new Map<string, StoreValue[]>();
    for (const v of template.values.filter((v) => v.kind !== "app")) {
      const key = v.os || splitVersion(v.label)[0].toLowerCase();
      families.set(key, [...(families.get(key) ?? []), v]);
    }
    for (const versions of families.values()) versions.sort((a, b) => b.label.localeCompare(a.label, undefined, { numeric: true }));
    return Array.from(families.entries());
  }, [template.values]);
  const apps = template.values.filter((v) => v.kind === "app");
  const [familyVersion, setFamilyVersion] = useState<Record<string, number>>({});

  const sshKeyInvalid = !windows && sshKey.trim() !== "" && !SSH_KEY.test(sshKey.trim());
  const hasServerFields = !!document.querySelector('[name="hostname"]');

  return (
    <div className="flex flex-col gap-4">
      <Section title={t("store.location")} hint={t("store.locationHint")}>
        <div className="grid grid-cols-[repeat(auto-fill,minmax(170px,1fr))] gap-2">
          {location.values.map((v) => {
            const [city, region] = v.label.split(/,\s*/, 2);
            const country = COUNTRIES.find(([re]) => re.test(v.label))?.[1];
            return (
              <Choice key={v.id} selected={v.id === locationId} onSelect={() => setLocationId(v.id)} extra={priceTag(price(v.id), money, perCycle)}>
                {country ? (
                  <img src={`${config.assets}flags/${country}.svg`} alt="" className="h-4 w-6 shrink-0 rounded-[2px] object-cover shadow-sm" />
                ) : (
                  <Globe className="h-4 w-4 shrink-0 text-muted-foreground" />
                )}
                <span className="flex min-w-0 flex-col">
                  <span className="truncate text-sm font-medium">{city}</span>
                  {region && <span className="truncate text-xs text-muted-foreground">{region}</span>}
                </span>
              </Choice>
            );
          })}
        </div>
      </Section>

      <Section
        title={t("store.image")}
        hint={t("store.imageHint")}
        action={
          apps.length > 0 && (
            <div className="inline-flex rounded-lg border bg-muted p-0.5 text-xs font-medium">
              {(["os", "app"] as const).map((k) => (
                <button
                  key={k}
                  type="button"
                  onClick={() => setTab(k)}
                  className={cn("cursor-pointer rounded-md px-3 py-1", tab === k ? "bg-card text-foreground shadow-sm" : "text-muted-foreground")}
                >
                  {t(k === "os" ? "store.tab.os" : "store.tab.apps")}
                </button>
              ))}
            </div>
          )
        }
      >
        {tab === "os" ? (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-2">
            {osFamilies.map(([family, versions]) => {
              const selected = versions.some((v) => v.id === templateId);
              const current = selected ? templateId : familyVersion[family] ?? versions[0].id;
              const name = splitVersion(versions[0].label)[0];
              return (
                <div
                  key={family}
                  role="button"
                  tabIndex={0}
                  onClick={() => setTemplateId(current)}
                  onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && setTemplateId(current)}
                  className={cn(
                    "relative flex cursor-pointer flex-col items-center gap-2 rounded-lg border bg-card p-3 text-center transition-colors hover:bg-muted/50",
                    selected ? "border-foreground ring-1 ring-foreground" : "border-border",
                  )}
                >
                  {selected && <SelectedMark />}
                  <Icon src={iconFor(config.assets, family)} className="h-9 w-9" />
                  <span className="text-sm font-medium">{name}</span>
                  {versions.length > 1 ? (
                    <Select
                      value={current}
                      className="h-7 text-xs"
                      onClick={(e) => e.stopPropagation()}
                      onChange={(e) => {
                        const id = Number(e.target.value);
                        setFamilyVersion({ ...familyVersion, [family]: id });
                        setTemplateId(id);
                      }}
                    >
                      {versions.map((v) => (
                        <option key={v.id} value={v.id}>
                          {splitVersion(v.label)[1] || v.label}
                        </option>
                      ))}
                    </Select>
                  ) : (
                    <span className="text-xs text-muted-foreground">{splitVersion(versions[0].label)[1] || " "}</span>
                  )}
                </div>
              );
            })}
          </div>
        ) : (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-2">
            {apps.map((v) => (
              <Choice key={v.id} selected={v.id === templateId} onSelect={() => setTemplateId(v.id)}>
                <Icon src={iconFor(config.assets, v.os || v.value)} className="h-7 w-7 shrink-0" />
                <span className="min-w-0 truncate text-sm font-medium">{v.label}</span>
              </Choice>
            ))}
          </div>
        )}
        {selectedTemplate && price(selectedTemplate.id) !== 0 && (
          <p className="mt-2 text-xs text-muted-foreground">
            {selectedTemplate.label}: {money(price(selectedTemplate.id), true)} {perCycle}
          </p>
        )}
      </Section>

      {(network || backups) && (
        <div className={cn("grid gap-4", network && backups && "@lg:grid-cols-2")}>
          {network && (
            <Section title={t("store.network")} hint={t("store.networkHint")}>
              <div className="grid gap-2">
                {network.values.map((v) => (
                  <Choice key={v.id} selected={v.id === networkId} onSelect={() => setNetworkId(v.id)} extra={priceTag(price(v.id), money, perCycle)}>
                    <Network className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span className="flex min-w-0 flex-col">
                      <span className="text-sm font-medium">{v.value === "ipv6" ? t("store.network.ipv6") : t("store.network.dual")}</span>
                      <span className="text-xs text-muted-foreground">{v.value === "ipv6" ? t("store.network.ipv6Hint") : t("store.network.dualHint")}</span>
                    </span>
                  </Choice>
                ))}
              </div>
            </Section>
          )}
          {backups && backupsSub && (
            <Section title={t("store.backups")} hint={t("store.backupsHint")}>
              <label
                className={cn(
                  "flex cursor-pointer items-center gap-3 rounded-lg border bg-card px-4 py-3 transition-colors hover:bg-muted/50",
                  backupsOn ? "border-foreground ring-1 ring-foreground" : "border-border",
                )}
              >
                <HardDriveDownload className="h-4 w-4 shrink-0 text-muted-foreground" />
                <span className="flex min-w-0 flex-1 flex-col">
                  <span className="text-sm font-medium">{t("store.backupsDaily")}</span>
                  <span className="text-xs text-muted-foreground">{t("store.backupsDetail")}</span>
                </span>
                <span className="shrink-0 text-xs font-medium text-muted-foreground">{priceTag(price(backupsSub.id), money, perCycle)}</span>
                <Switch checked={backupsOn} onCheckedChange={setBackupsOn} aria-label={t("store.backups")} />
              </label>
            </Section>
          )}
        </div>
      )}

      <Section title={t("store.access")} hint={t(windows ? "store.accessHintWindows" : "store.accessHint")}>
        <div className="flex flex-col gap-3">
          {hasServerFields && (
            <div className="grid gap-3 @sm:grid-cols-2">
              <div className="grid gap-1.5">
                <Label htmlFor="cp-hostname">{t("store.hostname")}</Label>
                <Input id="cp-hostname" value={hostname} placeholder="server1.example.com" autoComplete="off" onChange={(e) => setHostname(e.target.value.trim())} />
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="cp-rootpw">{t("store.password")}</Label>
                <div className="relative">
                  <Input
                    id="cp-rootpw"
                    type={showPassword ? "text" : "password"}
                    value={password}
                    autoComplete="new-password"
                    className="pr-16 font-mono"
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  <div className="absolute right-1 top-1/2 flex -translate-y-1/2 gap-0.5">
                    <IconButton label={showPassword ? t("common.hide") : t("common.show")} onClick={() => setShowPassword((v) => !v)}>
                      {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </IconButton>
                    <IconButton
                      label={t("common.generate")}
                      onClick={() => {
                        setPassword(generatePassword());
                        setShowPassword(true);
                      }}
                    >
                      <RefreshCw className="h-4 w-4" />
                    </IconButton>
                  </div>
                </div>
              </div>
            </div>
          )}
          {config.fields.sshKey && !windows && (
            <div className="grid gap-1.5">
              <Label htmlFor="cp-sshkey" className="flex items-center gap-1.5">
                <KeyRound className="h-3.5 w-3.5" />
                {t("store.sshKey")} <span className="font-normal text-muted-foreground">({t("store.optional")})</span>
              </Label>
              <textarea
                id="cp-sshkey"
                rows={3}
                value={sshKey}
                spellCheck={false}
                placeholder="ssh-ed25519 AAAA... user@laptop"
                onChange={(e) => setSshKey(e.target.value)}
                className={cn(
                  "w-full rounded-md border bg-card px-3 py-2 font-mono text-xs text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                  sshKeyInvalid ? "border-destructive" : "border-input",
                )}
              />
              {sshKeyInvalid && <p className="text-xs text-destructive">{t("store.sshKeyInvalid")}</p>}
            </div>
          )}
        </div>
      </Section>

      {config.fields.cloudInit && !windows && (
        <div className="rounded-xl border bg-card">
          <button
            type="button"
            onClick={() => setAdvanced((v) => !v)}
            className="flex w-full cursor-pointer items-center justify-between px-4 py-3 text-left text-sm font-semibold"
          >
            {t("store.advanced")}
            <ChevronDown className={cn("h-4 w-4 text-muted-foreground transition-transform", advanced && "rotate-180")} />
          </button>
          {advanced && (
            <div className="grid gap-1.5 border-t px-4 py-3">
              <Label htmlFor="cp-cloudinit">
                {t("store.cloudInit")} <span className="font-normal text-muted-foreground">({t("store.optional")})</span>
              </Label>
              <textarea
                id="cp-cloudinit"
                rows={8}
                value={cloudInit}
                spellCheck={false}
                placeholder={"#cloud-config\npackages:\n  - nginx"}
                onChange={(e) => setCloudInit(e.target.value)}
                className="w-full rounded-md border border-input bg-card px-3 py-2 font-mono text-xs text-foreground shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              />
              <p className="text-xs text-muted-foreground">{t("store.cloudInitHint")}</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function priceTag(amount: number, money: (amount: number, signed?: boolean) => string, perCycle: string) {
  if (amount === 0) return null;
  return (
    <span className="whitespace-nowrap text-xs font-medium text-muted-foreground">
      {money(amount, true)} {perCycle}
    </span>
  );
}

function Section({ title, hint, action, children }: { title: string; hint?: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-xl border bg-card p-4">
      <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 className="text-sm font-semibold">{title}</h3>
          {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function Choice({ selected, onSelect, extra, children }: { selected: boolean; onSelect: () => void; extra?: ReactNode; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={selected}
      onClick={onSelect}
      className={cn(
        "relative flex min-w-0 cursor-pointer items-center gap-3 rounded-lg border bg-card px-3 py-2.5 text-left transition-colors hover:bg-muted/50",
        selected ? "border-foreground ring-1 ring-foreground" : "border-border",
      )}
    >
      {children}
      {extra && <span className="ml-auto shrink-0 pl-1">{extra}</span>}
    </button>
  );
}

function SelectedMark() {
  return (
    <span className="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-primary-foreground">
      <Check className="h-3 w-3" />
    </span>
  );
}

function IconButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      className="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
    >
      {children}
    </button>
  );
}
