import type { ComponentType } from "react";
import { cn } from "@/lib/utils";

export interface TabItem<K extends string> {
  key: K;
  label: string;
  icon: ComponentType<{ className?: string }>;
}

interface TabsProps<K extends string> {
  items: TabItem<K>[];
  value: K;
  onChange: (key: K) => void;
}

export function Tabs<K extends string>({ items, value, onChange }: TabsProps<K>) {
  return (
    <div>
      <div role="tablist" className="flex flex-wrap gap-x-1 border-b">
        {items.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={value === key}
            onClick={() => onChange(key)}
            className={cn(
              "-mb-px inline-flex cursor-pointer items-center gap-2 whitespace-nowrap border-b-2 px-2.5 py-2.5 text-sm font-medium transition-colors",
              value === key
                ? "border-primary text-foreground"
                : "border-transparent text-muted-foreground hover:text-foreground",
            )}
          >
            <Icon className="h-4 w-4" />
            {label}
          </button>
        ))}
      </div>
    </div>
  );
}
