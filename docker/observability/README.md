# Observabilité - FactuSentinel

La stack d'observabilité (Loki, Alloy, Prometheus, Grafana, Tempo) vivait auparavant dans
ce répertoire, couplée au seul projet Compose de FactuSentinel (Phase 18). Migrée vers le
socle partagé du VPS (Phase 19, `docs/20-observability-infrastructure-migration.md`) :
configuration désormais dans le dépôt privé `github.com/Joello61/infrastructure`,
réutilisable par de futurs projets hébergés sur le même serveur.

Le contenu technique (décisions, vérifications, critère de clôture de la Phase 18) reste
documenté dans `docs/19-observability-architecture.md` - jamais dupliqué ici.

Le code applicatif qui produit les logs/métriques/traces (`App\Shared\Logging\RequestContextProcessor`,
`App\Shared\Metrics\MetricsRecorder`, `App\Shared\Controller\GetMetricsController`,
`App\Shared\Observability\Tracer`) reste inchangé, dans `backend/src/` - il ignore
complètement qui consomme ces signaux ensuite.
