"use client";

import { useEffect, useId, useState, type FormEvent } from "react";
import { apiRequest } from "@/lib/api/client";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import { formatBusinessDate } from "@/lib/format/date";
import type { Organization, VatStatus } from "@/lib/api/types";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

const VAT_STATUS_OPTIONS: { value: VatStatus; label: string }[] = [
  { value: "ASSUJETTI_REDEVABLE", label: "Assujetti et redevable de la TVA" },
  { value: "ASSUJETTI_FRANCHISE_EN_BASE", label: "Assujetti, en franchise en base de TVA" },
  { value: "NON_ASSUJETTI", label: "Non assujetti à la TVA" },
];

interface FormState {
  legalName: string;
  vatStatus: VatStatus | "";
  employeesCount: string;
  annualTurnover: string;
  annualBalanceSheetTotal: string;
}

const EMPTY_FORM: FormState = {
  legalName: "",
  vatStatus: "",
  employeesCount: "",
  annualTurnover: "",
  annualBalanceSheetTotal: "",
};

/**
 * Configuration entreprise (US-COMPANY-001/002, docs/11-frontend-design-system.md, section
 * 59). Les trois valeurs brutes (effectif, CA, bilan) sont saisies ici ; company_size_category
 * n'est jamais soumis par ce formulaire, il est toujours dérivé par le backend
 * (docs/07-data-model.md, section 7 — voir plan Phase 3, gap 1).
 */
export function CompanyForm() {
  const vatStatusId = useId();
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [loading, setLoading] = useState(false);
  const [loadingInitial, setLoadingInitial] = useState(true);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);
  const [organization, setOrganization] = useState<Organization | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const current = await apiRequest<Organization>("/api/v1/organizations/current");
        if (cancelled) {
          return;
        }
        setOrganization(current);
        setForm({
          legalName: current.legal_name ?? "",
          vatStatus: current.fiscal_context?.vat_status ?? "",
          employeesCount: current.fiscal_context ? String(current.fiscal_context.employees_count) : "",
          annualTurnover: current.fiscal_context?.annual_turnover ?? "",
          annualBalanceSheetTotal: current.fiscal_context?.annual_balance_sheet_total ?? "",
        });
      } catch {
        // Première visite : rien à pré-remplir, le formulaire reste vide (comportement
        // normal, pas une erreur à afficher à l'utilisateur).
      } finally {
        if (!cancelled) {
          setLoadingInitial(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);

    try {
      const updated = await apiRequest<Organization>("/api/v1/organizations/current", {
        method: "PATCH",
        body: {
          legal_name: form.legalName,
          fiscal_context: {
            vat_status: form.vatStatus,
            employees_count: form.employeesCount === "" ? null : Number(form.employeesCount),
            annual_turnover: form.annualTurnover,
            annual_balance_sheet_total: form.annualBalanceSheetTotal,
          },
        },
      });
      setOrganization(updated);
    } catch (error) {
      setErrors(toFormErrors(error, "Impossible d'enregistrer ces informations pour le moment."));
    } finally {
      setLoading(false);
    }
  }

  if (loadingInitial) {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  const diagnostic = organization?.eligibility_diagnostic;

  return (
    <div className="flex max-w-xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Configuration entreprise</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Ces informations déterminent si et à partir de quand votre entreprise est concernée par la réforme de la
          facturation électronique.
        </p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <FormField
          label="Raison sociale"
          name="legal_name"
          required
          value={form.legalName}
          onChange={(event) => setForm({ ...form, legalName: event.target.value })}
          error={errors.fieldErrors.legal_name}
        />

        <div className="flex flex-col gap-1.5">
          <label htmlFor={vatStatusId} className="text-sm font-medium text-foreground">
            Statut TVA
          </label>
          <select
            id={vatStatusId}
            name="vat_status"
            required
            value={form.vatStatus}
            onChange={(event) => setForm({ ...form, vatStatus: event.target.value as VatStatus })}
            aria-invalid={errors.fieldErrors.vat_status ? true : undefined}
            className={`rounded-md border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
              errors.fieldErrors.vat_status ? "border-error" : "border-border"
            }`}
          >
            <option value="" disabled>
              Sélectionnez votre statut TVA
            </option>
            {VAT_STATUS_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {errors.fieldErrors.vat_status ? (
            <p role="alert" className="text-xs text-error">
              {errors.fieldErrors.vat_status}
            </p>
          ) : null}
        </div>

        <FormField
          label="Effectif salarié"
          name="employees_count"
          type="number"
          min={0}
          step={1}
          required
          value={form.employeesCount}
          onChange={(event) => setForm({ ...form, employeesCount: event.target.value })}
          error={errors.fieldErrors.employees_count}
        />

        <FormField
          label="Chiffre d'affaires annuel (€)"
          name="annual_turnover"
          type="number"
          min={0}
          step="0.01"
          required
          value={form.annualTurnover}
          onChange={(event) => setForm({ ...form, annualTurnover: event.target.value })}
          error={errors.fieldErrors.annual_turnover}
        />

        <FormField
          label="Total du bilan annuel (€)"
          name="annual_balance_sheet_total"
          type="number"
          min={0}
          step="0.01"
          required
          value={form.annualBalanceSheetTotal}
          onChange={(event) => setForm({ ...form, annualBalanceSheetTotal: event.target.value })}
          error={errors.fieldErrors.annual_balance_sheet_total}
        />

        <Button type="submit" loading={loading}>
          Enregistrer
        </Button>
      </form>

      {diagnostic ? (
        <div className="rounded-md border border-primary bg-primary/5 px-4 py-3">
          <p className="text-sm font-medium text-foreground">Diagnostic d&apos;éligibilité</p>
          {diagnostic.reception_obligation_date ? (
            <ul className="mt-2 flex flex-col gap-1 text-sm text-foreground">
              <li>Obligation de réception à partir du {formatBusinessDate(diagnostic.reception_obligation_date)}</li>
              {diagnostic.emission_obligation_date ? (
                <li>Obligation d&apos;émission à partir du {formatBusinessDate(diagnostic.emission_obligation_date)}</li>
              ) : null}
            </ul>
          ) : null}
          <p className="mt-2 text-sm text-muted-foreground">{diagnostic.explanation}</p>
        </div>
      ) : null}
    </div>
  );
}
