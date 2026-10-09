import { Server } from "lucide-react";
import { cn } from "@/lib/utils";

const ABBREVIATIONS: Record<string, string> = {
  ubuntu: "Ub",
  debian: "De",
  almalinux: "Al",
  rocky: "Ro",
  centos: "Ce",
  fedora: "Fe",
  windows: "Wi",
  opensuse: "Su",
  freebsd: "Bs",
  alpine: "Ap",
  arch: "Ar",
  oracle: "Or",
};

/** Monochrome OS badge, matching the dashboard's neutral palette. */
export function OsIcon({ name, className }: { name: string; className?: string }) {
  const key = Object.keys(ABBREVIATIONS).find((k) => name.toLowerCase().startsWith(k));
  return (
    <div className={cn("flex items-center justify-center rounded-lg border bg-muted text-sm font-semibold text-foreground", className)}>
      {key ? ABBREVIATIONS[key] : <Server className="h-5 w-5 text-muted-foreground" />}
    </div>
  );
}
