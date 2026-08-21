import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { onboardOrganization } from "../support/onboarding";
import { syntheticSiren } from "../support/testData";

/**
 * E2E-003 (docs/09-test-strategy.md, section 38) : facture avec SIREN client manquant ->
 * analyse -> NON_CONFORME avec explication et action de correction affichées -> correction
 * -> nouvelle analyse -> CONFORME (US-COMPLIANCE-003/004/006). Le parcours de correction le
 * plus important du MVP (../../CLAUDE.md racine, section 9 : "Pourquoi, jamais seulement
 * si.").
 */
test.describe("E2E-003 - Non conforme puis corrigée", () => {
  test("un SIREN manquant est signalé NON_CONFORME avec une action de correction menant à CONFORME après correction", async ({
    page,
  }) => {
    await onboardOrganization(page, "e2e003");

    await page.goto("/customers/new");
    await page.getByLabel("Type de client").selectOption("PROFESSIONNEL_FRANCAIS");
    await page.getByLabel("Nom ou raison sociale").fill("Client Sans Siren SARL");
    // SIREN volontairement laissé vide (REG-004).
    await page.getByLabel("Pays").fill("FR");
    await page.getByRole("button", { name: "Enregistrer" }).click();
    await expect(page).toHaveURL("/customers");

    await page.goto("/invoices/new");
    await page.getByLabel("Client").selectOption({ label: "Client Sans Siren SARL" });
    await page.getByLabel("Nature de l'opération").selectOption("PRESTATION_SERVICE");
    await page.getByLabel("Date d'émission").fill("2026-08-20");
    await page.getByLabel("Description").fill("Prestation de conseil");
    await page.getByLabel("Quantité").fill("1");
    await page.getByLabel("Prix unitaire HT (€)").fill("1000");
    await page.getByRole("button", { name: "Enregistrer la facture" }).click();
    await expect(page).toHaveURL(/\/invoices\/[0-9a-f-]+$/);

    await page.getByRole("button", { name: "Lancer l'analyse" }).click();

    // REG-004 (App\Compliance\Engine\Integration\ComplianceEngineTest::testReg004MissingSirenIsNonConforme) :
    // NON_CONFORME, avec message d'explication et action de correction toujours non vides
    // (BR-COMPLIANCE-002) - jamais un simple badge sans contexte.
    await expect(page.getByText("Non conforme", { exact: true }).first()).toBeVisible();
    await expect(page.getByText(/SIREN/i).first()).toBeVisible();

    const axeNonConforme = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeNonConforme.violations).toEqual([]);

    // Niveau 2 de la progressive disclosure (docs/11-frontend-design-system.md, section 28) :
    // "Corriger maintenant" mène directement au client concerné (resolveCorrectionLink,
    // components/compliance/ComplianceFindingCard.tsx).
    const correctionLink = page.getByRole("link", { name: "Corriger maintenant" });
    await expect(correctionLink).toBeVisible();
    await correctionLink.click();
    await expect(page).toHaveURL(/\/customers\/[0-9a-f-]+$/);

    await page.getByLabel("SIREN").fill(syntheticSiren());
    await page.getByRole("button", { name: "Enregistrer" }).click();
    await expect(page).toHaveURL("/customers");

    await page.goto("/invoices");
    // filter({visible:true}) : rendu responsive dupliqué (table desktop + carte mobile,
    // docs/11-frontend-design-system.md section 24) - une seule copie visible selon le
    // viewport réel.
    await page.getByRole("link", { name: "Consulter" }).filter({ visible: true }).click();
    await expect(page).toHaveURL(/\/invoices\/[0-9a-f-]+$/);
    await expect(page.getByRole("button", { name: "Relancer l'analyse" })).toBeVisible();
    await page.getByRole("button", { name: "Relancer l'analyse" }).click();

    await expect(page.getByText("Conforme", { exact: true }).first()).toBeVisible();
    await expect(page.getByText("Non conforme", { exact: true })).toHaveCount(0);
  });
});
