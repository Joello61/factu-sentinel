/**
 * Montants reçus de l'API en chaîne décimale (docs/08-api-specification.md, section 18),
 * jamais utilisés pour un calcul côté client (../../CLAUDE.md frontend, section 4) — ce
 * formatteur ne sert qu'à l'affichage fr-FR avec chiffres tabulaires
 * (docs/11-frontend-design-system.md, section 7 et 53).
 */
export function formatAmount(decimalAmount: string, currency: string): string {
  return new Intl.NumberFormat("fr-FR", { style: "currency", currency }).format(Number(decimalAmount));
}
