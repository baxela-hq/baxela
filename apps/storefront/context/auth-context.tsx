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
import { clearCartToken, getCartToken } from "@/lib/cart/client";
import {
  AUTH_TOKEN_KEY,
  AUTH_USER_KEY,
  clearAuthStorage,
} from "@/lib/auth-storage";
import type { ApiUser } from "@/lib/api/types";

// Sanctum bearer-token session. The backend signs in via
// POST /auth/public/auth/signin and returns a plain-text token; there is
// no signout endpoint (tokens are revoked server-side only), so signing
// out just drops the local session. Storage keys live in
// lib/auth-storage, shared with the API client's 401 interceptor.

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
    clearAuthStorage();
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

      const stored = window.localStorage.getItem(AUTH_TOKEN_KEY);
      if (!stored) {
        if (active) setStatus("unauthenticated");
        return;
      }

      try {
        const fresh = await api.get<ApiUser>("/auth/user/account/me", {
          token: stored,
        });
        if (!active) return;
        window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(fresh));
        setToken(stored);
        setUser(fresh);
        setStatus("authenticated");
      } catch {
        if (!active) return;
        // A dead token 401s and the API client's interceptor has already
        // cleared storage and started the login redirect; this only syncs
        // the in-memory state (no-op by the time the reload lands).
        clearAuthStorage();
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
    // Send the guest cart token along: the backend folds the guest cart
    // into the account cart synchronously before responding.
    const { token: nextToken, user: nextUser } = await api.post<
      { token: string; user: ApiUser }
    >("/auth/public/auth/signin", { email, password }, {
      cartToken: getCartToken(),
    });

    window.localStorage.setItem(AUTH_TOKEN_KEY, nextToken);
    window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(nextUser));
    setToken(nextToken);
    setUser(nextUser);
    setStatus("authenticated");

    // Only drop the guest token once sign-in (and the server-side merge)
    // has succeeded — a failed attempt must leave the guest cart reachable.
    clearCartToken();

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
