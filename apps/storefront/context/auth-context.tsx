"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { api } from "@/lib/api/client";
import type { ApiUser } from "@/lib/api/types";

// Sanctum bearer-token session. The backend signs in via
// POST /auth/public/auth/signin and returns a plain-text token; there is
// no signout endpoint (tokens are revoked server-side only), so signing
// out just drops the local session.

const TOKEN_KEY = "baxela_token";
const USER_KEY = "baxela_user";

type AuthStatus = "loading" | "authenticated" | "unauthenticated";

interface SignInInput {
  email: string;
  password: string;
}

interface AuthContextValue {
  status: AuthStatus;
  user: ApiUser | null;
  token: string | null;
  signIn: (input: SignInInput) => Promise<ApiUser>;
  signOut: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [status, setStatus] = useState<AuthStatus>("loading");

  const clearSession = useCallback(() => {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(USER_KEY);
    setToken(null);
    setUser(null);
    setStatus("unauthenticated");
  }, []);

  useEffect(() => {
    let active = true;

    // The async IIFE keeps every setState off the synchronous effect path
    // (the leading await yields first); `active` ignores results after
    // unmount.
    void (async () => {
      await Promise.resolve();

      const stored = window.localStorage.getItem(TOKEN_KEY);
      if (!stored) {
        if (active) setStatus("unauthenticated");
        return;
      }

      try {
        const fresh = await api.get<ApiUser>("/auth/user/account/me", {
          token: stored,
        });
        if (!active) return;
        window.localStorage.setItem(USER_KEY, JSON.stringify(fresh));
        setToken(stored);
        setUser(fresh);
        setStatus("authenticated");
      } catch {
        if (!active) return;
        window.localStorage.removeItem(TOKEN_KEY);
        window.localStorage.removeItem(USER_KEY);
        setToken(null);
        setUser(null);
        setStatus("unauthenticated");
      }
    })();

    return () => {
      active = false;
    };
  }, []);

  const signIn = useCallback(async ({ email, password }: SignInInput) => {
    const { token: nextToken, user: nextUser } = await api.post<
      { token: string; user: ApiUser }
    >("/auth/public/auth/signin", { email, password });

    window.localStorage.setItem(TOKEN_KEY, nextToken);
    window.localStorage.setItem(USER_KEY, JSON.stringify(nextUser));
    setToken(nextToken);
    setUser(nextUser);
    setStatus("authenticated");

    return nextUser;
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, token, signIn, signOut: clearSession }),
    [status, user, token, signIn, clearSession],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
