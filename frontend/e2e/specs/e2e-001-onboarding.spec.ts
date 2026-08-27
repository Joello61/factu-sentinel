import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { waitForEmailLink } from "../support/mailpit";
import { TEST_PASSWORD, uniqueEmail } from "../support/testData";

/**
 * E2E-001 (docs/09-test-strategy.md, section 38) : inscription -> configuration entreprise
 * (statut TVA, taille) -> diagnostic d'éligibilité affiché (US-ONBOARDING-001,
 * US-COMPANY-001/002, US-COMPLIANCE-001). Écrit à la main plutôt que via
 * support/onboarding.ts (réutilisé par les 4 autres specs) : c'est ce parcours précis qui
 * est sous test ici, pas seulement un pré-requis d'un autre scénario.
 */
test.describe("E2E-001 - Onboarding", () => {
  test("un nouvel utilisateur peut créer un compte, le vérifier, configurer son entreprise et voir son diagnostic", async ({
    page,
  }) => {
    const email = uniqueEmail("e2e001");

    await page.goto("/register");
    await page.getByLabel("Adresse email").fill(email);
    await page.getByLabel("Mot de passe", { exact: true }).fill(TEST_PASSWORD);
    await page.getByLabel(/J'accepte les/).check();
    await page.getByRole("button", { name: "Créer mon compte" }).click();

    await expect(page).toHaveURL(/\/login\?registered=1/);
    await expect(page.getByText("Compte créé. Vous pouvez maintenant vous connecter.")).toBeVisible();

    const verificationLink = await waitForEmailLink(email);
    await page.goto(verificationLink);
    await expect(page.getByRole("heading", { name: "Email vérifié" })).toBeVisible();
    await expect(
      page.getByText("Votre adresse email est confirmée. Vous pouvez maintenant vous connecter."),
    ).toBeVisible();

    const axeVerify = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeVerify.violations).toEqual([]);

    await page.getByRole("link", { name: "Se connecter" }).click();
    await expect(page).toHaveURL(/\/login\?verified=1/);
    await expect(page.getByText("Adresse email confirmée. Vous pouvez maintenant vous connecter.")).toBeVisible();

    await page.getByLabel("Adresse email").fill(email);
    await page.getByLabel("Mot de passe", { exact: true }).fill(TEST_PASSWORD);
    await page.getByRole("button", { name: "Se connecter" }).click();
    await expect(page).toHaveURL("/");

    await page.getByRole("link", { name: "Entreprise" }).click();
    await expect(page).toHaveURL("/company");

    await page.getByLabel("Raison sociale").fill("Entreprise E2E-001");
    await page.getByLabel("Statut TVA").selectOption("ASSUJETTI_REDEVABLE");
    await page.getByLabel("Effectif salarié").fill("5");
    await page.getByLabel("Chiffre d'affaires annuel (€)").fill("200000");
    await page.getByLabel("Total du bilan annuel (€)").fill("100000");

    // Navigation clavier scriptée (plan Phase 11) : le bouton d'enregistrement est atteignable
    // et activable au clavier, jamais uniquement à la souris.
    await page.getByLabel("Total du bilan annuel (€)").press("Tab");
    await expect(page.getByRole("button", { name: "Enregistrer" })).toBeFocused();
    await page.keyboard.press("Enter");

    const diagnosticHeading = page.getByText("Diagnostic d'éligibilité");
    await expect(diagnosticHeading).toBeVisible();
    // REG-009 (docs/09-test-strategy.md, section 9 ; 02-regulatory-study.md, section 5) :
    // calendrier PME/TPE/micro, émission à partir du 1er septembre 2027
    // (formatBusinessDate, frontend/lib/format/date.ts : jour numérique + mois long fr-FR).
    await expect(page.getByText("1 septembre 2027")).toBeVisible();

    const axeCompany = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeCompany.violations).toEqual([]);
  });
});
