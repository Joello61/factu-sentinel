import { expect, type Page } from "@playwright/test";
import { waitForEmailLink } from "./mailpit";
import { TEST_PASSWORD, uniqueEmail } from "./testData";

export interface OnboardedAccount {
  email: string;
  password: string;
}

/**
 * Parcours commun aux specs E2E-002 à E2E-005 : inscription -> vérification email (Mailpit)
 * -> connexion -> configuration entreprise (assujettie et redevable de la TVA, PME/TPE/micro)
 * -> diagnostic affiché. Reproduit intégralement E2E-001 (docs/09-test-strategy.md, section
 * 38) via l'UI réelle, jamais un appel API direct - c'est la seule façon d'obtenir une
 * organisation utilisable par les parcours suivants sans dupliquer cette séquence dans
 * chaque spec ni contourner une étape testée par ailleurs (e2e-001-onboarding.spec.ts).
 */
export async function onboardOrganization(page: Page, emailPrefix: string): Promise<OnboardedAccount> {
  const email = uniqueEmail(emailPrefix);
  const password = TEST_PASSWORD;

  await page.goto("/register");
  await page.getByLabel("Adresse email").fill(email);
  await page.getByLabel("Mot de passe", { exact: true }).fill(password);
  await page.getByLabel(/J'accepte les/).check();
  await page.getByRole("button", { name: "Créer mon compte" }).click();
  await expect(page).toHaveURL(/\/login\?registered=1/);

  const verificationLink = await waitForEmailLink(email);
  await page.goto(verificationLink);
  await expect(page.getByRole("heading", { name: "Email vérifié" })).toBeVisible();
  await page.getByRole("link", { name: "Se connecter" }).click();

  await expect(page).toHaveURL(/\/login/);
  await page.getByLabel("Adresse email").fill(email);
  await page.getByLabel("Mot de passe", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Se connecter" }).click();
  await expect(page).toHaveURL("/");

  await page.goto("/company");
  await page.getByLabel("Raison sociale").fill(`Organisation ${emailPrefix}`);
  await page.getByLabel("Statut TVA").selectOption("ASSUJETTI_REDEVABLE");
  await page.getByLabel("Effectif salarié").fill("5");
  await page.getByLabel("Chiffre d'affaires annuel (€)").fill("200000");
  await page.getByLabel("Total du bilan annuel (€)").fill("100000");
  await page.getByRole("button", { name: "Enregistrer" }).click();
  await expect(page.getByText("Diagnostic d'éligibilité")).toBeVisible();

  return { email, password };
}
