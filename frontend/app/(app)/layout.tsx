'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { AppShell } from '@/components/app-shell/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';

/**
 * Garde de session pour toute l'UI authentifiée (App Shell). Confort d'expérience
 * uniquement (../../CLAUDE.md frontend, section 6) : le backend revalide systématiquement,
 * cette redirection n'est jamais le contrôle d'autorisation réel - proxy.ts fait déjà un
 * premier filtrage grossier sur la seule présence du cookie de refresh avant que cette page
 * ne soit même rendue.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === 'anonymous') {
      router.replace('/login');
    }
  }, [status, router]);

  if (status !== 'authenticated') {
    return null;
  }

  return <AppShell>{children}</AppShell>;
}
