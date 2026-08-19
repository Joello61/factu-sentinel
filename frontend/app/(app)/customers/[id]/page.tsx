import { CustomerForm } from "../CustomerForm";

/**
 * `params` est une Promise depuis Next.js 15 (vérifié dans la documentation Next.js 16
 * embarquée, node_modules/next/dist/docs/01-app/03-api-reference/03-file-conventions/dynamic-routes.md) :
 * ce composant reste un Server Component non-"use client" pour pouvoir l'attendre
 * directement, puis passe un id: string simple à CustomerForm (client component).
 */
export default async function EditCustomerPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return <CustomerForm customerId={id} />;
}
