"use client";

import { useEffect, useState } from "react";
import { AlertDialog } from "radix-ui";
import { platformAdminApiRequest, ApiError } from "@/lib/api/platformAdminClient";
import { Button } from "@/components/ui/Button";
import { formatTimestamp } from "@/lib/format/date";
import type { PlatformOrganizationDetail } from "@/lib/api/platformAdminTypes";

const ROLE_LABELS: Record<string, string> = {
  OWNER: "Propriétaire",
  ADMIN: "Administrateur",
  COLLABORATOR: "Collaborateur",
};

type ViewState =
  | { status: "loading" }
  | { status: "not_found" }
  | { status: "error"; message: string }
  | { status: "ready"; organization: PlatformOrganizationDetail };

/**
 * US-PLATFORMADMIN-001/002 (docs/08-api-specification.md, section 38.2). Suspendre/réactiver
 * est une action sensible (Confirmation Pattern, docs/11-frontend-design-system.md ligne
 * 641) - même patron AlertDialog que app/(app)/team/TeamManagement.tsx.
 */
export function OrganizationDetail({ organizationId }: { organizationId: string }) {
  const [state, setState] = useState<ViewState>({ status: "loading" });
  const [confirming, setConfirming] = useState<"suspend" | "reactivate" | null>(null);
  const [acting, setActing] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const organization = await platformAdminApiRequest<PlatformOrganizationDetail>(
          `/api/v1/platform-admin/organizations/${organizationId}`,
        );
        if (!cancelled) {
          setState({ status: "ready", organization });
        }
      } catch (error) {
        if (cancelled) {
          return;
        }
        if (error instanceof ApiError && error.status === 404) {
          setState({ status: "not_found" });
        } else {
          setState({ status: "error", message: "Impossible de charger cette organisation pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [organizationId]);

  async function handleConfirmAction() {
    if (state.status !== "ready" || !confirming) {
      return;
    }
    setActing(true);
    setActionError(null);
    try {
      const updated = await platformAdminApiRequest<PlatformOrganizationDetail>(
        `/api/v1/platform-admin/organizations/${organizationId}/${confirming}`,
        { method: "POST" },
      );
      setState({ status: "ready", organization: { ...state.organization, ...updated } });
      setConfirming(null);
    } catch {
      setActionError(
        confirming === "suspend"
          ? "Impossible de suspendre cette organisation pour le moment."
          : "Impossible de réactiver cette organisation pour le moment.",
      );
    } finally {
      setActing(false);
    }
  }

  if (state.status === "loading") {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === "not_found") {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        Cette organisation n&apos;existe pas.
      </p>
    );
  }

  if (state.status === "error") {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {state.message}
      </p>
    );
  }

  const { organization } = state;
  const suspended = organization.suspended_at !== null;

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">
            {organization.legal_name ?? "Organisation non configurée"}
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            SIREN {organization.siren ?? "non renseigné"} · Créée le {formatTimestamp(organization.created_at)}
          </p>
        </div>

        {suspended ? (
          <Button type="button" className="w-fit" onClick={() => setConfirming("reactivate")}>
            Réactiver
          </Button>
        ) : (
          <Button type="button" variant="destructive" className="w-fit" onClick={() => setConfirming("suspend")}>
            Suspendre
          </Button>
        )}
      </div>

      {suspended ? (
        <p role="status" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          Cette organisation est suspendue depuis le {formatTimestamp(organization.suspended_at as string)}. Tous
          ses membres ont perdu l&apos;accès applicatif.
        </p>
      ) : null}

      <section className="flex flex-col gap-4">
        <h2 className="text-lg font-medium text-foreground">Membres</h2>

        {organization.members.length === 0 ? (
          <p className="text-sm text-muted-foreground">Aucun membre.</p>
        ) : (
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">Email</th>
                  <th className="px-4 py-2 font-medium">Rôle</th>
                </tr>
              </thead>
              <tbody>
                {organization.members.map((member) => (
                  <tr key={member.user_id} className="border-t border-border">
                    <td className="px-4 py-2 text-foreground">{member.email}</td>
                    <td className="px-4 py-2 text-foreground">{ROLE_LABELS[member.role] ?? member.role}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <AlertDialog.Root open={confirming !== null} onOpenChange={(open) => !open && setConfirming(null)}>
        <AlertDialog.Portal>
          <AlertDialog.Overlay className="fixed inset-0 bg-black/50" />
          <AlertDialog.Content className="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border border-border bg-surface p-6 shadow-lg">
            <AlertDialog.Title className="text-lg font-semibold text-foreground">
              {confirming === "suspend" ? "Suspendre cette organisation ?" : "Réactiver cette organisation ?"}
            </AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
              {confirming === "suspend"
                ? "Tous les membres perdront immédiatement l'accès applicatif. Les données restent conservées."
                : "Tous les membres retrouveront immédiatement l'accès applicatif."}
            </AlertDialog.Description>

            {actionError ? (
              <p role="alert" className="mt-4 rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
                {actionError}
              </p>
            ) : null}

            <div className="mt-6 flex justify-end gap-3">
              <AlertDialog.Cancel asChild>
                <Button type="button" variant="secondary" className="w-fit">
                  Annuler
                </Button>
              </AlertDialog.Cancel>
              <Button
                type="button"
                variant={confirming === "suspend" ? "destructive" : "primary"}
                className="w-fit"
                loading={acting}
                onClick={() => {
                  void handleConfirmAction();
                }}
              >
                {confirming === "suspend" ? "Suspendre définitivement" : "Réactiver"}
              </Button>
            </div>
          </AlertDialog.Content>
        </AlertDialog.Portal>
      </AlertDialog.Root>
    </div>
  );
}
