import { OrganizationDetail } from "../OrganizationDetail";

export default async function OrganizationDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <OrganizationDetail organizationId={id} />;
}
