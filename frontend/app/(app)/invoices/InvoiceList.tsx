'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequestPaginated } from '@/lib/api/client';
import { InvoiceStatusBadge } from '@/components/ui/InvoiceStatusBadge';
import { formatBusinessDate } from '@/lib/format/date';
import { formatAmount } from '@/lib/format/amount';
import type { Invoice } from '@/lib/api/types';

type ViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; invoices: Invoice[] };

/**
 * Liste des factures (docs/11-frontend-design-system.md, section 32) : client, date, statut
 * d'analyse en priorité - pas de colonne de résultat de conformité sur cette liste (réservée
 * au Dashboard/Historique, Phase 9, docs/11-frontend-design-system.md section 59) : le
 * résultat détaillé se consulte sur la page de détail de chaque facture.
 */
export function InvoiceList() {
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data } = await apiRequestPaginated<Invoice>(
          '/api/v1/invoices?per_page=50',
        );
        if (!cancelled) {
          setState({ status: 'ready', invoices: data });
        }
      } catch {
        if (!cancelled) {
          setState({
            status: 'error',
            message:
              'Impossible de charger la liste des factures pour le moment.',
          });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-foreground">Factures</h1>
        <Link
          href="/invoices/new"
          className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
        >
          Nouvelle facture
        </Link>
      </div>

      {state.status === 'loading' ? (
        <p className="text-sm text-muted-foreground">Chargement…</p>
      ) : null}

      {state.status === 'error' ? (
        <p
          role="alert"
          className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error"
        >
          {state.message}
        </p>
      ) : null}

      {state.status === 'ready' && 0 === state.invoices.length ? (
        <p className="text-sm text-muted-foreground">
          Aucune facture enregistrée pour le moment.
        </p>
      ) : null}

      {state.status === 'ready' && state.invoices.length > 0 ? (
        <>
          {/* Desktop/tablette (docs/11-frontend-design-system.md, section 24) - jamais de
              défilement horizontal illisible par défaut sur mobile, voir la liste de cartes
              ci-dessous. */}
          <div className="hidden overflow-x-auto rounded-md border border-border sm:block">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">N°</th>
                  <th className="px-4 py-2 font-medium">Date</th>
                  <th className="px-4 py-2 font-medium">Total TTC</th>
                  <th className="px-4 py-2 font-medium">Statut</th>
                  <th className="px-4 py-2 font-medium" aria-hidden="true" />
                </tr>
              </thead>
              <tbody>
                {state.invoices.map((invoice) => (
                  <tr key={invoice.id} className="border-t border-border">
                    <td className="px-4 py-2 text-foreground">
                      {invoice.invoice_number ?? '-'}
                    </td>
                    <td className="px-4 py-2 text-muted-foreground">
                      {formatBusinessDate(invoice.issue_date)}
                    </td>
                    <td className="px-4 py-2 tabular-nums text-foreground">
                      {formatAmount(invoice.total_amount_ttc, invoice.currency)}
                    </td>
                    <td className="px-4 py-2">
                      <InvoiceStatusBadge status={invoice.status} />
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link
                        href={`/invoices/${invoice.id}`}
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

          {/* Mobile (section 24) : une carte par facture, colonnes prioritaires (numéro,
              statut, montant) - toute la carte reste cliquable, cohérent avec "la ligne elle-même
              doit rester cliquable pour la consultation". */}
          <ul className="flex flex-col gap-3 sm:hidden">
            {state.invoices.map((invoice) => (
              <li key={invoice.id}>
                <Link
                  href={`/invoices/${invoice.id}`}
                  className="flex flex-col gap-2 rounded-md border border-border p-4 hover:bg-primary/5"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium text-foreground">
                      {invoice.invoice_number ?? 'Facture sans numéro'}
                    </span>
                    <InvoiceStatusBadge status={invoice.status} />
                  </div>
                  <div className="flex items-center justify-between gap-2 text-sm">
                    <span className="text-muted-foreground">{formatBusinessDate(invoice.issue_date)}</span>
                    <span className="tabular-nums text-foreground">
                      {formatAmount(invoice.total_amount_ttc, invoice.currency)}
                    </span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </div>
  );
}
