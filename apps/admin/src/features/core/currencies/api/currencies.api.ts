import { getRequest, patchRequest } from "@/shared/lib/api-client";
import type { Currency } from "@/shared/types/locale.types";
import type { AllResponse } from "@/shared/types/common.types";
import type { CurrencyForm } from "../data/schema";

const BASE_URL = "core/admin/currencies";

export async function fetchCurrencies(): Promise<Currency[]> {
  const { data } = await getRequest<AllResponse<Currency>>(BASE_URL);
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