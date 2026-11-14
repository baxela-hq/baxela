"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Script from "next/script";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth, type GoogleSignInResult } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";

const GIS_SCRIPT_SRC = "https://accounts.google.com/gsi/client";
const GIS_NONCE_ENDPOINT = "/auth/public/auth/google/nonce";

interface GoogleSignInButtonProps {
  /** Called on success only — the page owns toasts for success + navigation. */
  onSuccess: (result: GoogleSignInResult) => void;
  /** Rendered for the signup surface ("Sign up" instead of "Sign in"). */
  text?: "signin_with" | "signup_with";
}

/**
 * The official Google button (GIS), owning its whole lifecycle:
 *
 * - A single-use nonce is fetched from the backend and passed to
 *   `initialize()` — the returned ID token carries it and the backend
 *   consumes it atomically (replay protection).
 * - The nonce is bound to this browser attempt: it lives in a ref and is
 *   CLEARED ON EVERY TERMINAL CALLBACK PATH (success, failure, cancel),
 *   so it is never reused across prompts; a failed attempt re-initializes
 *   with a fresh nonce (a stale nonce would be rejected server-side).
 * - Strict-Mode safe: refs guard against double initialization/rendering,
 *   and unmount resets them; at most one callback runs at a time.
 * - Script-loading failures, a disabled feature, or an unconfigured client
 *   id hide the button silently (the backend flag remains the real
 *   control) — never an alarming error.
 */
export function GoogleSignInButton({ onSuccess, text = "signin_with" }: GoogleSignInButtonProps) {
  const clientId = process.env.NEXT_PUBLIC_GOOGLE_CLIENT_ID;
  const featureEnabled = process.env.NEXT_PUBLIC_AUTH_GOOGLE_ENABLED === "true";
  const enabled = Boolean(clientId) && featureEnabled;

  const tCommon = useTranslations("shared.common");
  const locale = useLocale();
  const { signInWithGoogle } = useAuth();

  const containerRef = useRef<HTMLDivElement | null>(null);
  const initializedRef = useRef(false);
  const inFlightRef = useRef(false);
  const [scriptReady, setScriptReady] = useState(false);
  const [scriptFailed, setScriptFailed] = useState(false);
  const [nonce, setNonce] = useState<string | null>(null);

  // A fresh nonce per attempt: fetched on mount and again whenever the
  // previous one was burned (any terminal callback clears it).
  useEffect(() => {
    if (!enabled || !scriptReady || nonce !== null) return;

    let active = true;

    void (async () => {
      try {
        const { nonce: next } = await api.post<{ nonce: string }>(GIS_NONCE_ENDPOINT);
        if (active) setNonce(next);
      } catch {
        // Backend flag off, rate-limited, or Redis unavailable — the
        // backend is the security control; hide the button, stay silent.
        if (active) setScriptFailed(true);
      }
    })();

    return () => {
      active = false;
    };
  }, [enabled, scriptReady, nonce]);

  const handleCredential = useCallback(
    async (response: { credential?: string }) => {
      if (inFlightRef.current) return;

      // Terminal path: the nonce is burned regardless of the outcome.
      setNonce(null);
      initializedRef.current = false;

      const credential = response.credential;
      if (!credential) return; // cancelled or malformed — silent

      inFlightRef.current = true;
      try {
        const result = await signInWithGoogle(credential);
        onSuccess(result);
      } catch (cause) {
        let mustRefreshNonce = false;

        if (cause instanceof ApiError) {
          switch (cause.code) {
            case "auth.google.email_exists":
              toast.error(tCommon("messages.error.google_email_exists"));
              break;
            case "auth.google.nonce_invalid":
              // The nonce aged out (or was burned) — refresh and retry.
              toast.error(tCommon("messages.error.google_nonce_invalid"));
              mustRefreshNonce = true;
              break;
            case "auth.google.admin_forbidden":
            case "auth.google.disabled":
            case "auth.google.unavailable":
              toast.error(tCommon("messages.error.google_admin_forbidden"));
              break;
            default:
              // Backend messages are already localized and non-leaky.
              toast.error(cause.message);
          }
        } else {
          toast.error(tCommon("messages.error.general"));
        }

        if (mustRefreshNonce) {
          // Effect refetches when nonce is null; re-render below.
          setNonce(null);
        }
      } finally {
        inFlightRef.current = false;
      }
    },
    [signInWithGoogle, onSuccess, tCommon],
  );

  const initializeAndRender = useCallback(() => {
    if (!enabled || !scriptReady || nonce === null || !clientId) return;
    if (initializedRef.current || !containerRef.current || !window.google) return;

    window.google.accounts.id.initialize({
      client_id: clientId,
      nonce,
      callback: (response) => {
        void handleCredential(response);
      },
      error_callback: () => {
        // Prompt-level failures (popup blocked, FedCM errors) are silent;
        // the user can simply click the button again.
      },
    });

    // Re-render into a clean container so a stale nonce can never be
    // reused by an already-initialized button.
    containerRef.current.innerHTML = "";
    window.google.accounts.id.renderButton(containerRef.current, {
      type: "standard",
      theme: "outline",
      size: "large",
      text,
      shape: "pill",
      logo_alignment: "center",
      width: 320,
      locale,
    });
    initializedRef.current = true;
  }, [enabled, scriptReady, nonce, clientId, handleCredential, text, locale]);

  useEffect(() => {
    initializeAndRender();
  }, [initializeAndRender]);

  useEffect(() => {
    return () => {
      // Unmount (including Strict Mode remounts): forget the state so the
      // next mount initializes cleanly.
      initializedRef.current = false;
      inFlightRef.current = false;
      window.google?.accounts.id.disableAutoSelect();
    };
  }, []);

  if (!enabled || scriptFailed) return null;

  return (
    <>
      <Script
        src={GIS_SCRIPT_SRC}
        strategy="afterInteractive"
        onReady={() => setScriptReady(true)}
        onError={() => setScriptFailed(true)}
      />
      <div className="mt-6 flex w-full flex-col items-center">
        <div className="flex w-full items-center gap-3" aria-hidden="true">
          <span className="h-px flex-1 bg-border-light" />
          <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {tCommon("messages.info.or")}
          </span>
          <span className="h-px flex-1 bg-border-light" />
        </div>
        <div ref={containerRef} className="mt-4 flex min-h-10 w-full justify-center" />
      </div>
    </>
  );
}
