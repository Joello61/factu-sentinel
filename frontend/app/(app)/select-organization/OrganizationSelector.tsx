"use client";

import { useEffect, useState } from "react";
import { Building2 } from "lucide-react";
import { apiRequest } from "@/lib/api/client";
import { useAuth } from "@/components/auth/AuthProvider";
import type { Role, UserOrganizationMembership } from "@/lib/api/types";

const ROLE_LABELS: Record<Role, string> = {
  OWNER: "Propriétaire",
  ADMIN: "Administrateur",
  COLLABORATOR: "Collaborateur",
};

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; organizations: UserOrganizationMembership[] };

/**
 * Sélection d'organisation (plan Phase 14) : liste simple + badge de rôle par organisation
 * (docs/11-frontend-design-system.md, section 59). Une organisation active par défaut existe
 * déjà (Membership le plus ancien, docs/08-api-specification.md section 9) - cet écran est un
 * changement explicite, jamais une étape obligatoire du parcours de connexion.
 *
 * Rechargement complet de la page après sélection (jamais une navigation client seule) :
 * plusieurs composants de l'App Shell (ex. Sidebar, filtrage par rôle) chargent leurs propres
 * données une seule fois au montage et ne s'abonnent pas à un changement d'organisation en
 * cours de session - un rechargement garantit qu'ils repartent tous d'un état cohérent avec
 * la nouvelle organisation active.
 */
export function OrganizationSelector() {
  const { selectOrganization } = useAuth();
  const [state, setState] = useState<State>({ status: "loading" });
  const [switching, setSwitching] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const organizations = await apiRequest<UserOrganizationMembership[]>("/api/v1/auth/me/organizations");
        if (!cancelled) {
          setState({ status: "ready", organizations });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger vos organisations pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleSelect(organizationId: string) {
    setError(null);
    setSwitching(organizationId);
    try {
      await selectOrganization(organizationId);
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- rechargement complet volontaire (voir docblock), jamais une navigation client Next.js.
      window.location.assign("/dashboard");
    } catch {
      setError("Impossible de changer d'organisation pour le moment.");
      setSwitching(null);
    }
  }

  return (
    <div className="flex max-w-md flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Sélectionner une organisation</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Choisissez l&apos;organisation avec laquelle vous souhaitez travailler.
        </p>
      </div>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {error ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {error}
        </p>
      ) : null}

      {state.status === "ready" ? (
        <ul className="flex flex-col gap-3">
          {state.organizations.map((organization) => (
            <li key={organization.organization_id}>
              <button
                type="button"
                disabled={switching !== null}
                onClick={() => void handleSelect(organization.organization_id)}
                aria-busy={switching === organization.organization_id || undefined}
                className="flex w-full items-center justify-between gap-3 rounded-md border border-border bg-surface p-4 text-left hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <span className="flex items-center gap-3">
                  <Building2 aria-hidden="true" className="text-primary" size={20} strokeWidth={1.75} />
                  <span className="text-sm font-medium text-foreground">
                    {organization.legal_name ?? "Organisation sans nom"}
                  </span>
                </span>
                <span className="text-xs text-muted-foreground">{ROLE_LABELS[organization.role]}</span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
