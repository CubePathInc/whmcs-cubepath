import { createRoot } from "react-dom/client";
import styles from "./index.css?inline";
import { LocaleContext, resolveLocale } from "@/i18n";
import type { StoreConfig } from "@/lib/types";
import { StoreConfigurator } from "@/views/store/StoreConfigurator";
import { hideFields } from "@/views/store/form";

/**
 * Order form cards: mounted on <div data-cubepath-panel="{base64 StoreConfig}">,
 * which the hook prints at the end of the page. The div is moved to where the
 * native fields it replaces were, and those are hidden.
 */
function mount() {
  const host = document.querySelector<HTMLElement>("[data-cubepath-panel]");
  if (!host || host.shadowRoot) return;

  let config: StoreConfig;
  try {
    const bytes = Uint8Array.from(atob(host.dataset.cubepathPanel || ""), (c) => c.charCodeAt(0));
    config = JSON.parse(new TextDecoder().decode(bytes));
  } catch {
    return;
  }

  const { options, fields } = config;
  const names = [options.location, options.template, options.network, options.backups]
    .filter((o) => o !== undefined)
    .map((o) => `configoption[${o!.id}]`);
  for (const id of [fields.sshKey, fields.cloudInit]) if (id) names.push(`customfield[${id}]`);
  names.push("hostname", "rootpw", "ns1prefix", "ns2prefix");

  // Without the location field this is not the page the cards expect: leave the form as is.
  if (!document.querySelector(`[name="configoption[${options.location.id}]"]`)) return;

  // Nameserver prefixes are required by WHMCS for server products but mean nothing for a VPS.
  for (const [name, value] of [["ns1prefix", "ns1"], ["ns2prefix", "ns2"]]) {
    const el = document.querySelector<HTMLInputElement>(`[name="${name}"]`);
    if (el && !el.value) el.value = value;
  }

  const anchor = hideFields(names);
  anchor?.parentElement?.insertBefore(host, anchor);
  host.style.marginBottom = "20px";

  const shadow = host.attachShadow({ mode: "open" });
  const style = document.createElement("style");
  style.textContent = styles;
  const container = document.createElement("div");
  container.className = "cp-root @container";
  shadow.append(style, container);
  createRoot(container).render(
    <LocaleContext.Provider value={resolveLocale(config.lang)}>
      <StoreConfigurator config={config} />
    </LocaleContext.Provider>,
  );
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount);
else mount();
