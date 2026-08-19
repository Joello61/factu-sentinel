import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { Button } from "./Button";

// docs/11-frontend-design-system.md, section 21 : bouton désactivé pendant le chargement,
// pour éviter une double soumission.
describe("Button", () => {
  it("renders its children", () => {
    render(<Button>Se connecter</Button>);

    expect(screen.getByRole("button", { name: "Se connecter" })).toBeInTheDocument();
  });

  it("disables itself and reports aria-busy while loading", () => {
    render(<Button loading>Se connecter</Button>);

    const button = screen.getByRole("button");
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute("aria-busy", "true");
  });

  it("stays enabled when not loading", () => {
    render(<Button>Se connecter</Button>);

    expect(screen.getByRole("button")).toBeEnabled();
  });
});
