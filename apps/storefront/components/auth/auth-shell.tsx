import type { ReactNode } from "react";

export function AuthShell({ children }: { children: ReactNode }) {
  return (
    <main className="flex min-h-screen w-full items-center justify-center bg-white px-6 py-16">
      <div className="w-full max-w-[445px]">{children}</div>
    </main>
  );
}
