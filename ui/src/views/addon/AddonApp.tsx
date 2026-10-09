import { createContext, useContext, useState, type ReactNode } from "react";
import { Boxes, LayoutDashboard, MapPin, Package, Server, Settings } from "lucide-react";
import { Tabs, type TabItem } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/shared";
import { useConfig } from "@/lib/api";
import { useT } from "@/i18n";
import { ResellerList } from "@/views/ResellerList";
import { DashboardPage } from "./DashboardPage";
import { CreatorPage } from "./CreatorPage";
import { ProductsPage } from "./ProductsPage";
import { TogglesPage } from "./TogglesPage";
import { SettingsPage } from "./SettingsPage";

export type AddonPage = "dashboard" | "vps" | "creator" | "products" | "locations" | "templates" | "settings";

const PAGES: AddonPage[] = ["dashboard", "vps", "creator", "products", "locations", "templates", "settings"];

/** The creator is reached from Products, so it has no tab of its own. */
const TAB_PAGES = PAGES.filter((p) => p !== "creator");

const ICONS = {
  dashboard: LayoutDashboard,
  vps: Server,
  products: Package,
  locations: MapPin,
  templates: Boxes,
  settings: Settings,
};

const NavigateContext = createContext<(page: AddonPage) => void>(() => undefined);

export function useNavigate() {
  return useContext(NavigateContext);
}

/**
 * Admin panel of the addon (Addons > CubePath). Pages switch without a reload;
 * the page query parameter is kept in the URL so a reload opens the same page.
 */
export function AddonApp() {
  const t = useT();
  const config = useConfig();
  const [page, setPage] = useState<AddonPage>(PAGES.includes(config.page as AddonPage) ? (config.page as AddonPage) : "dashboard");

  const navigate = (next: AddonPage) => {
    setPage(next);
    try {
      const url = new URL(window.location.href);
      url.searchParams.set("page", next);
      window.history.replaceState(null, "", url.toString());
    } catch {
      /* URL not writable */
    }
  };

  const items: TabItem<AddonPage>[] = TAB_PAGES.map((key) => ({ key, label: t(`addon.page.${key}`), icon: ICONS[key] }));

  return (
    <NavigateContext.Provider value={navigate}>
      <div className="flex flex-col gap-4">
        <div className="overflow-x-auto">
          <Tabs items={items} value={page === "creator" ? "products" : page} onChange={navigate} />
        </div>

        {page === "dashboard" && <DashboardPage />}
        {page === "vps" && <ResellerList />}
        {page === "creator" && <CreatorPage />}
        {page === "products" && <ProductsPage />}
        {page === "locations" && <TogglesPage key="locations" kind="locations" />}
        {page === "templates" && <TogglesPage key="templates" kind="templates" />}
        {page === "settings" && <SettingsPage />}
      </div>
    </NavigateContext.Provider>
  );
}

/** Title row of a page, with optional actions on the right. */
export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
  return (
    <div className="flex flex-col gap-3 @sm:flex-row @sm:items-start @sm:justify-between">
      <div className="min-w-0">
        <h2 className="text-base font-semibold">{title}</h2>
        {description && <p className="mt-1 max-w-2xl text-xs text-muted-foreground">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </div>
  );
}

/** Error of a page that needs the CubePath API, with a way to the settings. */
export function PageError({ message, onRetry }: { message: string; onRetry: () => void }) {
  const t = useT();
  const navigate = useNavigate();
  return (
    <div className="rounded-xl border bg-card">
      <ErrorState message={message} onRetry={onRetry} />
      <div className="-mt-6 flex justify-center pb-8">
        <Button variant="ghost" size="sm" onClick={() => navigate("settings")}>
          <Settings />
          {t("addon.goToSettings")}
        </Button>
      </div>
    </div>
  );
}
