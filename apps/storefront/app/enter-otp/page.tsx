import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { LockIcon } from "@/components/ui/icons";

export const metadata = { title: "Verify Your Identity — Baxela Storefront" };

export default function EnterOtpPage() {
  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground">Verify Your Identity</h1>
        <p className="mt-3 text-center text-sm text-secondary-text">
          Please enter the verification code sent to your email address.
        </p>
        <form className="mt-10 flex w-full flex-col gap-6" action="/login" method="post">
          <Input label="Verification Code" placeholder="Enter the code" icon={<LockIcon />} />
          <button
            type="button"
            className="-mt-2 text-sm font-medium text-accent hover:underline"
          >
            Resend Code
          </button>
          <Button type="submit">Verify</Button>
        </form>
      </div>
    </AuthShell>
  );
}
