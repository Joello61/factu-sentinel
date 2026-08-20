'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequestPaginated } from '@/lib/api/client';
import { ComplianceResultBadge } from '@/components/ui/ComplianceResultBadge';
import { formatTimestamp } from '@/lib/format/date';
import type { ComplianceAnalysisHistoryItem, PaginationMeta } from '@/lib/api/types';

const PER_PAGE = 20;

type ViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; items: ComplianceAnalysisHistoryItem[]; pagination: PaginationMeta };

/**
 * Historique organisation-wide (docs/11-frontend-design-system.md, section 59 ;
 * US-HISTORY-001) : toutes les analyses, anciennes et nouvelles, jamais écrasées
 * (US-COMPLIANCE-006) -- contrairement à la page facture, qui n'affiche jamais que la
 * dernière analyse.
 */
export function ComplianceAnalysisHistory() {
  const [page, setPage] = useState(1);
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    // Jamais de setState() synchrone en tête d'effet (react-hooks/set-state-in-effect) : le
    // changement de page garde l'affichage courant jusqu'à ce que la nouvelle page soit
    // chargée, plutôt qu'un retour visuel à "Chargement…" à chaque clic -- cohérent avec
    // docs/11-frontend-design-system.md, section 36 (pas de flash de chargement générique).
    (async () => {
      try {
        const { data, meta } = await apiRequestPaginated<ComplianceAnalysisHistoryItem>(
          `/api/v1/compliance-analyses?page=${page}&per_page=${PER_PAGE}`,
        );
        if (!cancelled) {
          setState({ status: 'ready', items: data, pagination: meta.pagination });
        }
      } catch {
        if (!cancelled) {
          setState({
            status: 'error',
            message: "Impossible de charger l'historique des analyses pour le moment.",
          });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [page]);

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-foreground">Historique</h1>

      {state.status === 'loading' ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === 'error' ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === 'ready' && 0 === state.items.length ? (
        <p className="text-sm text-muted-foreground">Aucune analyse de conformité effectuée pour le moment.</p>
      ) : null}

      {state.status === 'ready' && state.items.length > 0 ? (
        <>
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">N° facture</th>
                  <th className="px-4 py-2 font-medium">Date</th>
                  <th className="px-4 py-2 font-medium">Résultat</th>
                  <th className="px-4 py-2 font-medium" aria-hidden="true" />
                </tr>
              </thead>
              <tbody>
                {state.items.map((item) => (
                  <tr key={item.id} className="border-t border-border">
                    <td className="px-4 py-2 text-foreground">{item.invoice_number ?? '-'}</td>
                    <td className="px-4 py-2 text-muted-foreground">{formatTimestamp(item.triggered_at)}</td>
                    <td className="px-4 py-2">
                      {item.global_result ? <ComplianceResultBadge result={item.global_result} /> : null}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link href={`/history/${item.id}`} className="text-sm font-medium text-primary hover:underline">
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
