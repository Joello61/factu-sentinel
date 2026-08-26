import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

/**
 * Garde grossière, confort d'expérience uniquement (../CLAUDE.md frontend, section 6 ;
 * plan Phase 2) : ne vérifie que la PRÉSENCE du cookie de refresh, jamais sa validité ni le
 * JWT lui-même - Symfony reste la seule autorité (AuthProvider redemande la session au
 * montage et redirige si le refresh échoue malgré tout).
 *
 * Nommé proxy.ts, pas middleware.ts : le fichier "middleware" est déprécié et renommé
 * "proxy" en Next.js 16 (vérifié dans node_modules/next/dist/docs/01-app/03-api-reference/
 * 03-file-conventions/proxy.md), pas une convention supposée depuis une version antérieure.
 */
const REFRESH_COOKIE_NAME = 'refresh_token';
// Réservées aux comptes non connectés : un compte déjà authentifié y est redirigé vers "/".
const PUBLIC_PATHS = ['/login', '/register'];
// Accessibles quel que soit l'état de session, jamais redirigées dans un sens ou l'autre -
// /verify-email/{id} est le lien reçu par email (docs/08-api-specification.md, section 7) :
// un compte peut le suivre avant toute connexion (inscription, US-AUTH-001) ou une fois déjà
// connecté (session restaurée depuis un onglet précédent), les deux cas doivent atteindre la
// page réellement, jamais rebondir vers /login ou /. /invitations/{token} (Phase 14) suit le
// même principe - trouvé manquant ici par le premier run réel de e2e-007-team-invitation.spec.ts
// (l'invité anonyme cliquant le lien reçu par email était systématiquement rebondi vers
// /login avant de jamais voir l'aperçu de l'invitation, l'endpoint public
// GET /invitations/{token} lui-même n'étant jamais en cause).
const ALWAYS_ACCESSIBLE_PATHS = ['/verify-email', '/invitations'];

// Phase 15 (ADR-009) - surface Platform Administration, garde indépendante de celle du
// tenant ci-dessus : jamais le même cookie (platform_admin_refresh_token, distinct de
// refresh_token), jamais mélangée à PUBLIC_PATHS/ALWAYS_ACCESSIBLE_PATHS qui ne concernent
// que l'espace tenant. Un onglet peut porter les deux sessions simultanément (deux cookies
// distincts) sans que l'une n'interfère avec la garde de l'autre.
const PLATFORM_ADMIN_REFRESH_COOKIE_NAME = 'platform_admin_refresh_token';
const PLATFORM_ADMIN_PREFIX = '/platform-admin';
const PLATFORM_ADMIN_PUBLIC_PATHS = ['/platform-admin/login'];

function matchesPath(pathname: string, paths: string[]): boolean {
  return paths.some((path) => pathname === path || pathname.startsWith(`${path}/`));
}

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (matchesPath(pathname, [PLATFORM_ADMIN_PREFIX])) {
    const hasPlatformAdminSession = request.cookies.has(PLATFORM_ADMIN_REFRESH_COOKIE_NAME);
    const isPlatformAdminPublicPath = matchesPath(pathname, PLATFORM_ADMIN_PUBLIC_PATHS);

    if (!hasPlatformAdminSession && !isPlatformAdminPublicPath) {
      return NextResponse.redirect(new URL('/platform-admin/login', request.url));
    }

    if (hasPlatformAdminSession && isPlatformAdminPublicPath) {
      return NextResponse.redirect(new URL('/platform-admin/organizations', request.url));
    }

    return NextResponse.next();
  }

  if (matchesPath(pathname, ALWAYS_ACCESSIBLE_PATHS)) {
    return NextResponse.next();
  }

  const hasSession = request.cookies.has(REFRESH_COOKIE_NAME);
  const isPublicPath = matchesPath(pathname, PUBLIC_PATHS);

  if (!hasSession && !isPublicPath) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  if (hasSession && isPublicPath) {
    return NextResponse.redirect(new URL('/', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/((?!api|_next/static|_next/image|favicon.ico).*)'],
};
