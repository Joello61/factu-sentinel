'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequestPaginated } from '@/lib/api/client';
import type { Customer, CustomerType } from '@/lib/api/types';

const CUSTOMER_TYPE_LABELS: Record<CustomerType, string> = {
  PROFESSIONNEL_FRANCAIS: 'Professionnel français',
  PARTICULIER: 'Particulier',
  PROFESSIONNEL_ETRANGER: 'Professionnel étranger',
};

type ViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; customers: Customer[] };

/**
 * Liste des clients (US-CUSTOMER-001, docs/11-frontend-design-system.md, section 24) :
 * tableau desktop / cartes mobile. Le type de client est toujours affiché de façon visible
 * (../../../CLAUDE.md frontend section 8 : jamais caché dans un détail secondaire), car il
 * détermine les règles de conformité applicables.
 */
export function CustomerList() {
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data } = await apiRequestPaginated<Customer>(
          '/api/v1/customers?per_page=50',
        );
        if (!cancelled) {
          setState({ status: 'ready', customers: data });
        }
      } catch {
        if (!cancelled) {
          setState({
            status: 'error',
            message:
              'Impossible de charger la liste des clients pour le moment.',
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
        <h1 className="text-2xl font-semibold text-foreground">Clients</h1>
        <Link
          href="/customers/new"
          className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
        >
          Nouveau client
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

      {state.status === 'ready' && 0 === state.customers.length ? (
        <p className="text-sm text-muted-foreground">
          Aucun client enregistré pour le moment.
        </p>
      ) : null}

      {state.status === 'ready' && state.customers.length > 0 ? (
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Nom</th>
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">SIREN</th>
                <th className="px-4 py-2 font-medium">Pays</th>
                <th className="px-4 py-2 font-medium" aria-hidden="true" />
              </tr>
            </thead>
            <tbody>
              {state.customers.map((customer) => (
                <tr key={customer.id} className="border-t border-border">
                  <td className="px-4 py-2 text-foreground">{customer.name}</td>
                  <td className="px-4 py-2 text-foreground">
                    {CUSTOMER_TYPE_LABELS[customer.customer_type]}
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">
                    {customer.siren ?? '-'}
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">
                    {customer.country}
                  </td>
                  <td className="px-4 py-2 text-right">
                    <Link
                      href={`/customers/${customer.id}`}
                      className="text-sm font-medium text-primary hover:underline"
                    >
                      Modifier
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
