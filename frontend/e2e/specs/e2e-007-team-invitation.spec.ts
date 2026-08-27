import { test, expect, chromium } from "@playwright/test";
import type { Browser, BrowserContext, Page } from "@playwright/test";
import { onboardOrganization } from "../support/onboarding";
import { waitForEmailLink } from "../support/mailpit";
import { TEST_PASSWORD, uniqueEmail } from "../support/testData";

/**
 * E2E-007 (plan Phase 14, revue de complétude du 21/08/2026 - ajout de périmètre documenté,
 * au même niveau que E2E-001 à E2E-006, docs/09-test-strategy.md section 38). Parcours
 * complet EPIC-TEAM/US-NOTIFICATION-003 via l'UI réelle, jamais un appel API direct :
 * invitation -> email (Mailpit) -> inscription du compte invité -> acceptation ->
 * apparition dans la liste des membres -> notification d'équipe reçue.
 *
 * Deux BrowserContext isolés (même patron que e2e-005-tenant-isolation.spec.ts) : le
 * propriétaire et l'invité sont deux comptes réellement distincts, jamais deux onglets d'une
 * même session qui partageraient les cookies.
 */
test.describe("E2E-007 - Invitation et notification d'équipe", () => {
  let browser: Browser;
  let ownerContext: BrowserContext;
  let inviteeContext: BrowserContext;
  let ownerPage: Page;
  let inviteePage: Page;

  test.beforeAll(async () => {
    browser = await chromium.launch();
    ownerContext = await browser.newContext();
    inviteeContext = await browser.newContext();
    ownerPage = await ownerContext.newPage();
    inviteePage = await inviteeContext.newPage();
  });

  test.afterAll(async () => {
    await ownerContext.close();
    await inviteeContext.close();
    await browser.close();
  });

  test("un OWNER invite un COLLABORATOR, qui accepte et reçoit une notification d'équipe", async () => {
    await onboardOrganization(ownerPage, "e2e007owner");
    const inviteeEmail = uniqueEmail("e2e007invitee");

    // 1. Le propriétaire invite le futur collaborateur.
    await ownerPage.goto("/team");
    await expect(ownerPage.getByRole("heading", { name: "Équipe" })).toBeVisible();
    await ownerPage.getByLabel("Email").fill(inviteeEmail);
    await ownerPage.getByLabel("Rôle").selectOption("COLLABORATOR");
    await ownerPage.getByRole("button", { name: "Envoyer l'invitation" }).click();
    await expect(ownerPage.getByText(`Invitation envoyée à ${inviteeEmail}.`)).toBeVisible();
    await expect(ownerPage.getByText(inviteeEmail).first()).toBeVisible();

    // 2. L'invité reçoit l'email, voit l'aperçu public, puis crée son compte.
    const invitationLink = await waitForEmailLink(inviteeEmail);
    await inviteePage.goto(invitationLink);
    await expect(inviteePage.getByRole("heading", { name: "Invitation à rejoindre une organisation" })).toBeVisible();
    await expect(inviteePage.getByText(inviteeEmail)).toBeVisible();
    await inviteePage.getByRole("link", { name: "Créer un compte" }).click();

    await expect(inviteePage).toHaveURL(/\/register/);
    await inviteePage.getByLabel("Adresse email").fill(inviteeEmail);
    await inviteePage.getByLabel("Mot de passe", { exact: true }).fill(TEST_PASSWORD);
    await inviteePage.getByLabel(/J'accepte les/).check();
    await inviteePage.getByRole("button", { name: "Créer mon compte" }).click();
    await expect(inviteePage).toHaveURL(/\/login\?registered=1/);

    const verificationLink = await waitForEmailLink(inviteeEmail);
    await inviteePage.goto(verificationLink);
    await expect(inviteePage.getByRole("heading", { name: "Email vérifié" })).toBeVisible();
    await inviteePage.getByRole("link", { name: "Se connecter" }).click();

    await inviteePage.getByLabel("Adresse email").fill(inviteeEmail);
    await inviteePage.getByLabel("Mot de passe", { exact: true }).fill(TEST_PASSWORD);
    await inviteePage.getByRole("button", { name: "Se connecter" }).click();
    await expect(inviteePage).toHaveURL("/");

    // 3. De retour sur le lien d'invitation, maintenant authentifié : acceptation.
    await inviteePage.goto(invitationLink);
    await inviteePage.getByRole("button", { name: "Accepter l'invitation" }).click();
    await expect(inviteePage.getByRole("heading", { name: "Invitation acceptée" })).toBeVisible();

    // 4. L'invité appartient désormais à deux organisations - bascule vers celle du
    // propriétaire.
    await inviteePage.goto("/select-organization");
    await expect(inviteePage.getByText("Collaborateur")).toBeVisible();
    await inviteePage.getByText("Collaborateur").click();
    await expect(inviteePage).toHaveURL("/dashboard");

    // Un COLLABORATOR n'a jamais accès à la gestion d'équipe (confort d'affichage, revalidé
    // côté backend par ailleurs - App\Shared\Security\OrganizationPermissionVoter).
    await expect(inviteePage.getByRole("link", { name: "Équipe" })).toHaveCount(0);

    // 5. Le propriétaire voit désormais le nouveau membre dans la liste.
    await ownerPage.reload();
    await expect(ownerPage.getByText(inviteeEmail).first()).toBeVisible();

    // 6. Le propriétaire envoie une notification d'équipe au nouveau membre. getByLabel()
    // seul est ambigu ici : le rôle (select) et le destinataire (checkbox) portent tous deux
    // l'email comme nom accessible - getByRole cible sans ambiguïté la checkbox.
    await ownerPage.getByRole("checkbox", { name: inviteeEmail }).check();
    await ownerPage.getByLabel("Message").fill("Bienvenue dans l'équipe !");
    await ownerPage.getByRole("button", { name: "Envoyer", exact: true }).click();
    await expect(ownerPage.getByText("Notification envoyée.")).toBeVisible();

    // 7. L'invité la reçoit et peut la marquer comme lue.
    await inviteePage.goto("/notifications");
    await expect(inviteePage.getByText("Bienvenue dans l'équipe !")).toBeVisible();
    await expect(inviteePage.getByText("Non lue")).toBeVisible();
    await inviteePage.getByText("Bienvenue dans l'équipe !").click();
    await expect(inviteePage.getByText("Non lue")).toHaveCount(0);
  });
});
