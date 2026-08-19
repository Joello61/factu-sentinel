"use client";

import Link from "next/link";
import { useEffect, useId, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { apiRequest, apiRequestPaginated } from "@/lib/api/client";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import { formatAmount } from "@/lib/format/amount";
import type { Customer, CreateInvoicePayload, Invoice, OperationType } from "@/lib/api/types";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

const OPERATION_TYPE_OPTIONS: { value: OperationType; label: string }[] = [
  { value: "VENTE_BIEN", label: "Vente de bien" },
  { value: "PRESTATION_SERVICE", label: "Prestation de service" },
  { value: "MIXTE", label: "Mixte" },
];

interface LineState {
  description: string;
  quantity: string;
  unitPriceHt: string;
  vatRate: string;
}

const EMPTY_LINE: LineState = { description: "", quantity: "1", unitPriceHt: "", vatRate: "0.20" };

function computeLineTotals(line: LineState): { ht: number; vat: number; ttc: number } {
  const quantity = Number(line.quantity);
  const unitPriceHt = Number(line.unitPriceHt);
  const vatRate = Number(line.vatRate);

  if (!Number.isFinite(quantity) || !Number.isFinite(unitPriceHt) || !Number.isFinite(vatRate)) {
    return { ht: 0, vat: 0, ttc: 0 };
  }

  const ht = quantity * unitPriceHt;
  const vat = ht * vatRate;

  return { ht, vat, ttc: ht + vat };
}

/**
 * Saisie manuelle d'une facture (US-INVOICE-002, docs/11-frontend-design-system.md, section
 * 33) : Client -> Informations générales -> Lignes -> Totaux. Volontairement sans étape
 * Documents (import de document reporté en Phase 7, docs/12-roadmap.md Phase 4) ni bouton
 * "Lancer l'analyse" (Compliance Engine, Phase 5, inexistant à ce stade) : le parcours
 * s'arrête à la création de la facture, disponible pour une analyse future.
 *
 * Les totaux affichés ici sont un aperçu client uniquement, jamais soumis au serveur : seuls
 * le backend (App\Invoicing\Service\InvoiceAmountCalculator) fait foi (../../../CLAUDE.md
 * frontend, section 3 et 8).
 */
export function InvoiceEditor() {
  const customerId = useId();
  const operationTypeId = useId();
  const router = useRouter();

  const [customers, setCustomers] = useState<Customer[] | null>(null);
  const [customersError, setCustomersError] = useState<string | null>(null);

  const [selectedCustomerId, setSelectedCustomerId] = useState("");
  const [operationType, setOperationType] = useState<OperationType | "">("");
  const [issueDate, setIssueDate] = useState("");
  const [currency, setCurrency] = useState("EUR");
  const [lines, setLines] = useState<LineState[]>([{ ...EMPTY_LINE }]);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data } = await apiRequestPaginated<Customer>("/api/v1/customers?per_page=100");
        if (!cancelled) {
          setCustomers(data);
        }
      } catch {
        if (!cancelled) {
          setCustomersError("Impossible de charger la liste des clients.");
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  function updateLine(index: number, next: Partial<LineState>) {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, ...next } : line)));
  }

  function addLine() {
    setLines((current) => [...current, { ...EMPTY_LINE }]);
  }

  function removeLine(index: number) {
    setLines((current) => current.filter((_, i) => i !== index));
  }

  const totals = lines.reduce(
    (acc, line) => {
      const lineTotals = computeLineTotals(line);
      return { ht: acc.ht + lineTotals.ht, ttc: acc.ttc + lineTotals.ttc };
    },
    { ht: 0, ttc: 0 },
  );

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);

    const payload: CreateInvoicePayload = {
      customer_id: selectedCustomerId,
      operation_type: operationType as OperationType,
      issue_date: issueDate,
      currency,
      lines: lines.map((line) => ({
        description: line.description,
        quantity: line.quantity,
        unit_price_ht: line.unitPriceHt,
        vat_rate: line.vatRate,
      })),
    };

    try {
      const invoice = await apiRequest<Invoice>("/api/v1/invoices", { method: "POST", body: payload });
      router.push(`/invoices/${invoice.id}`);
    } catch (error) {
      setErrors(toFormErrors(error, "Impossible d'enregistrer cette facture pour le moment."));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Nouvelle facture</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Cette saisie sert exclusivement à l&apos;analyse de conformité : aucune facture n&apos;est jamais émise ni
          transmise à votre client.
        </p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
        <fieldset className="flex flex-col gap-4">
          <legend className="text-sm font-semibold text-foreground">Client</legend>

          {customersError ? (
            <p role="alert" className="text-sm text-error">
              {customersError}
            </p>
          ) : null}

          <div className="flex flex-col gap-1.5">
            <label htmlFor={customerId} className="text-sm font-medium text-foreground">
              Client
            </label>
            <select
              id={customerId}
              name="customer_id"
              required
              value={selectedCustomerId}
              onChange={(event) => setSelectedCustomerId(event.target.value)}
              aria-invalid={errors.fieldErrors.customer_id ? true : undefined}
              className={`rounded-md border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
                errors.fieldErrors.customer_id ? "border-error" : "border-border"
              }`}
            >
              <option value="" disabled>
                Sélectionnez un client
              </option>
              {(customers ?? []).map((customer) => (
                <option key={customer.id} value={customer.id}>
                  {customer.name}
                </option>
              ))}
            </select>
            {errors.fieldErrors.customer_id ? (
              <p role="alert" className="text-xs text-error">
                {errors.fieldErrors.customer_id}
              </p>
            ) : null}
            <Link href="/customers/new" className="w-fit text-xs font-medium text-primary hover:underline">
              Créer un nouveau client
            </Link>
          </div>
        </fieldset>

        <fieldset className="flex flex-col gap-4">
          <legend className="text-sm font-semibold text-foreground">Informations générales</legend>

          <div className="flex flex-col gap-1.5">
            <label htmlFor={operationTypeId} className="text-sm font-medium text-foreground">
              Nature de l&apos;opération
            </label>
            <select
              id={operationTypeId}
              name="operation_type"
              required
              value={operationType}
              onChange={(event) => setOperationType(event.target.value as OperationType)}
              aria-invalid={errors.fieldErrors.operation_type ? true : undefined}
              className={`rounded-md border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
                errors.fieldErrors.operation_type ? "border-error" : "border-border"
              }`}
            >
              <option value="" disabled>
                Sélectionnez la nature de l&apos;opération
              </option>
              {OPERATION_TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            {errors.fieldErrors.operation_type ? (
              <p role="alert" className="text-xs text-error">
                {errors.fieldErrors.operation_type}
              </p>
            ) : null}
          </div>

          <FormField
            label="Date d'émission"
            name="issue_date"
            type="date"
            required
            value={issueDate}
            onChange={(event) => setIssueDate(event.target.value)}
            error={errors.fieldErrors.issue_date}
          />

          <FormField
            label="Devise"
            name="currency"
            required
            maxLength={3}
            value={currency}
            onChange={(event) => setCurrency(event.target.value.toUpperCase())}
            error={errors.fieldErrors.currency}
          />
        </fieldset>

        <fieldset className="flex flex-col gap-4">
          <legend className="text-sm font-semibold text-foreground">Lignes</legend>

          {errors.fieldErrors.lines ? (
            <p role="alert" className="text-sm text-error">
              {errors.fieldErrors.lines}
            </p>
          ) : null}

          <div className="flex flex-col gap-4">
            {lines.map((line, index) => {
              const lineTotals = computeLineTotals(line);
              return (
                <div key={index} className="rounded-md border border-border p-4">
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div className="sm:col-span-4">
                      <FormField
                        label="Description"
                        name={`lines.${index}.description`}
                        required
                        value={line.description}
                        onChange={(event) => updateLine(index, { description: event.target.value })}
                      />
                    </div>
                    <FormField
                      label="Quantité"
                      name={`lines.${index}.quantity`}
                      type="number"
                      min={0}
                      step="0.001"
                      required
                      value={line.quantity}
                      onChange={(event) => updateLine(index, { quantity: event.target.value })}
                    />
                    <FormField
                      label="Prix unitaire HT (€)"
                      name={`lines.${index}.unit_price_ht`}
                      type="number"
                      min={0}
                      step="0.01"
                      required
                      value={line.unitPriceHt}
                      onChange={(event) => updateLine(index, { unitPriceHt: event.target.value })}
                    />
                    <FormField
                      label="Taux de TVA"
                      name={`lines.${index}.vat_rate`}
                      type="number"
                      min={0}
                      max={1}
                      step="0.001"
                      required
                      hint="Fraction décimale (0.20 = 20 %)"
                      value={line.vatRate}
                      onChange={(event) => updateLine(index, { vatRate: event.target.value })}
                    />
                    <p className="self-end pb-2 text-sm tabular-nums text-muted-foreground sm:col-span-1">
                      TTC : {formatAmount(lineTotals.ttc.toFixed(2), currency || "EUR")}
                    </p>
                  </div>
                  {lines.length > 1 ? (
                    <button
                      type="button"
                      onClick={() => removeLine(index)}
                      className="mt-3 text-xs font-medium text-error hover:underline"
                    >
                      Supprimer cette ligne
                    </button>
                  ) : null}
                </div>
              );
            })}
          </div>

          <button
            type="button"
            onClick={addLine}
            className="w-fit rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground hover:bg-primary/10"
          >
            Ajouter une ligne
          </button>
        </fieldset>

        <div className="rounded-md border border-primary bg-primary/5 px-4 py-3">
          <p className="text-sm font-medium text-foreground">
            Total HT (aperçu) : <span className="tabular-nums">{formatAmount(totals.ht.toFixed(2), currency || "EUR")}</span>
          </p>
          <p className="text-sm font-medium text-foreground">
            Total TTC (aperçu) : <span className="tabular-nums">{formatAmount(totals.ttc.toFixed(2), currency || "EUR")}</span>
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Ces totaux sont un aperçu ; les montants réellement enregistrés sont toujours recalculés par le serveur.
          </p>
        </div>

        <Button type="submit" loading={loading}>
          Enregistrer la facture
        </Button>
      </form>
    </div>
  );
}
