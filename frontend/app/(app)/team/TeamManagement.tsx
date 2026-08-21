"use client";

import { useEffect, useState, type FormEvent } from "react";
import { AlertDialog } from "radix-ui";
import { apiRequest } from "@/lib/api/client";
import { FormField } from "@/components/forms/FormField";
import { Button } from "@/components/ui/Button";
import { toFormErrors, type FormErrors } from "@/lib/forms/api-error";
import type {
  AssignableRole,
  Invitation,
  InviteMemberPayload,
  Member,
  Organization,
  Role,
  SendTeamNotificationPayload,
} from "@/lib/api/types";

const ROLE_LABELS: Record<Role, string> = {
  OWNER: "Propriétaire",
  ADMIN: "Administrateur",
  COLLABORATOR: "Collaborateur",
};

const ASSIGNABLE_ROLE_OPTIONS: { value: AssignableRole; label: string }[] = [
  { value: "ADMIN", label: "Administrateur" },
  { value: "COLLABORATOR", label: "Collaborateur" },
];

const EMPTY_ERRORS: FormErrors = { fieldErrors: {}, formError: null };

type LoadState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; role: Role; members: Member[]; invitations: Invitation[] };

/**
 * Gestion d'équipe (EPIC-TEAM, docs/11-frontend-design-system.md section 59, plan Phase 14).
 * Toute restriction visuelle ici (masquer un bouton, désactiver une action) reste un confort
 * d'expérience uniquement - le backend (App\Shared\Security\OrganizationPermissionVoter)
 * revalide systématiquement (../../../CLAUDE.md frontend, section 6).
 */
export function TeamManagement() {
  const [state, setState] = useState<LoadState>({ status: "loading" });

  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRole, setInviteRole] = useState<AssignableRole>("COLLABORATOR");
  const [inviteErrors, setInviteErrors] = useState<FormErrors>(EMPTY_ERRORS);
  const [inviting, setInviting] = useState(false);
  const [inviteSuccess, setInviteSuccess] = useState<string | null>(null);

  const [notificationRecipients, setNotificationRecipients] = useState<string[]>([]);
  const [notificationMessage, setNotificationMessage] = useState("");
  const [notificationErrors, setNotificationErrors] = useState<FormErrors>(EMPTY_ERRORS);
  const [sendingNotification, setSendingNotification] = useState(false);
  const [notificationSuccess, setNotificationSuccess] = useState<string | null>(null);

  const [pendingRemoval, setPendingRemoval] = useState<Member | null>(null);
  const [removing, setRemoving] = useState(false);
  const [removeError, setRemoveError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const [organization, members, invitations] = await Promise.all([
          apiRequest<Organization>("/api/v1/organizations/current"),
          apiRequest<Member[]>("/api/v1/organizations/current/members"),
          apiRequest<Invitation[]>("/api/v1/organizations/current/invitations"),
        ]);
        if (!cancelled) {
          setState({ status: "ready", role: organization.role, members, invitations });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger les informations d'équipe pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleInvite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (state.status !== "ready") {
      return;
    }
    setInviteErrors(EMPTY_ERRORS);
    setInviteSuccess(null);
    setInviting(true);
    try {
      const payload: InviteMemberPayload = { email: inviteEmail, role: inviteRole };
      const created = await apiRequest<Invitation>("/api/v1/organizations/current/invitations", {
        method: "POST",
        body: payload,
        headers: { "Idempotency-Key": crypto.randomUUID() },
      });
      setState({ ...state, invitations: [created, ...state.invitations] });
      setInviteEmail("");
      setInviteSuccess(`Invitation envoyée à ${payload.email}.`);
    } catch (error) {
      setInviteErrors(toFormErrors(error, "Impossible d'envoyer cette invitation pour le moment."));
    } finally {
      setInviting(false);
    }
  }

  async function handleRevokeInvitation(invitation: Invitation) {
    if (state.status !== "ready") {
      return;
    }
    try {
      await apiRequest(`/api/v1/organizations/current/invitations/${invitation.id}`, { method: "DELETE" });
      setState({ ...state, invitations: state.invitations.filter((item) => item.id !== invitation.id) });
    } catch {
      // Confort : un échec de révocation laisse simplement l'invitation en attente visible,
      // l'utilisateur peut réessayer.
    }
  }

  async function handleRoleChange(member: Member, role: AssignableRole) {
    if (state.status !== "ready") {
      return;
    }
    try {
      const updated = await apiRequest<Member>(`/api/v1/organizations/current/members/${member.id}`, {
        method: "PATCH",
        body: { role },
      });
      setState({
        ...state,
        members: state.members.map((item) => (item.id === updated.id ? updated : item)),
      });
    } catch {
      // Confort d'affichage : en cas d'échec, le rôle affiché reste simplement celui d'avant
      // la tentative - l'utilisateur peut réessayer.
    }
  }

  async function handleRemoveMember() {
    if (!pendingRemoval || state.status !== "ready") {
      return;
    }
    setRemoveError(null);
    setRemoving(true);
    try {
      await apiRequest(`/api/v1/organizations/current/members/${pendingRemoval.id}`, { method: "DELETE" });
      setState({ ...state, members: state.members.filter((item) => item.id !== pendingRemoval.id) });
      setPendingRemoval(null);
    } catch (error) {
      setRemoveError(toFormErrors(error, "Impossible de retirer ce membre pour le moment.").formError);
    } finally {
      setRemoving(false);
    }
  }

  async function handleSendNotification(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setNotificationErrors(EMPTY_ERRORS);
    setNotificationSuccess(null);

    if (notificationRecipients.length === 0) {
      setNotificationErrors({ fieldErrors: {}, formError: "Sélectionnez au moins un destinataire." });
      return;
    }

    setSendingNotification(true);
    try {
      const payload: SendTeamNotificationPayload = {
        recipient_ids: notificationRecipients,
        message: notificationMessage,
      };
      await apiRequest("/api/v1/organizations/current/notifications", {
        method: "POST",
        body: payload,
        headers: { "Idempotency-Key": crypto.randomUUID() },
      });
      setNotificationMessage("");
      setNotificationRecipients([]);
      setNotificationSuccess("Notification envoyée.");
    } catch (error) {
      setNotificationErrors(toFormErrors(error, "Impossible d'envoyer cette notification pour le moment."));
    } finally {
      setSendingNotification(false);
    }
  }

  if (state.status === "loading") {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === "error") {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {state.message}
      </p>
    );
  }

  const canManageTeam = state.role === "OWNER" || state.role === "ADMIN";
  const canManageRoles = state.role === "OWNER";

  return (
    <div className="flex flex-col gap-10">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Équipe</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Gérez les membres de votre organisation et communiquez avec eux.
        </p>
      </div>

      <section className="flex flex-col gap-4">
        <h2 className="text-lg font-medium text-foreground">Membres</h2>

        <div className="hidden overflow-x-auto rounded-md border border-border sm:block">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Email</th>
                <th className="px-4 py-2 font-medium">Rôle</th>
                {canManageTeam ? <th className="px-4 py-2 font-medium" aria-hidden="true" /> : null}
              </tr>
            </thead>
            <tbody>
              {state.members.map((member) => (
                <tr key={member.id} className="border-t border-border">
                  <td className="px-4 py-2 text-foreground">{member.email}</td>
                  <td className="px-4 py-2 text-foreground">
                    {canManageRoles && member.role !== "OWNER" ? (
                      <select
                        aria-label={`Rôle de ${member.email}`}
                        value={member.role}
                        onChange={(event) => void handleRoleChange(member, event.target.value as AssignableRole)}
                        className="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
                      >
                        {ASSIGNABLE_ROLE_OPTIONS.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    ) : (
                      ROLE_LABELS[member.role]
                    )}
                  </td>
                  {canManageTeam ? (
                    <td className="px-4 py-2 text-right">
                      {member.role !== "OWNER" ? (
                        <Button
                          type="button"
                          variant="destructive"
                          className="w-fit"
                          onClick={() => setPendingRemoval(member)}
                        >
                          Retirer
                        </Button>
                      ) : null}
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <ul className="flex flex-col gap-3 sm:hidden">
          {state.members.map((member) => (
            <li key={member.id} className="flex flex-col gap-1 rounded-md border border-border p-4">
              <span className="text-sm font-medium text-foreground">{member.email}</span>
              <span className="text-xs text-muted-foreground">{ROLE_LABELS[member.role]}</span>
              {canManageTeam && member.role !== "OWNER" ? (
                <Button
                  type="button"
                  variant="destructive"
                  className="mt-2 w-fit"
                  onClick={() => setPendingRemoval(member)}
                >
                  Retirer
                </Button>
              ) : null}
            </li>
          ))}
        </ul>

        <AlertDialog.Root open={pendingRemoval !== null} onOpenChange={(open) => !open && setPendingRemoval(null)}>
          <AlertDialog.Portal>
            <AlertDialog.Overlay className="fixed inset-0 bg-black/50" />
            <AlertDialog.Content className="fixed left-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border border-border bg-surface p-6 shadow-lg">
              <AlertDialog.Title className="text-lg font-semibold text-foreground">
                Retirer {pendingRemoval?.email} de l&apos;organisation ?
              </AlertDialog.Title>
              <AlertDialog.Description className="mt-2 text-sm text-muted-foreground">
                Ce membre perdra immédiatement l&apos;accès à l&apos;organisation.
              </AlertDialog.Description>

              {removeError ? (
                <p role="alert" className="mt-4 rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
                  {removeError}
                </p>
              ) : null}

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
                  loading={removing}
                  onClick={() => {
                    void handleRemoveMember();
                  }}
                >
                  Retirer définitivement
                </Button>
              </div>
            </AlertDialog.Content>
          </AlertDialog.Portal>
        </AlertDialog.Root>
      </section>

      {canManageTeam ? (
        <section className="flex max-w-md flex-col gap-4">
          <h2 className="text-lg font-medium text-foreground">Inviter un membre</h2>

          {inviteErrors.formError ? (
            <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
              {inviteErrors.formError}
            </p>
          ) : null}
          {inviteSuccess ? (
            <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
              {inviteSuccess}
            </p>
          ) : null}

          <form onSubmit={handleInvite} noValidate className="flex flex-col gap-4">
            <FormField
              label="Email"
              name="invite_email"
              type="email"
              required
              value={inviteEmail}
              onChange={(event) => setInviteEmail(event.target.value)}
              error={inviteErrors.fieldErrors.email}
            />

            <div className="flex flex-col gap-1.5">
              <label htmlFor="invite_role" className="text-sm font-medium text-foreground">
                Rôle
              </label>
              <select
                id="invite_role"
                value={inviteRole}
                onChange={(event) => setInviteRole(event.target.value as AssignableRole)}
                className="rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
              >
                {ASSIGNABLE_ROLE_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            <Button type="submit" loading={inviting}>
              Envoyer l&apos;invitation
            </Button>
          </form>

          {state.invitations.length > 0 ? (
            <div className="flex flex-col gap-2">
              <h3 className="text-sm font-medium text-foreground">Invitations en attente</h3>
              <ul className="flex flex-col gap-2">
                {state.invitations.map((invitation) => (
                  <li
                    key={invitation.id}
                    className="flex items-center justify-between gap-2 rounded-md border border-border p-3"
                  >
                    <span className="text-sm text-foreground">
                      {invitation.email} · {ROLE_LABELS[invitation.role]}
                    </span>
                    <Button
                      type="button"
                      variant="secondary"
                      className="w-fit"
                      onClick={() => void handleRevokeInvitation(invitation)}
                    >
                      Révoquer
                    </Button>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </section>
      ) : null}

      {canManageTeam ? (
        <section className="flex max-w-md flex-col gap-4">
          <h2 className="text-lg font-medium text-foreground">Notifier des membres</h2>

          {notificationErrors.formError ? (
            <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
              {notificationErrors.formError}
            </p>
          ) : null}
          {notificationSuccess ? (
            <p role="status" className="rounded-md border border-success bg-success/10 px-3 py-2 text-sm text-success">
              {notificationSuccess}
            </p>
          ) : null}

          <form onSubmit={handleSendNotification} noValidate className="flex flex-col gap-4">
            <fieldset className="flex flex-col gap-2">
              <legend className="text-sm font-medium text-foreground">Destinataires</legend>
              {state.members.map((member) => (
                <label key={member.id} className="flex items-center gap-2 text-sm text-foreground">
                  <input
                    type="checkbox"
                    checked={notificationRecipients.includes(member.user_id)}
                    onChange={(event) => {
                      setNotificationRecipients((current) =>
                        event.target.checked
                          ? [...current, member.user_id]
                          : current.filter((id) => id !== member.user_id),
                      );
                    }}
                  />
                  {member.email}
                </label>
              ))}
            </fieldset>

            <div className="flex flex-col gap-1.5">
              <label htmlFor="notification_message" className="text-sm font-medium text-foreground">
                Message
              </label>
              <textarea
                id="notification_message"
                required
                rows={3}
                maxLength={1000}
                value={notificationMessage}
                onChange={(event) => setNotificationMessage(event.target.value)}
                className="rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1"
              />
              {notificationErrors.fieldErrors.message ? (
                <p role="alert" className="text-xs text-error">
                  {notificationErrors.fieldErrors.message}
                </p>
              ) : null}
            </div>

            <Button type="submit" loading={sendingNotification}>
              Envoyer
            </Button>
          </form>
        </section>
      ) : null}
    </div>
  );
}
