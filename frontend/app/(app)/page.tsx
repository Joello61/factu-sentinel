/**
 * Phase 1 - Technical Foundation (docs/12-roadmap.md) : contenu vide, l'App Shell
 * (Header + Sidebar) est fourni par app/(app)/layout.tsx, désormais protégé par
 * l'authentification (Phase 2). Ce placeholder sera remplacé, pas complété sur place.
 */
export default function Home() {
  return (
    <>
      <h1 className="text-2xl font-semibold text-foreground">Bienvenue sur FactuSentinel</h1>
      <p className="mt-2 max-w-prose text-muted-foreground">
        Cette page sera remplacée par le diagnostic d’éligibilité une fois la configuration
        de l’entreprise disponible.
      </p>
    </>
  );
}
