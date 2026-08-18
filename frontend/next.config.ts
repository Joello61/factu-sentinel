import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  // "standalone" est nécessaire pour l'image Docker de production (frontend/Dockerfile,
  // stage "prod") : Next.js ne copie alors que les fichiers strictement nécessaires
  // à l'exécution (.next/standalone), sans node_modules complet.
  output: "standalone",
};

export default nextConfig;
