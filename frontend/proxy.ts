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
const PUBLIC_PATHS = ['/login', '/register'];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const hasSession = request.cookies.has(REFRESH_COOKIE_NAME);
  const isPublicPath = PUBLIC_PATHS.some(
    (path) => pathname === path || pathname.startsWith(`${path}/`),
  );

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
