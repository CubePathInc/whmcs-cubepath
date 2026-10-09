export type Mode = "client" | "admin" | "reseller" | "addon";

export interface PanelConfig {
  mode: Mode;
  endpoint: string;
  token: string;
  lang: string;
  serviceId?: number;
  /** Admin URL of a service, with {userid} and {id} placeholders (reseller view). */
  serviceUrl?: string;
  /** Page the addon panel opens on. */
  page?: string;
}

export type VpsStatus =
  | "active"
  | "stopped"
  | "updating"
  | "failed"
  | "deleting"
  | "deploying"
  | "suspended"
  | "resizing"
  | "deleted";

export interface Vps {
  id: number;
  name: string;
  label: string | null;
  hostname: string | null;
  status: VpsStatus;
  user: string | null;
  plan: { plan_name: string; ram: number; cpu: number; storage: number; bandwidth: number } | null;
  template: { template_name: string; os_name: string } | null;
  location: { description: string; location_name: string } | null;
  ipv4: string | null;
  ipv6: string | null;
  private_ip: string | null;
}

export interface ServiceInfo {
  id: number;
  status: string;
  product: string;
  domain: string;
  username: string;
  regdate: string | null;
  nextduedate: string | null;
  billingcycle: string;
  amount: string;
  client?: { id: number; name: string; email: string };
}

export interface Bandwidth {
  inBytes: number;
  outBytes: number;
  totalBytes: number;
  includedBytes: number;
}

export interface Overview {
  service: ServiceInfo;
  vps: Vps | null;
  bandwidth: Bandwidth | null;
  /** Why the VPS could not be loaded (not provisioned yet, API error...). */
  notice: string | null;
}

export interface Series {
  name: string;
  unit: string;
  points: [number, number][];
}

export interface Metrics {
  step: number;
  series: Series[];
  unavailable: boolean;
}

export interface IpAddress {
  address: string;
  type: "IPv4" | "IPv6";
  netmask: string;
  is_primary: boolean;
  reverse_dns: string | null;
}

export interface Network {
  ips: IpAddress[];
  private: { name: string; ip_range: string; prefix: number; assigned_ip: string } | null;
}

export interface FirewallRule {
  direction: "in" | "out";
  protocol: "tcp" | "udp" | "icmp" | "gre";
  port: string | null;
  source: string | null;
  comment: string | null;
}

export interface Firewall {
  enabled: boolean;
  rules: FirewallRule[];
  /** Groups attached in CubePath that this panel does not manage. */
  external: { id: number; name: string }[];
  maxRules: number;
}

export interface Backup {
  id: number;
  backup_type: string;
  status: string;
  progress: number;
  size_gb: number | null;
  notes: string | null;
  error_message: string | null;
  created_at: string;
  completed_at: string | null;
}

export interface BackupSettings {
  enabled: boolean;
  schedule_hour: number;
  retention_days: number;
  max_backups: number;
}

export interface Backups {
  backups: Backup[];
  total: number;
  settings: BackupSettings;
}

export interface Iso {
  id: string;
  name: string;
  description: string | null;
  file_size: number;
  is_mounted: boolean;
}

export interface Isos {
  mounted: string | null;
  items: Iso[];
}

export interface TemplateOption {
  value: string;
  label: string;
}

export interface ActivityEntry {
  id: number;
  created_at: string;
  actor: "client" | "admin" | "system";
  action: string;
  details: string | null;
  success: boolean;
}

export interface ResellerServer {
  serviceId: number;
  clientId: number;
  client: string;
  email: string;
  product: string;
  domain: string;
  serviceStatus: string;
  vps: Vps | null;
}

export interface ServerRef {
  id: number;
  name: string;
}

export interface AddonDashboard {
  apiError: string | null;
  server: ServerRef | null;
  legacyToken: boolean;
  projectId: string;
  projectName: string | null;
  plans: number;
  locations: number;
  templates: number;
  products: number;
  services: number;
}

export interface CatalogPlan {
  name: string;
  /** CubePath cluster the plan runs on ("General Purpose"). */
  family: string;
  cpu: number;
  ramMb: number;
  storageGb: number;
  transferTb: number;
  monthly: number;
  locations: string[];
  available: boolean;
  exists: boolean;
}

export interface CreatorData {
  projectId: string;
  plans: CatalogPlan[];
  groups: { id: number; name: string }[];
  /** Family => name of the product group the creator made for it. */
  familyGroups: Record<string, string>;
  currencies: { id: number; code: string; prefix: string; default: boolean }[];
}

export interface AddonProduct {
  id: number;
  name: string;
  group: string;
  plan: string;
  paytype: "recurring" | "onetime" | "free";
  hidden: boolean;
  retired: boolean;
  services: number;
}

export interface ToggleData {
  items: { value: string; label: string; type: "location" | "os" | "app" }[];
  disabled: string[];
}

export interface AddonSettings {
  server: ServerRef | null;
  tokenHint: string;
  projectId: string;
  projects: { id: string; name: string }[];
  projectsError: string | null;
  products: number;
}
