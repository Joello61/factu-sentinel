import { VerifyEmailView } from "./VerifyEmailView";

/**
 * `params` est une Promise depuis Next.js 15 (voir customers/[id]/page.tsx pour la même
 * justification) : Server Component pour pouvoir l'attendre directement, puis passe un
 * userId: string simple à VerifyEmailView (client component, seul à lire les query params
 * signés via useSearchParams()).
 */
export default async function VerifyEmailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <VerifyEmailView userId={id} />;
}
