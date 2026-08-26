"use client";

import { useEffect, useState, type FormEvent } from "react";
import { platformAdminApiRequestPaginated } from "@/lib/api/platformAdminClient";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { formatTimestamp } from "@/lib/format/date";
import type { PlatformAuditEvent } from "@/lib/api/platformAdminTypes";
import type { PaginationMeta } from "@/lib/api/types";

const PER_PAGE = 20;

const ACTOR_LABELS: Record<PlatformAuditEvent["actor"]["type"], string> = {
  USER: "Utilisateur",
  SYSTEM: "Système",
  PLATFORM_ADMIN: "Administrateur plateforme",
};

type ViewState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; items: PlatformAuditEvent[]; pagination: PaginationMeta };

/**
 * US-PLATFORMADMIN-003 (docs/08-api-specification.md, section 38.2). Jamais
 * app/(app)/history - source distincte (GET /platform-admin/audit-events, cross-tenant).
 */
export function AuditTrail() {
  const [page, setPage] = useState(1);
  const [organizationIdFilter, setOrganizationIdFilter] = useState("");
  const [appliedOrganizationId, setAppliedOrganizationId] = useState("");
  const [state, setState] = useState<ViewState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const query = new URLSearchParams({ page: String(page), per_page: String(PER_PAGE) });
        if (appliedOrganizationId) {
          query.set("organization_id", appliedOrganizationId);
        }
        const { data, meta } = await platformAdminApiRequestPaginated<PlatformAuditEvent>(
          `/api/v1/platform-admin/audit-events?${query.toString()}`,
        );
        if (!cancelled) {
          setState({ status: "ready", items: data, pagination: meta.pagination });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger le journal d'audit pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [page, appliedOrganizationId]);

  function handleFilterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setAppliedOrganizationId(organizationIdFilter.trim());
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Journal d&apos;audit</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Consultez les événements journalisés à travers toutes les organisations.
        </p>
      </div>

      <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-end gap-3">
        <div className="w-full max-w-xs">
          <FormField
            label="Filtrer par identifiant d'organisation"
            name="organization_id"
            value={organizationIdFilter}
            onChange={(event) => setOrganizationIdFilter(event.target.value)}
          />
        </div>
        <Button type="submit" variant="secondary" className="w-fit">
          Filtrer
        </Button>
      </form>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === "ready" && state.items.length === 0 ? (
        <p className="text-sm text-muted-foreground">Aucun événement pour ce filtre.</p>
      ) : null}

      {state.status === "ready" && state.items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">Événement</th>
                  <th className="px-4 py-2 font-medium">Ressource</th>
                  <th className="px-4 py-2 font-medium">Organisation</th>
                  <th className="px-4 py-2 font-medium">Acteur</th>
                  <th className="px-4 py-2 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                {state.items.map((event, index) => (
                  <tr key={`${event.entity_id}-${event.occurred_at}-${index}`} className="border-t border-border">
                    <td className="px-4 py-2 text-foreground">{event.event_type}</td>
                    <td className="px-4 py-2 text-muted-foreground">
                      {event.entity_type} · {event.entity_id}
                    </td>
                    <td className="px-4 py-2 text-muted-foreground">{event.organization_id ?? "-"}</td>
                    <td className="px-4 py-2 text-muted-foreground">{ACTOR_LABELS[event.actor.type]}</td>
                    <td className="px-4 py-2 text-muted-foreground">{formatTimestamp(event.occurred_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {state.pagination.total_pages > 1 ? (
            <div className="flex items-center justify-between">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={page <= 1}
                className="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground disabled:opacity-50"
              >
                Précédent
              </button>
              <p className="text-xs text-muted-foreground">
                Page {state.pagination.page} sur {state.pagination.total_pages}
              </p>
              <button
                type="button"
                onClick={() => setPage((current) => Math.min(state.pagination.total_pages, current + 1))}
                disabled={page >= state.pagination.total_pages}
                className="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground disabled:opacity-50"
              >
                Suivant
              </button>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
