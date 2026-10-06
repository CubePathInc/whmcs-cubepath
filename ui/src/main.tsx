import { createRoot } from "react-dom/client";
import styles from "./index.css?inline";
import { App } from "./App";
import type { PanelConfig } from "./lib/types";

/**
 * Mounts a panel on every <div data-cubepath-panel="{base64 JSON config}">. Each one
 * renders inside a shadow root so the WHMCS theme (Bootstrap) and the panel's
 * Tailwind styles cannot affect each other.
 */
function mountAll() {
  document.querySelectorAll<HTMLElement>("[data-cubepath-panel]").forEach((host) => {
    if (host.shadowRoot) return;
    let config: PanelConfig;
    try {
      const bytes = Uint8Array.from(atob(host.dataset.cubepathPanel || ""), (c) => c.charCodeAt(0));
      config = JSON.parse(new TextDecoder().decode(bytes));
    } catch {
      host.textContent = "CubePath: invalid panel configuration.";
      return;
    }
    const shadow = host.attachShadow({ mode: "open" });
    const style = document.createElement("style");
    style.textContent = styles;
    const container = document.createElement("div");
    shadow.append(style, container);
    createRoot(container).render(<App config={config} />);
  });
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mountAll);
else mountAll();
