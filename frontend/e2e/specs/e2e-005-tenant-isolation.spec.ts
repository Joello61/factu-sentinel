import { test, expect, chromium } from "@playwright/test";
import type { Browser, BrowserContext, Page } from "@playwright/test";
import { onboardOrganization } from "../support/onboarding";
import { syntheticSiren } from "../support/testData";

/**
 * E2E-005 (docs/09-test-strategy.md, section 38) : deux organisations distinctes ->
 * vérification de l'isolation complète des données à chaque étape du parcours (section 22
 * de ce document). Complète les tests d'isolation déjà bloquants au niveau
 * intégration/API (TC-TENANT-001, backend/tests/Integration/MultiTenant/TenantIsolationTest.php)
 * en vérifiant que le FRONTEND respecte réellement le contrat "ressource d'un autre tenant
 * -> 404, jamais un message de permission" (../../CLAUDE.md frontend, section 6) - un test
 * API seul ne peut pas prouver ce que l'utilisateur voit réellement à l'écran.
 *
 * Deux BrowserContext isolés (jamais deux onglets du même contexte, qui partageraient les
 * cookies) pour simuler deux organisations réellement distinctes.
 */
test.describe("E2E-005 - Isolation multi-tenant", () => {
  let browser: Browser;
  let contextA: BrowserContext;
  let contextB: BrowserContext;
  let pageA: Page;
  let pageB: Page;

  test.beforeAll(async () => {
    browser = await chromium.launch();
    contextA = await browser.newContext();
    contextB = await browser.newContext();
    pageA = await contextA.newPage();
    pageB = await contextB.newPage();
  });

  test.afterAll(async () => {
    await contextA.close();
    await contextB.close();
    await browser.close();
  });

  test("une organisation ne peut jamais consulter les ressources d'une autre organisation", async () => {
    await onboardOrganization(pageA, "e2e005a");
    await onboardOrganization(pageB, "e2e005b");

    await pageA.goto("/customers/new");
    await pageA.getByLabel("Type de client").selectOption("PROFESSIONNEL_FRANCAIS");
    await pageA.getByLabel("Nom ou raison sociale").fill("Client Organisation A");
    await pageA.getByLabel("SIREN").fill(syntheticSiren());
    await pageA.getByLabel("Pays").fill("FR");
    await pageA.getByRole("button", { name: "Enregistrer" }).click();
    await expect(pageA).toHaveURL("/customers");
    // filter({visible:true}) : rendu responsive dupliqué (table desktop + carte mobile,
    // docs/11-frontend-design-system.md section 24) - une seule copie visible selon le
    // viewport réel.
    await pageA.getByRole("link", { name: "Modifier" }).filter({ visible: true }).click();
    await expect(pageA).toHaveURL(/\/customers\/[0-9a-f-]+$/);
    const customerAId = /\/customers\/([0-9a-f-]+)/.exec(pageA.url())?.[1];
    expect(customerAId).toBeTruthy();

    await pageA.goto("/invoices/new");
    await pageA.getByLabel("Client").selectOption({ label: "Client Organisation A" });
    await pageA.getByLabel("Nature de l'opération").selectOption("PRESTATION_SERVICE");
    await pageA.getByLabel("Date d'émission").fill("2026-08-20");
    await pageA.getByLabel("Description").fill("Prestation confidentielle A");
    await pageA.getByLabel("Quantité").fill("1");
    await pageA.getByLabel("Prix unitaire HT (€)").fill("1000");
    await pageA.getByRole("button", { name: "Enregistrer la facture" }).click();
    await expect(pageA).toHaveURL(/\/invoices\/([0-9a-f-]+)/);
    const invoiceAId = /\/invoices\/([0-9a-f-]+)/.exec(pageA.url())?.[1];
    expect(invoiceAId).toBeTruthy();

    // Org B tente d'accéder directement aux ressources d'org A par URL - jamais atteintes
    // via sa propre navigation, seulement en devinant/réutilisant un identifiant.
    await pageB.goto(`/invoices/${invoiceAId}`);
    await expect(pageB.getByText("Cette facture n'existe pas ou n'est plus disponible.")).toBeVisible();
    await expect(pageB.getByText("Prestation confidentielle A")).toHaveCount(0);

    await pageB.goto(`/customers/${customerAId}`);
    await expect(pageB.getByText("Client Organisation A")).toHaveCount(0);

    // Listes également isolées : org B ne voit jamais le client/la facture d'org A dans ses
    // propres listes paginées (TC-TENANT-001, isolation appliquée au niveau repository).
    await pageB.goto("/customers");
    await expect(pageB.getByText("Client Organisation A")).toHaveCount(0);
    await pageB.goto("/invoices");
    await expect(pageB.getByText("Prestation confidentielle A")).toHaveCount(0);
  });
});
