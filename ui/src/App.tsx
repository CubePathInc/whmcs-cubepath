import { ToastProvider } from "@/components/ui/toast";
import { ConfigContext } from "@/lib/api";
import type { PanelConfig } from "@/lib/types";
import { LocaleContext, resolveLocale } from "@/i18n";
import { ServicePanel } from "@/views/ServicePanel";
import { ResellerList } from "@/views/ResellerList";
import { AddonApp } from "@/views/addon/AddonApp";

export function App({ config }: { config: PanelConfig }) {
  return (
    <ConfigContext.Provider value={config}>
      <LocaleContext.Provider value={resolveLocale(config.lang)}>
        <div className="cp-root @container rounded-xl bg-background p-3 @sm:p-4">
          <ToastProvider>
            {config.mode === "addon" ? <AddonApp /> : config.mode === "reseller" ? <ResellerList /> : <ServicePanel />}
          </ToastProvider>
        </div>
      </LocaleContext.Provider>
    </ConfigContext.Provider>
  );
}
