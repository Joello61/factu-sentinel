import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import { AnalyticsDashboard } from "./AnalyticsDashboard";
import type { PlatformAnalyticsTrendPoint } from "@/lib/api/platformAdminTypes";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => (body === undefined ? "" : JSON.stringify(body)),
  } as Response;
}

/** Même contrat de fenêtre que App\PlatformAdmin\Service\PlatformAnalyticsTrendAggregator - 90 buckets, un par jour. */
function buildEmptyPoints(): PlatformAnalyticsTrendPoint[] {
  const points: PlatformAnalyticsTrendPoint[] = [];
  const today = new Date("2026-08-26T00:00:00Z");

  for (let i = 89; i >= 0; i -= 1) {
    const date = new Date(today);
    date.setUTCDate(date.getUTCDate() - i);
    points.push({
      date: date.toISOString().slice(0, 10),
      organizations_created: 0,
      users_created: 0,
      compliance_analyses_count: 0,
      compliance_rate: "0",
    });
  }

  return points;
}

function stubFetch(summary: unknown, points: PlatformAnalyticsTrendPoint[]): ReturnType<typeof vi.fn> {
  const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    if (url.endsWith("/api/v1/platform-admin/analytics/summary")) {
      return jsonResponse(200, { data: summary });
    }
    if (url.endsWith("/api/v1/platform-admin/analytics/trends")) {
      return jsonResponse(200, { data: { points }, meta: { window_days: 90 } });
    }
    throw new Error(`Unexpected fetch to ${url}`);
  });
  vi.stubGlobal("fetch", fetchMock);

  return fetchMock;
}

describe("AnalyticsDashboard", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows the loading state before data arrives", () => {
    vi.stubGlobal("fetch", vi.fn(() => new Promise(() => {})));

    render(<AnalyticsDashboard />);

    expect(screen.getByText("Chargement…")).toBeInTheDocument();
  });

  it("renders the summary and the accessible data tables once loaded", async () => {
    const points = buildEmptyPoints();
    points[points.length - 1] = {
      ...points[points.length - 1],
      organizations_created: 2,
      users_created: 5,
      compliance_analyses_count: 10,
      compliance_rate: "0.7",
    };

    stubFetch(
      {
        organizations_count: 12,
        users_count: 34,
        compliance_analyses_count: 56,
        compliance_rate: "0.75",
      },
      points,
    );

    render(<AnalyticsDashboard />);

    await screen.findByText("12");
    expect(screen.getByText("34")).toBeInTheDocument();
    expect(screen.getByText("56")).toBeInTheDocument();
    expect(screen.getByText("75.0 %")).toBeInTheDocument();

    // Table accessible (alternative textuelle, sr-only) : toujours dans le DOM même si le
    // graphique lui-même dépend d'un ResizeObserver non disponible en jsdom.
    const growthTable = screen.getByRole("table", { name: "Organisations et utilisateurs créés par jour, 90 derniers jours" });
    expect(within(growthTable).getByText("2")).toBeInTheDocument();
    expect(within(growthTable).getByText("5")).toBeInTheDocument();

    const complianceTable = screen.getByRole("table", { name: "Analyses de conformité et taux de conformité par jour, 90 derniers jours" });
    expect(within(complianceTable).getByText("10")).toBeInTheDocument();
    expect(within(complianceTable).getByText("70.0 %")).toBeInTheDocument();
  });

  it("shows an explicit empty state instead of a blank chart when there is no data at all", async () => {
    stubFetch(
      { organizations_count: 0, users_count: 0, compliance_analyses_count: 0, compliance_rate: "0" },
      buildEmptyPoints(),
    );

    render(<AnalyticsDashboard />);

    await screen.findByText("Aucune nouvelle organisation ni aucun nouvel utilisateur sur les 90 derniers jours.");
    expect(
      screen.getByText("Aucune analyse de conformité complétée sur les 90 derniers jours."),
    ).toBeInTheDocument();
    expect(screen.queryByRole("img", { name: /Graphique d'évolution/ })).not.toBeInTheDocument();
  });

  it("shows an error message when the API calls fail", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => jsonResponse(500, { error: { code: "UNKNOWN_ERROR", message: "boom", details: [], request_id: null } })),
    );

    render(<AnalyticsDashboard />);

    await screen.findByRole("alert");
    expect(screen.getByText("Impossible de charger les statistiques d'usage pour le moment.")).toBeInTheDocument();
  });
});
