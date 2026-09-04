import type { ApiProfile } from "@/lib/api/types";

// Shared display identity for the account pages: the greeting name prefers
// the profile's full name, then its display name, then the email prefix
// (the initials split also covers that prefix's dots/underscores).

export function accountIdentity(
  profile: ApiProfile | null,
  email: string | undefined,
): { displayName: string; initials: string } {
  const fullName =
    [profile?.full_name, profile?.display_name]
      .find((value) => Boolean(value?.trim()))
      ?.trim() ?? "";
  const emailPrefix = email?.split("@")[0] ?? "";
  const source = fullName || emailPrefix;

  return {
    displayName: source,
    initials: source
      .split(/[\s._-]+/)
      .map((part) => part[0])
      .join("")
      .slice(0, 2)
      .toUpperCase(),
  };
}
