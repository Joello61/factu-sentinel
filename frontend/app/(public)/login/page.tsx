import { LoginForm } from "./LoginForm";

interface LoginPageProps {
  searchParams: Promise<{ registered?: string }>;
}

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const { registered } = await searchParams;

  return <LoginForm justRegistered={registered === "1"} />;
}
