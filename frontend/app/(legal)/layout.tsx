import type { ReactNode } from "react";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";

/**
 * Pages légales (mentions légales, CGU, politique de confidentialité) - jamais l'App Shell
 * (réservé à l'UI authentifiée), un simple conteneur de lecture large, cohérent avec
 * `docs/11-frontend-design-system.md` section 207 ("pages légales" listées dans
 * l'inventaire public). Toujours accessible sans authentification.
 */
export default function LegalLayout({ children }: { children: ReactNode }) {
  return (
    <div className="mx-auto flex min-h-full w-full max-w-3xl flex-1 flex-col gap-8 px-6 py-10">
      <Link href="/" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
        <ArrowLeft aria-hidden="true" size={14} strokeWidth={2} />
        Retour à FactuSentinel
      </Link>
      <article className="flex flex-col gap-8 text-sm leading-relaxed text-foreground [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:mt-4 [&_h3]:text-base [&_h3]:font-medium [&_p]:text-muted-foreground [&_li]:text-muted-foreground [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-1 [&_ol]:flex [&_ol]:flex-col [&_ol]:gap-1 [&_strong]:text-foreground [&_a]:text-primary [&_a]:underline">
        {children}
      </article>
      <nav className="flex flex-wrap gap-4 border-t border-border pt-6 text-xs text-muted-foreground">
        <Link href="/mentions-legales" className="hover:text-foreground hover:underline">
          Mentions légales
        </Link>
        <Link href="/cgu" className="hover:text-foreground hover:underline">
          Conditions générales d&apos;utilisation
        </Link>
        <Link href="/politique-de-confidentialite" className="hover:text-foreground hover:underline">
          Politique de confidentialité
        </Link>
      </nav>
    </div>
  );
}
