import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { MailIcon } from "@/components/ui/icons";

export const metadata = { title: "Forgot Password — Baxela Storefront" };

export default function ForgotPasswordPage() {
  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground">Forgot Password?</h1>
        <p className="mt-3 text-center text-sm text-secondary-text">
          Enter your email address and we&apos;ll send you a code to reset your password.
        </p>
        <form className="mt-10 flex w-full flex-col gap-6" action="/enter-otp" method="post">
          <Input type="email" label="Email" placeholder="Enter your email" icon={<MailIcon />} />
          <Button type="submit">Send Code</Button>
        </form>
      </div>
    </AuthShell>
  );
}
