import { cache } from "react";
import { api } from "./client";
import { serverApiGet } from "./server";
import type { ApiActiveCurrency, ApiCurrency, ApiMenu, ApiSetting } from "./types";

// Public site menus — header (nav) and footer (link columns) both come from
// the Menu module. Returns null when the backend is unreachable so the
// layout degrades gracefully instead of erroring the whole page.
export async function fetchMenu(location: string): Promise<ApiMenu | null> {
  try {
    return await serverApiGet<ApiMenu>(`/menu/public/menus/${location}`);
  } catch {
    return null;
  }
}

// Public site settings (Setting module) — title/description, the announcement
// bar, and the storefront theme. Returns null when the backend is unreachable;
// callers skip whatever depends on it instead of erroring the whole page.
// cache() dedupes the two server callers (root layout resolves the site
// theme, SiteHeader reads the announcement) into one API request per render.
export const fetchSettings = cache(
  async (): Promise<ApiSetting[] | null> => {
    try {
      return await serverApiGet<ApiSetting[]>("/setting/public/settings");
    } catch {
      return null;
    }
  },
);

// Store's default currency (Core module). Same contract as fetchSettings:
// null when the backend is unreachable, so formatting falls back to USD.
export const fetchCurrency = cache(
  async (): Promise<ApiCurrency | null> => {
    try {
      return await serverApiGet<ApiCurrency>("/core/public/currency");
    } catch {
      return null;
    }
  },
);
// Active currencies with display-conversion rates (Core module) — the
// switcher's data and the conversion context, default-first. Fetched via
// the plain client (not serverApiGet) so the request-config resolver can
// share this cache without recursing through getLocale(); currency rows
// carry no translated fields, so locale injection is unnecessary anyway.
export const fetchActiveCurrencies = cache(
  async (): Promise<ApiActiveCurrency[] | null> => {
    try {
      return await api.get<ApiActiveCurrency[]>("/core/public/currencies");
    } catch {
      return null;
    }
  },
);
