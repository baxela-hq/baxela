import { getRequest, patchRequest, postRequest } from "@/shared/lib/api-client";
import type { Currency } from "@/shared/types/locale.types";
import type { AllResponse } from "@/shared/types/common.types";
import type { CurrencyForm } from "../data/schema";
import { type Setting, type SettingRequest } from "@/features/setting/settings/data/schema";

const BASE_URL = "core/admin/currencies";

/**
 * Active currencies by default (what the settings default-currency dropdown
 * consumes). The management pages pass `all` to also list deactivated rows.
 */
export async function fetchCurrencies(all = false): Promise<Currency[]> {
  const { data } = await getRequest<AllResponse<Currency>>(
    all ? `${BASE_URL}?all=1` : BASE_URL
  );
  return data as Currency[];
}

// Partial PATCH: the drawer sends the edit fields, the row switch sends
// only { is_active }.
export function updateCurrency(
  id: number,
  data: Partial<CurrencyForm>
): Promise<Currency> {
  return patchRequest<Currency, Partial<CurrencyForm>>(
    `${BASE_URL}/${id}`,
    data
  );
}
export function createCurrency(data: Partial<CurrencyForm>) {
  return postRequest<Currency, Partial<CurrencyForm>>(BASE_URL, data)
}

// ---------------------------------------------------------------------------
// FX rate auto-sync (Part E)
// ---------------------------------------------------------------------------

export interface FxSyncStatus {
  last_attempt: {
    at: string
    status: 'success' | 'failed'
    error_code: string | null
    provider_timestamp: number | null
    base_code: string | null
  } | null
  last_success: {
    at: string
    provider_timestamp: number
    base_code: string
    updated_count: number
    skipped_count: number
    updated: { code: string; from: string; to: string }[]
    skipped: string[]
    truncated: boolean
  } | null
}

export interface FxSyncResponse {
  outcome: 'synced' | 'sync_in_progress' | 'skipped_disabled' | 'skipped_not_due' | 'skipped_cooldown'
  status: FxSyncStatus | null
}

/**
 * The "Sync now" path — the same engine as the hourly schedule with the
 * gates bypassed. 409 means another sync is running.
 */
export async function syncFxRates(): Promise<FxSyncResponse> {
  const { data } = await postRequest<{ data: FxSyncResponse }, object>(
    `${BASE_URL}/sync-rates`,
    {}
  )
  return data
}

const SETTINGS_URL = 'setting/admin/settings'

/** The FX settings rows as the admin sees them (the key is masked). */
export async function fetchFxSettings(): Promise<Record<string, string | null>> {
  const { data } = await getRequest<AllResponse<Setting>>(SETTINGS_URL)
  const rows = (data as Setting[]).filter((setting) =>
    setting.name.startsWith('fx_')
  )

  return Object.fromEntries(rows.map((row) => [row.name, row.value]))
}

/**
 * Save the editable FX settings. The API key is NEVER included unless
 * the caller passes one explicitly — Laravel's ConvertEmptyStringsToNull
 * makes an empty submission indistinguishable from a deliberate clear,
 * so "keep" is expressed by omitting the item; `null` clears.
 */
export async function saveFxSettings(items: SettingRequest[]): Promise<void> {
  await patchRequest<AllResponse<Setting>, SettingRequest[]>(
    SETTINGS_URL,
    items
  )
}
