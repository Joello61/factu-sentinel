import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { Dashboard } from "./Dashboard";
import type { Dashboard as DashboardData } from "@/lib/api/types";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

function dashboardFixture(overrides: Partial<DashboardData>): DashboardData {
  return {
    global_status: "CONFORME",
    open_issues_count: 0,
    warnings_count: 0,
    recent_analyses: [],
    recommended_actions: [],
    ...overrides,
  };
}

describe("Dashboard", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("orients the user toward the diagnostic and a first invoice when AUCUNE_ANALYSE", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse(200, { data: dashboardFixture({ global_status: "AUCUNE_ANALYSE" }) }));

    render(<Dashboard />);

    await waitFor(() => expect(screen.getByText(/aucune analyse de conformité/i)).toBeInTheDocument());
    expect(screen.getByRole("link", { name: /comprendre mon calendrier/i })).toHaveAttribute("href", "/diagnostic");
    expect(screen.getByRole("link", { name: /analyser une première facture/i })).toHaveAttribute("href", "/invoices/new");
  });

  it("shows a positive empty state when there are no issues at all", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, { data: dashboardFixture({ global_status: "CONFORME", open_issues_count: 0, warnings_count: 0 }) }),
    );

    render(<Dashboard />);

    await waitFor(() => expect(screen.getByText(/aucun problème détecté sur vos dernières analyses/i)).toBeInTheDocument());
  });

  it("renders open issues and warnings counts separately for ATTENTION_REQUISE", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: dashboardFixture({
          global_status: "ATTENTION_REQUISE",
          open_issues_count: 2,
          warnings_count: 1,
          recommended_actions: [{ message: "Renseignez le SIREN de votre client.", related_analysis_id: "analysis-1" }],
        }),
      }),
    );

    render(<Dashboard />);

    await waitFor(() => expect(screen.getByText("Attention requise")).toBeInTheDocument());
    expect(screen.getByText("2")).toBeInTheDocument();
    expect(screen.getByText("1")).toBeInTheDocument();
    expect(screen.getByText("Renseignez le SIREN de votre client.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /consulter/i })).toHaveAttribute("href", "/history/analysis-1");
  });

  it("renders an AVERTISSEMENT status distinctly from ATTENTION_REQUISE", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, { data: dashboardFixture({ global_status: "AVERTISSEMENT", open_issues_count: 0, warnings_count: 3 }) }),
    );

    render(<Dashboard />);

    await waitFor(() => expect(screen.getByText("Avertissement")).toBeInTheDocument());
    expect(screen.queryByText("Attention requise")).not.toBeInTheDocument();
  });

  it("shows an error message when the dashboard cannot be loaded", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse(500, { error: { code: "INTERNAL", message: "boom", details: [], request_id: null } }));

    render(<Dashboard />);

    await waitFor(() => expect(screen.getByRole("alert")).toBeInTheDocument());
  });
});
