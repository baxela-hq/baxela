import { getRequest, patchRequest } from "@/shared/lib/api-client";
import type { Language } from "@/shared/types/locale.types";
import type { AllResponse } from "@/shared/types/common.types";

const BASE_URL = "core/admin/languages";

/**
 * Active languages by default (what translation tabs consume). The
 * management page passes `all` to also list deactivated languages.
 */
export async function fetchLanguages(all = false): Promise<Language[]> {
  const { data } = await getRequest<AllResponse<Language>>(
    all ? `${BASE_URL}?all=1` : BASE_URL
  );
  return data as Language[];
}

// Partial PATCH — currently only { is_active } is toggleable; the default
// language switch lives in the store settings, not here.
export function updateLanguage(
  id: number,
  data: Partial<Pick<Language, "is_active">>
): Promise<Language> {
  return patchRequest<Language, Partial<Pick<Language, "is_active">>>(
    `${BASE_URL}/${id}`,
    data
  );
}
