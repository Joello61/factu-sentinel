import { ComplianceAnalysisDetail } from "../ComplianceAnalysisDetail";

export default async function ComplianceAnalysisDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <ComplianceAnalysisDetail analysisId={id} />;
}
