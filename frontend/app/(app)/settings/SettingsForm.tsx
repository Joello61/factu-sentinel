"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { AlertDialog } from "radix-ui";
import { apiRequest } from "@/lib/api/client";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import { useAuth } from "@/components/auth/AuthProvider";
import type { CurrentUser } from "@/lib/api/types";

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

interface AccountFormState {
  email: string;
  currentPassword: string;
  newPassword: string;
  confirmNewPassword: string;
}

const EMPTY_ACCOUNT_FORM: AccountFormState = {
  email: "",
  currentPassword: "",
  newPassword: "",
  confirmNewPassword: "",
};

/**
 * Page Paramètres (US-SETTINGS-001/002, docs/11-frontend-design-system.md section 59, voir
 * plan Phase 13). Mot de passe actuel toujours requis dès qu'un email ou un nouveau mot de
 * passe est soumis (défense en profondeur côté backend, App\Identity\Service\
 * UpdateCurrentUserService) - jamais validé côté client, uniquement relayé.
 */
export function SettingsForm() {
  const router = useRouter();
  const { logout } = useAuth();

  const [loadingInitial, setLoadingInitial] = useState(true);
  const [currentUser, setCurrentUser] = useState<CurrentUser | null>(null);
  const [form, setForm] = useState<AccountFormState>(EMPTY_ACCOUNT_FORM);
  const [errors, setErrors] = useState<FormErrors>(EMPTY_ERRORS);
  const [saving, setSaving] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const [deletePassword, setDeletePassword] = useState("");
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const user = await apiRequest<CurrentUser>("/api/v1/users/current");
        if (!cancelled) {
          setCurrentUser(user);
          setForm((prev) => ({ ...prev, email: user.email }));
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
  }, []);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors(EMPTY_ERRORS);
    setSuccessMessage(null);

    if (form.newPassword !== form.confirmNewPassword) {
      setErrors({
        fieldErrors: { confirm_new_password: "Les deux mots de passe ne correspondent pas." },
        formError: null,
      });
      return;
    }

    const emailChanged = currentUser !== null && form.email !== currentUser.email;
    const payload: Record<string, string> = {};
    if (emailChanged) {
      payload.email = form.email;
    }
    if (form.newPassword !== "") {
      payload.new_password = form.newPassword;
    }
    if (emailChanged || form.newPassword !== "") {
      payload.current_password = form.currentPassword;
    }

    setSaving(true);
    try {
      const updated = await apiRequest<CurrentUser>("/api/v1/users/current", {
        method: "PATCH",
        body: payload,
      });
      setCurrentUser(updated);
      setForm({ email: updated.email, currentPassword: "", newPassword: "", confirmNewPassword: "" });
      setSuccessMessage(
        emailChanged
          ? "Informations mises à jour. Un email de vérification a été envoyé à votre nouvelle adresse."
          : "Informations mises à jour.",
      );
    } catch (error) {
      setErrors(toFormErrors(error, "Impossible d'enregistrer ces informations pour le moment."));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    setDeleteError(null);
    setDeleting(true);
    try {
      await apiRequest("/api/v1/users/current", {
        method: "DELETE",
        body: { current_password: deletePassword },
      });
      await logout();
      router.replace("/login");
    } catch (error) {
      const formErrors = toFormErrors(error, "Impossible de supprimer le compte pour le moment.");
      setDeleteError(formErrors.fieldErrors.current_password ?? formErrors.formError);
    } finally {
      setDeleting(false);
    }
  }

  if (loadingInitial) {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  return (
    <div className="flex max-w-xl flex-col gap-10">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Paramètres</h1>
        <p className="mt-1 text-sm text-muted-foreground">Consultez et modifiez les informations de votre compte.</p>
      </div>

      <section className="flex flex-col gap-4">
        <h2 className="text-lg font-medium text-foreground">Compte</h2>

        {errors.formError ? (
          <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
            {errors.formError}
          </p>
        ) : null}

        {successMessage ? (
          <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
            {successMessage}
          </p>
        ) : null}

        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
          <FormField
            label="Email"
            name="email"
            type="email"
            required
            value={form.email}
            onChange={(event) => setForm({ ...form, email: event.target.value })}
            error={errors.fieldErrors.email}
          />

          <FormField
            label="Nouveau mot de passe"
            name="new_password"
            type="password"
            hint="Laissez vide pour ne pas changer de mot de passe."
            value={form.newPassword}
            onChange={(event) => setForm({ ...form, newPassword: event.target.value })}
            error={errors.fieldErrors.new_password}
          />

          <FormField
            label="Confirmer le nouveau mot de passe"
            name="confirm_new_password"
            type="password"
            value={form.confirmNewPassword}
            onChange={(event) => setForm({ ...form, confirmNewPassword: event.target.value })}
            error={errors.fieldErrors.confirm_new_password}
          />

          <FormField
            label="Mot de passe actuel"
            name="current_password"
            type="password"
            hint="Requis pour confirmer un changement d'email ou de mot de passe."
            value={form.currentPassword}
            onChange={(event) => setForm({ ...form, currentPassword: event.target.value })}
            error={errors.fieldErrors.current_password}
          />

          <Button type="submit" loading={saving}>
            Enregistrer
          </Button>
        </form>
      </section>

      <section className="flex flex-col gap-4 rounded-md border border-error/40 p-4">
        <div>
          <h2 className="text-lg font-medium text-error">Zone dangereuse</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            La suppression de votre compte entraîne une perte d&apos;accès immédiate. Les factures et résultats de
            conformité déjà produits restent conservés le temps requis par les obligations légales.
          </p>
        </div>

        <AlertDialog.Root open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
          <AlertDialog.Trigger asChild>
            <Button type="button" variant="destructive" className="w-fit">
              Supprimer mon compte
            </Button>
          </AlertDialog.Trigger>

          <AlertDialog.Portal>
            <AlertDialog.Overlay className="fixed inset-0 bg-black/50" />
            <AlertDialog.Content className="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border border-border bg-surface p-6 shadow-lg">
              <AlertDialog.Title className="text-lg font-semibold text-foreground">
                Supprimer définitivement mon compte ?
              </AlertDialog.Title>
              <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
                Vous perdrez immédiatement l&apos;accès à votre compte. Les factures et résultats de conformité déjà
                produits restent conservés, comme l&apos;exigent les obligations légales de conservation.
              </AlertDialog.Description>

              <div className="mt-4">
                <FormField
                  label="Mot de passe actuel"
                  name="delete_current_password"
                  type="password"
                  required
                  value={deletePassword}
                  onChange={(event) => setDeletePassword(event.target.value)}
                  error={deleteError ?? undefined}
                />
              </div>

              <div className="mt-6 flex justify-end gap-3">
                <AlertDialog.Cancel asChild>
                  <Button type="button" variant="secondary" className="w-fit">
                    Annuler
                  </Button>
                </AlertDialog.Cancel>
                <Button
                  type="button"
                  variant="destructive"
                  className="w-fit"
                  loading={deleting}
                  onClick={() => {
                    void handleDelete();
                  }}
                >
                  Supprimer définitivement mon compte
                </Button>
              </div>
            </AlertDialog.Content>
          </AlertDialog.Portal>
        </AlertDialog.Root>
      </section>
    </div>
  );
}
