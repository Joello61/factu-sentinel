import type { NextConfig } from "next";

// Phase 10 - Security Hardening (docs/10-security-privacy.md, section 47). Approche "sans
// nonce" retenue (documentation Next.js officielle vérifiée le 20/08/2026, guide "Content
// Security Policy" pour la 16.3.1 installée) plutôt que l'approche à base de nonce
// (proxy.ts) : cette dernière exige que TOUTES les pages passent en rendu dynamique forcé
// (Static/ISR/Partial Prerendering désactivés), un changement d'architecture bien plus
// large qu'un ajout de headers de sécurité - à reconsidérer explicitement si le produit
// développe un jour des pages réellement statiques (aucune identifiée à ce stade : toutes
// les pages de FactuSentinel sont déjà authentifiées et propres à un utilisateur, donc déjà
// sans intérêt réel à la génération statique). "unsafe-inline" reste nécessaire ici pour
// script-src/style-src sans nonce - compromis documenté par Next.js lui-même pour cette
// approche, jamais présenté comme une CSP stricte.
const isDev = process.env.NODE_ENV === "development";
const cspHeader = `
    default-src 'self';
    script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""};
    style-src 'self' 'unsafe-inline';
    img-src 'self' blob: data:;
    font-src 'self';
    connect-src 'self';
    object-src 'none';
    base-uri 'self';
    form-action 'self';
    frame-ancestors 'none';
    upgrade-insecure-requests;
`
  .replace(/\s{2,}/g, " ")
  .trim();

const nextConfig: NextConfig = {
  /* config options here */
  // "standalone" est nécessaire pour l'image Docker de production (frontend/Dockerfile,
  // stage "prod") : Next.js ne copie alors que les fichiers strictement nécessaires
  // à l'exécution (.next/standalone), sans node_modules complet.
  output: "standalone",
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "Content-Security-Policy", value: cspHeader },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          {
            key: "Permissions-Policy",
            value: "geolocation=(), camera=(), microphone=(), payment=(), usb=()",
          },
          { key: "X-Frame-Options", value: "DENY" },
        ],
      },
    ];
  },
};

export default nextConfig;
