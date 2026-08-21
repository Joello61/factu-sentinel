"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiRequest } from "@/lib/api/client";
import { useAuth } from "@/components/auth/AuthProvider";
import { toFormErrors } from "@/lib/forms/api-error";
import type { AcceptedInvitation, InvitationPreview } from "@/lib/api/types";

const ROLE_LABELS: Record<InvitationPreview["role"], string> = {
  ADMIN: "administrateur",
  COLLABORATOR: "collaborateur",
};

type State =
  | { status: "loading" }
  | { status: "invalid" }
  | { status: "preview" }
  | { status: "accepted" }
  | { status: "error"; message: string };

/**
 * Acceptation d'invitation (plan Phase 14, "Décisions à valider" #3 - endpoints non
 * documentés par la version initiale de docs/08-api-specification.md, comblés à
 * l'implémentation). Aperçu public (GET /invitations/{token}) toujours chargé en premier,
 * même pour un utilisateur déjà connecté - jamais présumé valide seulement parce qu'un token
 * est présent dans l'URL.
 */
export function InvitationAcceptanceView({ token }: { token: string }) {
  const { status: authStatus } = useAuth();
  const [state, setState] = useState<State>({ status: "loading" });
  const [preview, setPreview] = useState<InvitationPreview | null>(null);
  const [accepting, setAccepting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const result = await apiRequest<InvitationPreview>(`/api/v1/invitations/${token}`, { auth: false });
        if (!cancelled) {
          setPreview(result);
          setState({ status: "preview" });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "invalid" });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token]);

  async function handleAccept() {
    setAccepting(true);
    try {
      await apiRequest<AcceptedInvitation>(`/api/v1/invitations/${token}/accept`, { method: "POST" });
      setState({ status: "accepted" });
    } catch (error) {
      setState({
        status: "error",
        message: toFormErrors(error, "Impossible d'accepter cette invitation pour le moment.").formError ?? "Impossible d'accepter cette invitation pour le moment.",
      });
    } finally {
      setAccepting(false);
    }
  }

  if (state.status === "loading") {
    return (
      <div className="flex flex-col gap-2 text-center">
        <h1 className="text-xl font-semibold text-foreground">Invitation</h1>
        <p role="status" className="text-sm text-muted-foreground">
          Vérification de l&apos;invitation…
        </p>
      </div>
    );
  }

  if (state.status === "invalid") {
    return (
      <div className="flex flex-col gap-6 text-center">
        <h1 className="text-xl font-semibold text-foreground">Invitation introuvable</h1>
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          Ce lien d&apos;invitation n&apos;est plus valide - il a peut-être expiré ou déjà été utilisé.
        </p>
        <p className="text-sm text-muted-foreground">
          <Link href="/login" className="font-medium text-primary hover:underline">
            Retourner à la connexion
          </Link>
        </p>
      </div>
    );
  }

  if (state.status === "accepted") {
    return (
      <div className="flex flex-col gap-6 text-center">
        <h1 className="text-xl font-semibold text-foreground">Invitation acceptée</h1>
        <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
          Vous faites désormais partie de cette organisation.
        </p>
        <Link
          href="/select-organization"
          className="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
          Continuer
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6 text-center">
      <h1 className="text-xl font-semibold text-foreground">Invitation à rejoindre une organisation</h1>

      {preview ? (
        <p className="text-sm text-muted-foreground">
          <span className="font-medium text-foreground">{preview.organization_name ?? "Cette organisation"}</span>{" "}
          vous invite à rejoindre son équipe en tant que <span className="font-medium text-foreground">{ROLE_LABELS[preview.role]}</span>.
        </p>
      ) : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {authStatus === "authenticated" ? (
        <button
          type="button"
          disabled={accepting}
          onClick={() => void handleAccept()}
          aria-busy={accepting || undefined}
          className="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
        >
          Accepter l&apos;invitation
        </button>
      ) : (
        <div className="flex flex-col gap-3">
          <p className="text-sm text-muted-foreground">
            Connectez-vous ou créez un compte avec l&apos;adresse <span className="font-medium text-foreground">{preview?.email}</span>, puis revenez sur ce lien pour l&apos;accepter.
          </p>
          <Link
            href="/login"
            className="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            Se connecter
          </Link>
          <Link
            href="/register"
            className="inline-flex w-full items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            Créer un compte
          </Link>
        </div>
      )}
    </div>
  );
}
