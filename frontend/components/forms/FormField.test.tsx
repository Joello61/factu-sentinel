import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { FormField } from "./FormField";

// docs/11-frontend-design-system.md, section 23 : label associé programmatiquement,
// aria-describedby vers l'erreur/l'aide, jamais la couleur de bordure seule.
describe("FormField", () => {
  it("associates the label with the input", () => {
    render(<FormField label="Adresse email" />);

    expect(screen.getByLabelText("Adresse email")).toBeInTheDocument();
  });

  it("exposes the error via role=alert and aria-describedby/aria-invalid", () => {
    render(<FormField label="Mot de passe" error="15 caractères minimum." />);

    const input = screen.getByLabelText("Mot de passe");
    const error = screen.getByRole("alert");

    expect(error).toHaveTextContent("15 caractères minimum.");
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input.getAttribute("aria-describedby")).toContain(error.id);
  });

  it("links the hint via aria-describedby without marking the field invalid", () => {
    render(<FormField label="Mot de passe" hint="15 caractères minimum." />);

    const input = screen.getByLabelText("Mot de passe");
    const hint = screen.getByText("15 caractères minimum.");

    expect(input).not.toHaveAttribute("aria-invalid");
    expect(input.getAttribute("aria-describedby")).toContain(hint.id);
  });
});
