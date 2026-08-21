import { defineConfig, devices } from "@playwright/test";

/**
 * Phase 11 (docs/12-roadmap.md) - E2E-001 à E2E-005 (docs/09-test-strategy.md, section 38).
 * Exécuté contre la pile complète démarrée par
 * `docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d` (jamais lancé par
 * Playwright lui-même via `webServer` - la pile est multi-conteneurs : Nginx, Symfony,
 * Next.js, Postgres, Redis, Mustang, Mailpit - hors de portée d'un simple process enfant).
 *
 * Chromium uniquement (décision produit, plan Phase 11) : Firefox/WebKit ajoutés plus tard
 * seulement si la stratégie de test l'exige explicitement.
 *
 * `next dev` (jamais un build de production) : voir ../CLAUDE.md section 2 pour le bug
 * amont Next.js 16.0.x-16.3.1 documenté sur "next build" - timeouts volontairement généreux
 * ci-dessous, le serveur de dev compilant chaque route à la demande au premier accès.
 */
export default defineConfig({
  testDir: "./specs",
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: process.env.CI ? [["html", { outputFolder: "../playwright-report" }], ["list"]] : "list",
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://localhost:8080",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    navigationTimeout: 30_000,
    actionTimeout: 15_000,
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
