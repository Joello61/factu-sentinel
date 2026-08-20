import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { DashboardStatusBadge } from "./DashboardStatusBadge";
import type { DashboardGlobalStatus } from "@/lib/api/types";

const EXPECTED_LABELS: Record<DashboardGlobalStatus, string> = {
  AUCUNE_ANALYSE: "Aucune analyse",
  CONFORME: "Conforme",
  AVERTISSEMENT: "Avertissement",
  ATTENTION_REQUISE: "Attention requise",
};

// docs/08-api-specification.md, section 33 (décision produit Phase 9) : couleur + icône +
// label, jamais la couleur seule, même discipline que ComplianceResultBadge.
describe("DashboardStatusBadge", () => {
  for (const [status, label] of Object.entries(EXPECTED_LABELS) as [DashboardGlobalStatus, string][]) {
    it(`renders the label and icon for ${status}`, () => {
      render(<DashboardStatusBadge status={status} />);

      expect(screen.getByText(label)).toBeInTheDocument();
      const icon = document.querySelector("svg[aria-hidden='true']");
      expect(icon).not.toBeNull();
    });
  }

  it("never renders AUCUNE_ANALYSE with the same classes as CONFORME", () => {
    // AUCUNE_ANALYSE ne doit jamais être confondu visuellement avec CONFORME (US-DASHBOARD-001 :
    // distinguer "rien analysé" de "tout est conforme").
    const { container: aucuneAnalyse } = render(<DashboardStatusBadge status="AUCUNE_ANALYSE" />);
    const { container: conforme } = render(<DashboardStatusBadge status="CONFORME" />);

    expect(aucuneAnalyse.querySelector("span")?.className).not.toEqual(conforme.querySelector("span")?.className);
  });
});
