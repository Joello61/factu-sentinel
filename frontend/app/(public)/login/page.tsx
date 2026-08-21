import { LoginForm } from "./LoginForm";

interface LoginPageProps {
  searchParams: Promise<{ registered?: string; verified?: string }>;
}

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const { registered, verified } = await searchParams;

  return <LoginForm justRegistered={registered === "1"} justVerified={verified === "1"} />;
}
