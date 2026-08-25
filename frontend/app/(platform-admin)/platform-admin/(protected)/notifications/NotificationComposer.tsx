"use client";

import { useState, type FormEvent } from "react";
import { AlertDialog } from "radix-ui";
import { platformAdminApiRequest } from "@/lib/api/platformAdminClient";
import { Button } from "@/components/ui/Button";
import { FormField } from "@/components/forms/FormField";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import type {
  PlatformNotificationTargetType,
  SendPlatformNotificationPayload,
  SendPlatformNotificationResult,
} from "@/lib/api/platformAdminTypes";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

const TARGET_TYPE_OPTIONS: { value: PlatformNotificationTargetType; label: string }[] = [
  { value: "USER", label: "Un utilisateur précis" },
  { value: "ORGANIZATION", label: "Une organisation entière" },
  { value: "SEGMENT", label: "Un segment (critères fiscaux)" },
  { value: "ALL", label: "Diffusion globale (tous les utilisateurs)" },
];

const VAT_STATUS_OPTIONS = [
  { value: "ASSUJETTI_REDEVABLE", label: "Assujetti redevable" },
  { value: "ASSUJETTI_FRANCHISE_EN_BASE", label: "Assujetti, franchise en base" },
  { value: "NON_ASSUJETTI", label: "Non assujetti" },
];

const COMPANY_SIZE_OPTIONS = [
  { value: "PME_TPE_MICRO", label: "PME / TPE / micro-entreprise" },
  { value: "GRANDE_ENTREPRISE_ETI", label: "Grande entreprise / ETI" },
];

/**
 * US-PLATFORMADMIN-004 (docs/08-api-specification.md, section 38.2). Confirmation renforcée
 * pour target_type = ALL (docs/11-frontend-design-system.md, ligne 303 : "une diffusion
 * globale mal maîtrisée impacte tous les utilisateurs") - un AlertDialog systématique,
 * jamais un simple bouton de soumission direct, quel que soit target_type.
 */
export function NotificationComposer() {
  const [targetType, setTargetType] = useState<PlatformNotificationTargetType>("USER");
  const [targetId, setTargetId] = useState("");
  const [vatStatuses, setVatStatuses] = useState<string[]>([]);
  const [companySizeCategories, setCompanySizeCategories] = useState<string[]>([]);
  const [message, setMessage] = useState("");
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);
  const [confirming, setConfirming] = useState(false);
  const [sending, setSending] = useState(false);
  const [result, setResult] = useState<SendPlatformNotificationResult | null>(null);

  function toggle(current: string[], value: string, setter: (next: string[]) => void) {
    setter(current.includes(value) ? current.filter((item) => item !== value) : [...current, value]);
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors(EMPTY_ERRORS);
    setResult(null);
    setConfirming(true);
  }

  async function handleConfirmedSend() {
    setSending(true);
    setErrors(EMPTY_ERRORS);
    try {
      const payload: SendPlatformNotificationPayload = {
        target_type: targetType,
        message,
        ...(targetType === "USER" || targetType === "ORGANIZATION" ? { target_id: targetId } : {}),
        ...(targetType === "SEGMENT"
          ? {
              target_criteria: {
                ...(vatStatuses.length > 0 ? { vat_status: vatStatuses } : {}),
                ...(companySizeCategories.length > 0 ? { company_size_category: companySizeCategories } : {}),
              },
            }
          : {}),
      };
      const sent = await platformAdminApiRequest<SendPlatformNotificationResult>(
        "/api/v1/platform-admin/notifications",
        { method: "POST", body: payload, headers: { "Idempotency-Key": crypto.randomUUID() } },
      );
      setResult(sent);
      setMessage("");
      setTargetId("");
      setVatStatuses([]);
      setCompanySizeCategories([]);
      setConfirming(false);
    } catch (error) {
      setErrors(toFormErrors(error, "Impossible d'envoyer cette notification pour le moment."));
      setConfirming(false);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Notifications</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Communiquez avec un utilisateur, une organisation, un segment ou l&apos;ensemble de la plateforme.
        </p>
      </div>

      {errors.formError ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {errors.formError}
        </p>
      ) : null}

      {result ? (
        <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
          Notification envoyée à {result.estimated_recipient_count} destinataire(s).
        </p>
      ) : null}

      <form onSubmit={handleSubmit} noValidate className="flex max-w-lg flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <label htmlFor="target_type" className="text-sm font-medium text-foreground">
            Cible
          </label>
          <select
            id="target_type"
            value={targetType}
            onChange={(event) => setTargetType(event.target.value as PlatformNotificationTargetType)}
            className="rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
          >
            {TARGET_TYPE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        {targetType === "USER" || targetType === "ORGANIZATION" ? (
          <FormField
            label={targetType === "USER" ? "Identifiant de l'utilisateur" : "Identifiant de l'organisation"}
            name="target_id"
            required
            value={targetId}
            onChange={(event) => setTargetId(event.target.value)}
            error={errors.fieldErrors.target_id}
          />
        ) : null}

        {targetType === "SEGMENT" ? (
          <div className="flex flex-col gap-4">
            <fieldset className="flex flex-col gap-2">
              <legend className="text-sm font-medium text-foreground">Statut TVA</legend>
              {VAT_STATUS_OPTIONS.map((option) => (
                <label key={option.value} className="flex items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    checked={vatStatuses.includes(option.value)}
                    onChange={() => toggle(vatStatuses, option.value, setVatStatuses)}
                  />
                  {option.label}
                </label>
              ))}
            </fieldset>
            <fieldset className="flex flex-col gap-2">
              <legend className="text-sm font-medium text-foreground">Taille d&apos;entreprise</legend>
              {COMPANY_SIZE_OPTIONS.map((option) => (
                <label key={option.value} className="flex items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    checked={companySizeCategories.includes(option.value)}
                    onChange={() => toggle(companySizeCategories, option.value, setCompanySizeCategories)}
                  />
                  {option.label}
                </label>
              ))}
            </fieldset>
          </div>
        ) : null}

        <div className="flex flex-col gap-1.5">
          <label htmlFor="message" className="text-sm font-medium text-foreground">
            Message
          </label>
          <textarea
            id="message"
            required
            rows={4}
            maxLength={1000}
            value={message}
            onChange={(event) => setMessage(event.target.value)}
            className="rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
          />
          {errors.fieldErrors.message ? (
            <p role="alert" className="text-xs text-error">
              {errors.fieldErrors.message}
            </p>
          ) : null}
        </div>

        <Button type="submit">Envoyer</Button>
      </form>

      <AlertDialog.Root open={confirming} onOpenChange={(open) => !sending && setConfirming(open)}>
        <AlertDialog.Portal>
          <AlertDialog.Overlay className="fixed inset-0 bg-black/50" />
          <AlertDialog.Content className="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border border-border bg-surface p-6 shadow-lg">
            <AlertDialog.Title className="text-lg font-semibold text-foreground">
              {targetType === "ALL" ? "Diffuser à tous les utilisateurs ?" : "Envoyer cette notification ?"}
            </AlertDialog.Title>
            <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
              {targetType === "ALL"
                ? "Ce message sera envoyé à l'ensemble des utilisateurs de la plateforme, toutes organisations confondues. Cette action ne peut pas être annulée."
                : "Cette action ne peut pas être annulée."}
            </AlertDialog.Description>

            <div className="mt-6 flex justify-end gap-3">
              <AlertDialog.Cancel asChild>
                <Button type="button" variant="secondary" className="w-fit">
                  Annuler
                </Button>
              </AlertDialog.Cancel>
              <Button
                type="button"
                variant={targetType === "ALL" ? "destructive" : "primary"}
                className="w-fit"
                loading={sending}
                onClick={() => {
                  void handleConfirmedSend();
                }}
              >
                {targetType === "ALL" ? "Diffuser à tous" : "Envoyer"}
              </Button>
            </div>
          </AlertDialog.Content>
        </AlertDialog.Portal>
      </AlertDialog.Root>
    </div>
  );
}
