"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { platformAdminApiRequestPaginated } from "@/lib/api/platformAdminClient";
import { formatTimestamp } from "@/lib/format/date";
import type { PlatformOrganization } from "@/lib/api/platformAdminTypes";
import type { PaginationMeta } from "@/lib/api/types";

const PER_PAGE = 20;

type ViewState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; items: PlatformOrganization[]; pagination: PaginationMeta };

/** US-PLATFORMADMIN-001 (docs/11-frontend-design-system.md, ligne 641). */
export function OrganizationsList() {
  const [page, setPage] = useState(1);
  const [state, setState] = useState<ViewState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data, meta } = await platformAdminApiRequestPaginated<PlatformOrganization>(
          `/api/v1/platform-admin/organizations?page=${page}&per_page=${PER_PAGE}`,
        );
        if (!cancelled) {
          setState({ status: "ready", items: data, pagination: meta.pagination });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger les organisations pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [page]);

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Organisations</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Consultez et gérez l&apos;ensemble des organisations de la plateforme.
        </p>
      </div>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === "ready" && state.items.length === 0 ? (
        <p className="text-sm text-muted-foreground">Aucune organisation pour le moment.</p>
      ) : null}

      {state.status === "ready" && state.items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">Raison sociale</th>
                  <th className="px-4 py-2 font-medium">SIREN</th>
                  <th className="px-4 py-2 font-medium">Créée le</th>
                  <th className="px-4 py-2 font-medium">Statut</th>
                  <th className="px-4 py-2 font-medium" aria-hidden="true" />
                </tr>
              </thead>
              <tbody>
                {state.items.map((organization) => (
                  <tr key={organization.id} className="border-t border-border">
                    <td className="px-4 py-2 text-foreground">
                      {organization.legal_name ?? <span className="text-muted-foreground">Non configurée</span>}
                    </td>
                    <td className="px-4 py-2 text-muted-foreground">{organization.siren ?? "-"}</td>
                    <td className="px-4 py-2 text-muted-foreground">{formatTimestamp(organization.created_at)}</td>
                    <td className="px-4 py-2">
                      {organization.suspended_at ? (
                        <span className="rounded-full border border-error bg-error/10 px-2 py-0.5 text-xs font-medium text-error">
                          Suspendue
                        </span>
                      ) : (
                        <span className="rounded-full border border-success bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                          Active
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link
                        href={`/platform-admin/organizations/${organization.id}`}
                        className="text-sm font-medium text-primary hover:underline"
                      >
                        Consulter
                      </Link>
                    </td>
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
