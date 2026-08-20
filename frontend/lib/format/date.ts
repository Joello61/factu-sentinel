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

/**
 * Horodatage ISO 8601 UTC (docs/08-api-specification.md, section 17-18 : ex.
 * ComplianceAnalysis.completed_at), converti au fuseau local uniquement à l'affichage
 * (../../CLAUDE.md frontend, section 4). Distinct de formatBusinessDate() : une date métier
 * (issue_date) n'a jamais d'heure, un horodatage technique en a toujours une -- utile ici
 * pour distinguer deux analyses relancées le même jour (US-COMPLIANCE-006).
 */
export function formatTimestamp(isoTimestamp: string): string {
  return new Date(isoTimestamp).toLocaleString("fr-FR", {
    day: "numeric",
    month: "long",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}
