import { InvitationAcceptanceView } from "./InvitationAcceptanceView";

/**
 * `params` est une Promise depuis Next.js 15 (voir customers/[id]/page.tsx pour la même
 * justification) : Server Component pour pouvoir l'attendre directement, puis passe un
 * token: string simple à InvitationAcceptanceView (client component).
 */
export default async function InvitationAcceptancePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return <InvitationAcceptanceView token={token} />;
}
