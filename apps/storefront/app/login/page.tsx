import Link from "next/link";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { LockIcon, MailIcon } from "@/components/ui/icons";

export const metadata = { title: "Login — Baxela Storefront" };

export default function LoginPage() {
  return (
    <AuthShell image="/images/auth-photo.jpg">
      <h1 className="text-2xl font-semibold text-foreground">Login Details</h1>
      <form className="mt-10 flex flex-col gap-6" action="/login" method="post">
        <Input type="email" label="Email" placeholder="Enter your email" icon={<MailIcon />} />
        <Input type="password" label="Password" placeholder="Enter your password" icon={<LockIcon />} />
        <div className="flex items-center justify-between">
          <Checkbox label="Remember me" />
          <Link
            href="/forgot-password"
            className="text-sm text-secondary-text underline underline-offset-2 hover:text-foreground"
          >
            Forgot Password?
          </Link>
        </div>
        <Button type="submit">Login</Button>
      </form>
      <p className="mt-8 text-center text-sm text-secondary-text">
        Don&apos;t have an account?{" "}
        <Link href="/signup" className="font-medium text-accent hover:underline">
          Sign up
        </Link>
      </p>
    </AuthShell>
  );
}
