import type { LucideIcon } from "lucide-react";

/**
 * Tuile de statistique partagée entre les écrans Platform Admin - extraite de
 * app/(platform-admin)/platform-admin/(protected)/health/HealthDashboard.tsx (Phase 15) au
 * moment où un deuxième écran (Analytics, Phase 16) en a eu besoin (docs/11-frontend-design-
 * system.md, section 67 : composant créé quand un pattern se répète au moins deux fois, pas
 * avant).
 */
const TONE_CLASSES = {
  success: "border-success/40 bg-success/10 text-success",
  warning: "border-warning/40 bg-warning/10 text-warning",
  error: "border-error/40 bg-error/10 text-error",
  info: "border-info/40 bg-info/10 text-info",
} as const;

export type StatTileTone = keyof typeof TONE_CLASSES;

export function StatTile({
  icon: Icon,
  tone,
  label,
  value,
}: {
  icon: LucideIcon;
  tone: StatTileTone;
  label: string;
  value: string;
}) {
  return (
    <div className={`flex flex-col gap-2 rounded-md border p-4 ${TONE_CLASSES[tone]}`}>
      <Icon aria-hidden="true" size={20} strokeWidth={1.75} />
      <span className="text-2xl font-semibold tabular-nums">{value}</span>
      <span className="text-xs font-medium">{label}</span>
    </div>
  );
}
