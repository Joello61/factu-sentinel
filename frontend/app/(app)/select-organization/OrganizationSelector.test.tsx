import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { OrganizationSelector } from "./OrganizationSelector";

const selectOrganizationMock = vi.fn();

vi.mock("@/components/auth/AuthProvider", () => ({
  useAuth: () => ({ selectOrganization: selectOrganizationMock }),
}));

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

const ORGANIZATIONS = {
  data: [
    { organization_id: "org-a", legal_name: "Organisation A", role: "OWNER" },
    { organization_id: "org-b", legal_name: "Organisation B", role: "COLLABORATOR" },
  ],
};

describe("OrganizationSelector", () => {
  beforeEach(() => {
    selectOrganizationMock.mockClear();
    selectOrganizationMock.mockResolvedValue(undefined);
    vi.stubGlobal("fetch", vi.fn(async () => jsonResponse(200, ORGANIZATIONS)));
    // window.location.assign n'existe pas nativement dans jsdom - stub explicite pour
    // vérifier l'appel sans naviguer réellement.
    vi.stubGlobal("location", { ...window.location, assign: vi.fn() });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("lists the user's organizations with their role and switches on click", async () => {
    const user = userEvent.setup();
    render(<OrganizationSelector />);

    await waitFor(() => expect(screen.getByText("Organisation A")).toBeInTheDocument());
    expect(screen.getByText("Organisation B")).toBeInTheDocument();
    expect(screen.getByText("Propriétaire")).toBeInTheDocument();
    expect(screen.getByText("Collaborateur")).toBeInTheDocument();

    await user.click(screen.getByText("Organisation B"));

    await waitFor(() => expect(selectOrganizationMock).toHaveBeenCalledWith("org-b"));
    expect(window.location.assign).toHaveBeenCalledWith("/dashboard");
  });
});
