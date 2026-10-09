import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import type { PanelConfig } from "./types";

export class ApiError extends Error {}

/**
 * Calls the module's JSON endpoint. Requests are form-encoded so WHMCS reads
 * the CSRF token from $_POST like any of its own forms.
 */
export async function callApi<T>(config: PanelConfig, action: string, data: Record<string, unknown> = {}): Promise<T> {
  const body = new URLSearchParams();
  body.set("token", config.token);
  body.set("cpaction", action);
  body.set("cpdata", JSON.stringify(data));
  if (config.serviceId) body.set("serviceid", String(config.serviceId));

  let response: Response;
  try {
    response = await fetch(config.endpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest" },
      body,
    });
  } catch {
    throw new ApiError("Network error. Check your connection and try again.");
  }

  const text = await response.text();
  let json: { ok?: boolean; data?: T; error?: string };
  try {
    json = JSON.parse(text);
  } catch {
    throw new ApiError(response.ok ? "Unexpected response from the server." : `Request failed (${response.status}).`);
  }
  if (!json.ok) throw new ApiError(json.error || "Request failed.");
  return json.data as T;
}

export const ConfigContext = createContext<PanelConfig | null>(null);

export function useConfig(): PanelConfig {
  const config = useContext(ConfigContext);
  if (!config) throw new Error("ConfigContext missing");
  return config;
}

export interface Query<T> {
  data: T | undefined;
  error: string | null;
  loading: boolean;
  reload: () => Promise<void>;
}

/** Fetches an action on mount and whenever its data changes. */
export function useQuery<T>(action: string, data: Record<string, unknown> = {}): Query<T> {
  const config = useConfig();
  const [state, setState] = useState<{ data: T | undefined; error: string | null; loading: boolean }>({
    data: undefined,
    error: null,
    loading: true,
  });
  const key = JSON.stringify(data);
  const keyRef = useRef(key);
  keyRef.current = key;

  const reload = useCallback(async () => {
    const requested = key;
    try {
      const result = await callApi<T>(config, action, JSON.parse(requested));
      if (keyRef.current === requested) setState({ data: result, error: null, loading: false });
    } catch (e) {
      if (keyRef.current === requested) setState((s) => ({ data: s.data, error: (e as Error).message, loading: false }));
    }
  }, [config, action, key]);

  useEffect(() => {
    setState((s) => ({ ...s, loading: true }));
    void reload();
  }, [reload]);

  return { ...state, reload };
}

/** Calls fn every ms while the page is visible. */
export function usePolling(fn: () => Promise<void>, ms: number) {
  const saved = useRef(fn);
  saved.current = fn;
  useEffect(() => {
    const timer = window.setInterval(() => {
      if (document.visibilityState === "visible") void saved.current();
    }, ms);
    return () => window.clearInterval(timer);
  }, [ms]);
}
