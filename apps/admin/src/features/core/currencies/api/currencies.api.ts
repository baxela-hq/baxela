import { getRequest } from "@/shared/lib/api-client";
import type { Currency } from "@/shared/types/locale.types";
import type { AllResponse } from "@/shared/types/common.types";

const BASE_URL = "core/admin/currencies";

export async function fetchCurrencies(): Promise<Currency[]> {
  const { data } = await getRequest<AllResponse<Currency>>(BASE_URL);
  return data as Currency[];
}