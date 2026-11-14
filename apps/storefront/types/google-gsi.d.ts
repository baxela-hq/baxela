/**
 * Minimal typing for the Google Identity Services JS SDK surface the
 * sign-in button uses. Only the fields we actually pass are typed — the
 * full IdConfiguration/renderButton options live in Google's JS reference.
 */
interface GoogleIdApiConfig {
  client_id: string;
  callback: (response: { credential?: string }) => void;
  /** Replay protection: consumed server-side against the issued nonce. */
  nonce?: string;
  auto_select?: boolean;
  error_callback?: (error: { type?: string; message?: string }) => void;
}

interface GoogleIdApi {
  initialize: (config: GoogleIdApiConfig) => void;
  renderButton: (
    parent: HTMLElement,
    options: {
      type?: "standard";
      theme?: "outline" | "filled_blue" | "filled_black";
      size?: "large" | "medium" | "small";
      text?: "signin_with" | "signup_with" | "continue_with";
      shape?: "rectangular" | "pill" | "circle" | "square";
      logo_alignment?: "left" | "center";
      width?: number;
      locale?: string;
    },
  ) => void;
  disableAutoSelect: () => void;
}

declare global {
  interface Window {
    google?: {
      accounts: {
        id: GoogleIdApi;
      };
    };
  }
}

export {};
