import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { onboardOrganization } from "../support/onboarding";
import { syntheticSiren } from "../support/testData";

/**
 * E2E-002 (docs/09-test-strategy.md, section 38) : entreprise configurée -> création d'un
 * client professionnel français avec SIREN -> saisie manuelle d'une facture conforme ->
 * analyse -> résultat CONFORME (US-CUSTOMER-001/002, US-INVOICE-002, US-COMPLIANCE-002).
 */
test.describe("E2E-002 - Facture conforme", () => {
  test("une facture correctement renseignée pour un client professionnel avec SIREN est analysée CONFORME", async ({
    page,
  }) => {
    await onboardOrganization(page, "e2e002");

    await page.goto("/customers/new");
    await page.getByLabel("Type de client").selectOption("PROFESSIONNEL_FRANCAIS");
    await page.getByLabel("Nom ou raison sociale").fill("Client Conforme SARL");
    await page.getByLabel("SIREN").fill(syntheticSiren());
    await page.getByLabel("Pays").fill("FR");
    await page.getByRole("button", { name: "Enregistrer" }).click();
    await expect(page).toHaveURL("/customers");
    // Deux rendus responsive coexistent dans le DOM (docs/11-frontend-design-system.md,
    // section 24 : tableau desktop + liste de cartes mobile, un seul visible selon le
    // viewport réel - contrairement à getByRole, getByText ne filtre pas par visibilité,
    // filter({visible:true}) est le pattern recommandé par Playwright pour ce cas précis).
    await expect(page.getByText("Client Conforme SARL").filter({ visible: true })).toBeVisible();

    await page.goto("/invoices/new");
    await page.getByLabel("Client").selectOption({ label: "Client Conforme SARL" });
    await page.getByLabel("Nature de l'opération").selectOption("PRESTATION_SERVICE");
    await page.getByLabel("Date d'émission").fill("2026-08-20");
    await page.getByLabel("Description").fill("Prestation de conseil");
    await page.getByLabel("Quantité").fill("1");
    await page.getByLabel("Prix unitaire HT (€)").fill("1000");
    await page.getByRole("button", { name: "Enregistrer la facture" }).click();

    await expect(page).toHaveURL(/\/invoices\/[0-9a-f-]+$/);
    await expect(page.getByText("Cette facture n'a pas encore été analysée.")).toBeVisible();

    const axeInvoiceDetail = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeInvoiceDetail.violations).toEqual([]);

    await page.getByRole("button", { name: "Lancer l'analyse" }).click();
    // Résultat global + findings mention-siren-client/mention-categorie-operation, tous
    // CONFORME (App\Compliance\Engine\Integration\ComplianceEngineTest::testSirenPresentIsConforme) -
    // .first() cible le badge de résultat global, affiché en premier dans le DOM
    // (ComplianceResultSummary), sans dépendre du nombre exact de findings individuels.
    await expect(page.getByText("Conforme", { exact: true }).first()).toBeVisible();
    await expect(page.getByText("Non conforme", { exact: true })).toHaveCount(0);

    const axeResult = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeResult.violations).toEqual([]);
  });
});
