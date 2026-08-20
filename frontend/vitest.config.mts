import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import tsconfigPaths from 'vite-tsconfig-paths';

// Setup Vitest recommandé par la documentation officielle Next.js pour cette version
// (node_modules/next/dist/docs/01-app/02-guides/testing/vitest.md) : les Server Components
// asynchrones (ex. app/(public)/login/page.tsx) n'y sont pas testables - ils restent
// couverts par la vérification manuelle en navigateur, jamais par un test Vitest qui
// échouerait silencieusement à les exercer réellement.
export default defineConfig({
  plugins: [tsconfigPaths(), react()],
  test: {
    environment: 'jsdom',
    // Requis pour que @testing-library/react enregistre son nettoyage automatique du DOM
    // entre les tests (elle s'accroche au afterEach global) - sans quoi le DOM s'accumule
    // d'un test à l'autre au sein d'un même fichier (constaté : "Found multiple elements").
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
  },
});
