"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiRequest } from "@/lib/api/client";
import type { EmailVerificationResult } from "@/lib/api/types";
import { toFormErrors } from "@/lib/forms/api-error";

type VerificationState = "verifying" | "verified" | "failed";

/**
 * docs/08-api-specification.md, section 7 : le lien envoyé par email pointe vers
 * {FRONTEND_URL}/verify-email/{userId}?{query signée symfonycasts/verify-email-bundle} ;
 * cette page relaie ces paramètres tels quels vers GET /api/v1/auth/verify-email/{userId} -
 * jamais reconstruits champ par champ, pour rester compatible avec tout paramètre que le
 * bundle ajouterait sans que ce composant ait besoin d'être mis à jour.
 */
export function VerifyEmailView({ userId }: { userId: string }) {
  const [state, setState] = useState<VerificationState>("verifying");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const query = window.location.search;
      try {
        await apiRequest<EmailVerificationResult>(`/api/v1/auth/verify-email/${userId}${query}`, {
          method: "GET",
          auth: false,
        });
        if (!cancelled) {
          setState("verified");
        }
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(toFormErrors(error, "Lien de vérification invalide ou expiré.").formError);
          setState("failed");
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [userId]);

  if (state === "verifying") {
    return (
      <div className="flex flex-col gap-2 text-center">
        <h1 className="text-xl font-semibold text-foreground">Vérification en cours</h1>
        <p role="status" className="text-sm text-muted-foreground">
          Confirmation de votre adresse email...
        </p>
      </div>
    );
  }

  if (state === "verified") {
    return (
      <div className="flex flex-col gap-6 text-center">
        <h1 className="text-xl font-semibold text-foreground">Email vérifié</h1>
        <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
          Votre adresse email est confirmée. Vous pouvez maintenant vous connecter.
        </p>
        <Link
          href="/login?verified=1"
          className="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
          Se connecter
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6 text-center">
      <h1 className="text-xl font-semibold text-foreground">Vérification impossible</h1>
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {errorMessage}
      </p>
      <p className="text-sm text-muted-foreground">
        <Link href="/login" className="font-medium text-primary hover:underline">
          Retourner à la connexion
        </Link>
      </p>
    </div>
  );
}
