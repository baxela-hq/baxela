import type { ReactNode } from "react";
import { Logo } from "@/components/ui/logo";

export function AuthShell({
  image,
  children,
}: {
  image?: string;
  children: ReactNode;
}) {
  return (
    <main className="flex min-h-screen w-full">
      {image ? (
        <div
          className="relative hidden w-[58.7%] shrink-0 bg-cover bg-center lg:block"
          style={{ backgroundImage: `url(${image})` }}
        >
          <div className="absolute start-[60px] top-[60px]">
            <Logo variant="light" />
          </div>
        </div>
      ) : null}
      <div className="flex flex-1 items-center justify-center bg-white px-6 py-16">
        <div className="w-full max-w-[445px]">{children}</div>
      </div>
    </main>
  );
}
