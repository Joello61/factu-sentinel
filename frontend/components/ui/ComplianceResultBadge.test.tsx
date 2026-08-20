import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { ComplianceResultBadge } from "./ComplianceResultBadge";
import type { ComplianceResult } from "@/lib/api/types";

const EXPECTED_LABELS: Record<ComplianceResult, string> = {
  CONFORME: "Conforme",
  NON_CONFORME: "Non conforme",
  AVERTISSEMENT: "Avertissement",
  A_VERIFIER: "À vérifier",
  NON_APPLICABLE: "Non applicable",
  INCERTAIN_REGLEMENTAIRE: "Incertain (réglementation)",
};

// docs/11-frontend-design-system.md, sections 5, 26, 27 : chaque état de conformité est
// systématiquement rendu avec couleur + icône + label, jamais la couleur seule.
describe("ComplianceResultBadge", () => {
  for (const [result, label] of Object.entries(EXPECTED_LABELS) as [ComplianceResult, string][]) {
    it(`renders the label and icon for ${result}`, () => {
      render(<ComplianceResultBadge result={result} />);

      expect(screen.getByText(label)).toBeInTheDocument();
      const icon = document.querySelector("svg[aria-hidden='true']");
      expect(icon).not.toBeNull();
    });
  }

  it("never renders NON_CONFORME with the same classes as CONFORME", () => {
    const { container: nonConforme } = render(<ComplianceResultBadge result="NON_CONFORME" />);
    const { container: conforme } = render(<ComplianceResultBadge result="CONFORME" />);

    expect(nonConforme.querySelector("span")?.className).not.toEqual(conforme.querySelector("span")?.className);
  });
});
