import { getRequest } from "@/shared/lib/api-client";
import type { Language } from "@/shared/types/locale.types";
import type { AllResponse } from "@/shared/types/common.types";

const BASE_URL = "core/admin/languages";

export async function fetchLanguages(): Promise<Language[]> {
  const { data } = await getRequest<AllResponse<Language>>(BASE_URL);
  return data as Language[];
}