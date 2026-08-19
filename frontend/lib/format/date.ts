/**
 * Dates métier reçues de l'API au format YYYY-MM-DD (docs/08-api-specification.md, section
 * 17), affichées en fr-FR uniquement à ce point (../../CLAUDE.md frontend, section 4).
 * timeZone: "UTC" évite qu'une date métier sans heure ne glisse d'un jour selon le fuseau
 * local du navigateur.
 */
export function formatBusinessDate(isoDate: string): string {
  return new Date(`${isoDate}T00:00:00Z`).toLocaleDateString("fr-FR", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  });
}
