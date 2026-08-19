"use client";

import { useEffect, useId, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { apiRequest } from "@/lib/api/client";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import type { Customer, CustomerType } from "@/lib/api/types";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

const CUSTOMER_TYPE_OPTIONS: { value: CustomerType; label: string }[] = [
  { value: "PROFESSIONNEL_FRANCAIS", label: "Professionnel français" },
  { value: "PARTICULIER", label: "Particulier" },
  { value: "PROFESSIONNEL_ETRANGER", label: "Professionnel étranger" },
];

interface FormState {
  customerType: CustomerType | "";
  name: string;
  siren: string;
  vatNumber: string;
  country: string;
}

const EMPTY_FORM: FormState = {
  customerType: "",
  name: "",
  siren: "",
  vatNumber: "",
  country: "",
};

interface CustomerFormProps {
  /** Présent en édition (docs/08-api-specification.md, section 26, PATCH), absent en création. */
  customerId?: string;
}

/**
 * Création/édition d'un client (US-CUSTOMER-001/002, docs/11-frontend-design-system.md,
 * section 22). SIREN présenté comme recommandé, jamais requis, y compris pour un
 * professionnel français : son absence n'est pas une erreur de validation (plan Phase 4,
 * décision D1 ; backend App\Customer\Http\CreateCustomerRequest).
 */
export function CustomerForm({ customerId }: CustomerFormProps) {
  const customerTypeId = useId();
  const router = useRouter();
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [loading, setLoading] = useState(false);
  const [loadingInitial, setLoadingInitial] = useState(Boolean(customerId));
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);

  useEffect(() => {
    if (!customerId) {
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const customer = await apiRequest<Customer>(`/api/v1/customers/${customerId}`);
        if (cancelled) {
          return;
        }
        setForm({
          customerType: customer.customer_type,
          name: customer.name,
          siren: customer.siren ?? "",
          vatNumber: customer.vat_number ?? "",
          country: customer.country,
        });
      } catch (error) {
        if (!cancelled) {
          setErrors(toFormErrors(error, "Impossible de charger ce client."));
        }
      } finally {
        if (!cancelled) {
          setLoadingInitial(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [customerId]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setErrors(EMPTY_ERRORS);

    const payload = {
      customer_type: form.customerType,
      name: form.name,
      siren: form.siren === "" ? null : form.siren,
      vat_number: form.vatNumber === "" ? null : form.vatNumber,
      country: form.country.toUpperCase(),
    };

    try {
      if (customerId) {
        await apiRequest<Customer>(`/api/v1/customers/${customerId}`, { method: "PATCH", body: payload });
      } else {
        await apiRequest<Customer>("/api/v1/customers", { method: "POST", body: payload });
      }
      router.push("/customers");
    } catch (error) {
      setErrors(toFormErrors(error, "Impossible d'enregistrer ce client pour le moment."));
    } finally {
      setLoading(false);
    }
  }

  if (loadingInitial) {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  return (
    <div className="flex max-w-xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">{customerId ? "Modifier le client" : "Nouveau client"}</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Le type de client détermine les règles de conformité applicables à ses factures (e-invoicing pour un
          professionnel français, e-reporting pour un particulier).
        </p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={customerTypeId} className="text-sm font-medium text-foreground">
            Type de client
          </label>
          <select
            id={customerTypeId}
            name="customer_type"
            required
            value={form.customerType}
            onChange={(event) => setForm({ ...form, customerType: event.target.value as CustomerType })}
            aria-invalid={errors.fieldErrors.customer_type ? true : undefined}
            className={`rounded-md border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 ${
              errors.fieldErrors.customer_type ? "border-error" : "border-border"
            }`}
          >
            <option value="" disabled>
              Sélectionnez un type de client
            </option>
            {CUSTOMER_TYPE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {errors.fieldErrors.customer_type ? (
            <p role="alert" className="text-xs text-error">
              {errors.fieldErrors.customer_type}
            </p>
          ) : null}
        </div>

        <FormField
          label="Nom ou raison sociale"
          name="name"
          required
          value={form.name}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
          error={errors.fieldErrors.name}
        />

        <FormField
          label="SIREN"
          name="siren"
          hint="Recommandé pour un professionnel français, afin de faciliter la vérification de cette mention obligatoire. Son absence n'empêche pas la création du client."
          value={form.siren}
          onChange={(event) => setForm({ ...form, siren: event.target.value })}
          error={errors.fieldErrors.siren}
        />

        <FormField
          label="Numéro de TVA intracommunautaire"
          name="vat_number"
          hint="Pertinent pour un client professionnel étranger."
          value={form.vatNumber}
          onChange={(event) => setForm({ ...form, vatNumber: event.target.value })}
          error={errors.fieldErrors.vat_number}
        />

        <FormField
          label="Pays"
          name="country"
          required
          maxLength={2}
          hint="Code pays ISO 3166-1 alpha-2 (ex. FR)."
          value={form.country}
          onChange={(event) => setForm({ ...form, country: event.target.value })}
          error={errors.fieldErrors.country}
        />

        <Button type="submit" loading={loading}>
          Enregistrer
        </Button>
      </form>
    </div>
  );
}
