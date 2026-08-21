/**
 * Données uniques par exécution (email, SIREN synthétique) : chaque spec crée son propre
 * compte/organisation de bout en bout (aucune fixture pré-chargée, section 54 de
 * docs/09-test-strategy.md n'étant pas applicable ici - ce sont des parcours E2E, pas des
 * tests d'intégration ciblés), donc rejouable sans collision sur une base persistante.
 */
export function uniqueEmail(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 100_000)}@example.test`;
}

/** SIREN syntaxiquement valide (9 chiffres) - jamais un vrai numéro d'entreprise. */
export function syntheticSiren(): string {
  const suffix = String(Date.now()).slice(-8);
  return `1${suffix}`;
}

export const TEST_PASSWORD = "a-sufficiently-long-password-123";
