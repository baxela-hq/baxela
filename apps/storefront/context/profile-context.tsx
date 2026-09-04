"use client";

import { createContext, useContext, type ReactNode } from "react";
import type { ApiProfile } from "@/lib/api/types";

// The profile layout fetches the user profile once and shares it with the
// tab pages (personal information edits it, notifications read from it) so
// navigating between tabs doesn't refetch.

export interface ProfileContextValue {
  profile: ApiProfile | null;
  setProfile: (profile: ApiProfile) => void;
}

const ProfileContext = createContext<ProfileContextValue | null>(null);

export function ProfileProvider({
  value,
  children,
}: {
  value: ProfileContextValue;
  children: ReactNode;
}) {
  return (
    <ProfileContext.Provider value={value}>{children}</ProfileContext.Provider>
  );
}

export function useProfile(): ProfileContextValue {
  const context = useContext(ProfileContext);
  if (!context) {
    throw new Error("useProfile must be used within a ProfileProvider");
  }
  return context;
}
