"use client";

import "./globals.css";

/**
 * Filet de secours pour une erreur non interceptée dans la racine (erreur technique,
 * jamais un résultat de conformité — docs/11-frontend-design-system.md, section 37). Sans
 * ce fichier, Next.js 16.3.1 génère sa propre page /_global-error qui échoue au build
 * ("Cannot read properties of null (reading 'useContext')", bug connu, vecteur habituel :
 * un Context Provider comme AuthProvider dans app/layout.tsx, jamais monté quand
 * global-error remplace la racine — vercel/next.js#86178, #84994, #95741, reproduit ici
 * hors de toute dépendance à AuthProvider avant d'être corrigé).
 *
 * Doit définir ses propres balises html/body et ne bénéficie pas des styles globaux par
 * défaut (doc officielle Next.js, error.md "Good to know") : globals.css est donc réimporté
 * explicitement ci-dessus.
 */
export default function GlobalError({ retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <html lang="fr">
      <body className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
        <h1 className="text-xl font-semibold text-foreground">Une erreur inattendue est survenue</h1>
        <p className="max-w-prose text-sm text-muted-foreground">
          Ce n&rsquo;est pas un résultat de conformité, mais un problème technique de
          l&rsquo;application. Vous pouvez réessayer ; si le problème persiste, rechargez la
          page.
        </p>
        <button
          type="button"
          onClick={() => retry()}
          className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
        >
          Réessayer
        </button>
      </body>
    </html>
  );
}
