// Formes de réponse alignées sur docs/08-api-specification.md (section 38.2, Phase 15).
// Jamais fusionné avec lib/api/types.ts (tenant) : deux espaces d'API structurellement
// séparés (ADR-009), jamais un type partagé qui laisserait croire à une frontière commune.

export interface PlatformAdministratorSession {
  email: string;
  created_at: string;
}

export interface PlatformOrganization {
  id: string;
  legal_name: string | null;
  siren: string | null;
  country: string | null;
  created_at: string;
  suspended_at: string | null;
}

export interface PlatformOrganizationMember {
  user_id: string;
  email: string;
  role: "OWNER" | "ADMIN" | "COLLABORATOR";
}

export interface PlatformOrganizationDetail extends PlatformOrganization {
  members: PlatformOrganizationMember[];
}

export interface PlatformAuditEvent {
  event_type: string;
  entity_type: string;
  entity_id: string;
  organization_id: string | null;
  occurred_at: string;
  actor: {
    type: "USER" | "SYSTEM" | "PLATFORM_ADMIN";
    id: string | null;
  };
}

export type PlatformNotificationTargetType = "USER" | "ORGANIZATION" | "SEGMENT" | "ALL";

export interface PlatformNotificationTargetCriteria {
  vat_status?: string[];
  company_size_category?: string[];
}

export interface SendPlatformNotificationPayload {
  target_type: PlatformNotificationTargetType;
  target_id?: string;
  target_criteria?: PlatformNotificationTargetCriteria;
  message: string;
}

export interface SendPlatformNotificationResult {
  sender_type: "PLATFORM_ADMIN";
  target_type: PlatformNotificationTargetType;
  estimated_recipient_count: number;
}

export interface PlatformHealth {
  compliance_engine_failure_rate_24h: string;
  async_jobs_dead_letter_count: number;
  ai_calls_volume_24h: number;
  ai_estimated_cost_24h: string;
  api_health: "ok" | "degraded";
}

// docs/08-api-specification.md, section 38.3 (Phase 16). Résumé cumulé sur toute
// l'historique de la plateforme - sémantique volontairement distincte de
// PlatformAnalyticsTrendPoint ci-dessous (activité par jour) : ne jamais les confondre en
// affichage.
export interface PlatformAnalyticsSummary {
  organizations_count: number;
  users_count: number;
  compliance_analyses_count: number;
  compliance_rate: string;
}

export interface PlatformAnalyticsTrendPoint {
  date: string;
  organizations_created: number;
  users_created: number;
  compliance_analyses_count: number;
  compliance_rate: string;
}

export interface PlatformAnalyticsTrends {
  points: PlatformAnalyticsTrendPoint[];
}
