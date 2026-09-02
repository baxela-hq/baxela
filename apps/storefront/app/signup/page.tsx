import Link from "next/link";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LockIcon, MailIcon, UserIcon } from "@/components/ui/icons";

export const metadata = { title: "Signup — Baxela Storefront" };

export default function SignupPage() {
  return (
    <AuthShell image="/images/auth-photo.jpg">
      <h1 className="text-2xl font-semibold text-foreground">Signup Details</h1>
      <form className="mt-10 flex flex-col gap-6" action="/signup" method="post">
        <Input label="Name" placeholder="Enter your name" icon={<UserIcon />} />
        <Input type="email" label="Email" placeholder="Enter your email" icon={<MailIcon />} />
        <Input type="password" label="Password" placeholder="Enter your password" icon={<LockIcon />} />
        <Input type="password" label="Confirm Password" placeholder="Confirm your password" icon={<LockIcon />} />
        <Button type="submit">Signup</Button>
      </form>
      <p className="mt-6 text-center text-sm text-secondary-text">
        By signing up you agree to our{" "}
        <Link href="/" className="text-accent hover:underline">
          Terms of Service
        </Link>{" "}
        and{" "}
        <Link href="/" className="text-accent hover:underline">
          Privacy Policy
        </Link>
      </p>
      <p className="mt-6 text-center text-sm text-secondary-text">
        Already have an account?{" "}
        <Link href="/login" className="font-medium text-accent hover:underline">
          Login
        </Link>
      </p>
    </AuthShell>
  );
}
