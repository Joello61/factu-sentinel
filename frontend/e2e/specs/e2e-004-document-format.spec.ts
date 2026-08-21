import path from "node:path";
import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { onboardOrganization } from "../support/onboarding";
import { syntheticSiren } from "../support/testData";

/**
 * E2E-004 (docs/09-test-strategy.md, section 38) : import d'un document PDF simple ->
 * traitement asynchrone -> analyse -> finding explicite sur la non-conformité du format
 * (US-COMPLIANCE-005, REG-006). Réutilise le fixture backend existant
 * (backend/tests/Fixtures/Document/pdf-simple.pdf), jamais dupliqué.
 *
 * Client avec SIREN renseigné (comme e2e-002) : seul le finding de format doit être
 * NON_CONFORME ici, sans le confondre avec REG-004 (SIREN manquant, testé en e2e-003).
 */
const PDF_SIMPLE_FIXTURE = path.resolve(
  __dirname,
  "../../../backend/tests/Fixtures/Document/pdf-simple.pdf",
);

test.describe("E2E-004 - Import de document, format non conforme", () => {
  test("un PDF simple importé produit un finding explicite de non-conformité de format", async ({ page }) => {
    await onboardOrganization(page, "e2e004");

    await page.goto("/customers/new");
    await page.getByLabel("Type de client").selectOption("PROFESSIONNEL_FRANCAIS");
    await page.getByLabel("Nom ou raison sociale").fill("Client Document SARL");
    await page.getByLabel("SIREN").fill(syntheticSiren());
    await page.getByLabel("Pays").fill("FR");
    await page.getByRole("button", { name: "Enregistrer" }).click();
    await expect(page).toHaveURL("/customers");

    await page.goto("/invoices/new");
    await page.getByLabel("Client").selectOption({ label: "Client Document SARL" });
    await page.getByLabel("Nature de l'opération").selectOption("PRESTATION_SERVICE");
    await page.getByLabel("Date d'émission").fill("2026-08-20");
    await page.getByLabel("Description").fill("Prestation de conseil");
    await page.getByLabel("Quantité").fill("1");
    await page.getByLabel("Prix unitaire HT (€)").fill("1000");
    await page.getByRole("button", { name: "Enregistrer la facture" }).click();
    await expect(page).toHaveURL(/\/invoices\/[0-9a-f-]+$/);

    await page.getByLabel("Importer un document (PDF ou Factur-X)").setInputFiles(PDF_SIMPLE_FIXTURE);

    // Traitement asynchrone réel (worker Messenger + Mustang, docker-compose.e2e.yml) :
    // polling frontend jusqu'à 120s (frontend/lib/hooks/useDocumentPolling.ts) - délai
    // Playwright aligné, jamais le défaut de 10s de la config.
    await expect(page.getByText("Traité", { exact: true })).toBeVisible({ timeout: 60_000 });

    const axeWithDocument = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag22aa"]).analyze();
    expect(axeWithDocument.violations).toEqual([]);

    await page.getByRole("button", { name: "Lancer l'analyse" }).click();

    // REG-006 (DocumentFormatRuleCheckerTest) : PDF_SIMPLE -> NON_CONFORME, message explicite
    // distinguant un PDF non structuré d'une facture électronique conforme
    // (US-COMPLIANCE-005) - jamais un état incertain ou silencieusement ignoré.
    await expect(page.getByText("Non conforme", { exact: true }).first()).toBeVisible();
    await expect(page.getByText(/format|structur/i).first()).toBeVisible();
  });
});
