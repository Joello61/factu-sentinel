"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiRequest, ApiError } from "@/lib/api/client";
import { formatBusinessDate } from "@/lib/format/date";
import type { EligibilityDiagnostic } from "@/lib/api/types";

type ViewState =
  | { status: "loading" }
  | { status: "not-configured" }
  | { status: "error"; message: string }
  | { status: "ready"; diagnostic: EligibilityDiagnostic };

/**
 * Diagnostic d'éligibilité (US-COMPLIANCE-001, docs/11-frontend-design-system.md, section
 * 59). Un 404 signifie ici un état légitime "pas encore configuré", jamais une erreur
 * système (../../../CLAUDE.md frontend, section 6) : distinct de toute autre erreur API.
 */
export function DiagnosticView() {
  const [state, setState] = useState<ViewState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const diagnostic = await apiRequest<EligibilityDiagnostic>("/api/v1/eligibility-diagnostics/current");
        if (!cancelled) {
          setState({ status: "ready", diagnostic });
        }
      } catch (error) {
        if (cancelled) {
          return;
        }
        if (error instanceof ApiError && error.status === 404) {
          setState({ status: "not-configured" });
          return;
        }
        setState({ status: "error", message: "Impossible de charger le diagnostic pour le moment." });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  if (state.status === "loading") {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === "not-configured") {
    return (
      <div className="flex max-w-xl flex-col gap-3">
        <h1 className="text-2xl font-semibold text-foreground">Diagnostic d&apos;éligibilité</h1>
        <p className="text-sm text-muted-foreground">
          Configurez d&apos;abord votre entreprise (statut TVA et taille) pour obtenir votre diagnostic
          d&apos;éligibilité à la réforme de la facturation électronique.
        </p>
        <Link
          href="/company"
          className="inline-flex w-fit items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
        >
          Configurer mon entreprise
        </Link>
      </div>
    );
  }

  if (state.status === "error") {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {state.message}
      </p>
    );
  }

  const { diagnostic } = state;

  return (
    <div className="flex max-w-xl flex-col gap-4">
      <h1 className="text-2xl font-semibold text-foreground">Diagnostic d&apos;éligibilité</h1>

      {diagnostic.reception_obligation_date ? (
        <div className="rounded-md border border-primary bg-primary/5 px-4 py-3">
          <ul className="flex flex-col gap-1 text-sm text-foreground">
            <li>Obligation de réception à partir du {formatBusinessDate(diagnostic.reception_obligation_date)}</li>
            {diagnostic.emission_obligation_date ? (
              <li>Obligation d&apos;émission à partir du {formatBusinessDate(diagnostic.emission_obligation_date)}</li>
            ) : null}
          </ul>
        </div>
      ) : (
        <div className="rounded-md border border-border bg-surface px-4 py-3">
          <p className="text-sm text-foreground">
            Votre entreprise n&apos;est, en l&apos;état des informations renseignées, pas concernée par la réforme.
          </p>
        </div>
      )}

      <p className="text-sm text-muted-foreground">{diagnostic.explanation}</p>

      <Link href="/company" className="w-fit text-sm font-medium text-primary hover:underline">
        Modifier les informations de mon entreprise
      </Link>
    </div>
  );
}
