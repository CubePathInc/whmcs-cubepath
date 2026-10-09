/**
 * Bridge between the configurator cards and the WHMCS order form. The cards
 * write every choice to the native field, hidden but still in the form, so
 * the cart prices, validates and submits the order as usual.
 */

type Field = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

declare global {
  interface Window {
    recalctotals?: () => void;
  }
}

export function field(name: string): Field | null {
  return document.querySelector<Field>(`[name="${name}"]`);
}

let recalcTimer: number | undefined;

/** Set a native field and let the cart recalculate its totals. */
export function setField(name: string, value: string | boolean) {
  const el = field(name);
  if (!el) return;
  if (el instanceof HTMLInputElement && el.type === "checkbox") el.checked = value === true;
  else el.value = String(value);
  el.dispatchEvent(new Event("input", { bubbles: true }));
  el.dispatchEvent(new Event("change", { bubbles: true }));
  window.clearTimeout(recalcTimer);
  recalcTimer = window.setTimeout(() => window.recalctotals?.(), 50);
}

export function fieldValue(name: string): string {
  const el = field(name);
  if (!el) return "";
  if (el instanceof HTMLInputElement && el.type === "checkbox") return el.checked ? "1" : "";
  return el.value;
}

/** The element that holds a field and its label, to hide them together. */
function wrapperOf(el: Element): HTMLElement {
  let wrapper = (el.closest(".form-group") as HTMLElement | null) ?? (el.parentElement as HTMLElement);
  const parent = wrapper.parentElement;
  if (parent && /\bcol-/.test(parent.className) && parent.children.length === 1) wrapper = parent;
  return wrapper;
}

const SECTIONS = "#productConfigurableOptions, .product-configurable-options, .field-container";

/**
 * Hide the given native fields, and the sections (with their headings) that
 * are left with nothing visible. Returns the element the cards should be
 * inserted before: the first section that held one of the fields.
 */
export function hideFields(names: string[]): Element | null {
  const elements = names.map(field).filter((el): el is Field => el !== null);
  if (elements.length === 0) return null;

  elements.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
  const first = elements[0];
  const firstSection = first.closest(SECTIONS);
  const heading = firstSection?.previousElementSibling?.classList.contains("sub-heading") ? firstSection.previousElementSibling : null;
  const anchor = heading ?? firstSection ?? wrapperOf(first);

  const sections = new Set<HTMLElement>();
  for (const el of elements) {
    wrapperOf(el).style.display = "none";
    const section = el.closest<HTMLElement>(SECTIONS);
    if (section) sections.add(section);
  }

  for (const section of sections) {
    const visible = Array.from(section.querySelectorAll<Field>("input, select, textarea")).some(
      (el) => el.type !== "hidden" && el.offsetParent !== null,
    );
    if (visible) continue;
    section.style.display = "none";
    const prev = section.previousElementSibling as HTMLElement | null;
    if (prev?.classList.contains("sub-heading")) prev.style.display = "none";
  }

  return anchor;
}

/** Billing cycle chosen in the order form ("monthly"). */
export function billingCycle(): string {
  return fieldValue("billingcycle") || "monthly";
}
