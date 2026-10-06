import { useState } from "react";
import { Moon, Sun } from "lucide-react";
import { ToastProvider } from "@/components/ui/toast";
import { ConfigContext } from "@/lib/api";
import { cn } from "@/lib/utils";
import type { PanelConfig } from "@/lib/types";
import { LocaleContext, resolveLocale, useT } from "@/i18n";
import { ServicePanel } from "@/views/ServicePanel";
import { ResellerList } from "@/views/ResellerList";

const THEME_KEY = "cubepath.panel.theme";

function savedTheme(): "light" | "dark" {
  try {
    return localStorage.getItem(THEME_KEY) === "dark" ? "dark" : "light";
  } catch {
    return "light";
  }
}

export function App({ config }: { config: PanelConfig }) {
  const [theme, setTheme] = useState(savedTheme);

  const toggleTheme = () => {
    const next = theme === "dark" ? "light" : "dark";
    setTheme(next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch {
      /* storage unavailable */
    }
  };

  return (
    <ConfigContext.Provider value={config}>
      <LocaleContext.Provider value={resolveLocale(config.lang)}>
        <div className={cn("cp-root @container rounded-xl bg-background p-3 @sm:p-4", theme === "dark" && "dark")}>
          <ToastProvider>
            <ThemeToggle theme={theme} onToggle={toggleTheme} />
            {config.mode === "reseller" ? <ResellerList /> : <ServicePanel />}
          </ToastProvider>
        </div>
      </LocaleContext.Provider>
    </ConfigContext.Provider>
  );
}

function ThemeToggle({ theme, onToggle }: { theme: "light" | "dark"; onToggle: () => void }) {
  const t = useT();
  const label = theme === "dark" ? t("common.lightMode") : t("common.darkMode");
  return (
    <div className="mb-2 flex justify-end">
      <button
        type="button"
        onClick={onToggle}
        aria-label={label}
        title={label}
        className="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
      >
        {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
      </button>
    </div>
  );
}
