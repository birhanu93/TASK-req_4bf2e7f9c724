# Session authentication

## How sessions are carried

Session tokens are minted by `POST /api/auth/select-role` and
`POST /api/auth/switch-role`. Each response does two things:

1. Returns the token in the JSON body (used by server-to-server clients and
   the existing test suite).
2. Sets a cookie — `workforce_session` — with these attributes:

   | Attribute | Value | Why |
   |-----------|-------|-----|
   | `HttpOnly` | true | JavaScript cannot read it, so XSS cannot exfiltrate the token |
   | `SameSite` | `Strict` | Browser never attaches the cookie to cross-site requests, which eliminates the CSRF vector for state-changing endpoints |
   | `Secure` | configurable via `SESSION_COOKIE_SECURE` (default `true`) | Cookie is only sent over HTTPS in production |
   | `Path` | `/` | Cookie scoped to the whole origin |
   | `Expires` | `now + SESSION_TTL_SECONDS` (8 hours) | Matches the server-side session TTL |

`POST /api/auth/logout` clears the cookie and revokes the server-side
session record.

The SPA uses `credentials: 'include'` on every `fetch` call so the cookie
travels with requests automatically. The React app no longer persists the
token in `localStorage`; the only in-memory state is the non-secret
`role`/`userId`/`username` tuple plus a sentinel that tells the UI whether
we *believe* a session cookie is set. On page load the SPA calls
`GET /api/auth/me`; a 200 re-hydrates the session, a 401 shows the login
screen.

## Why not a JWT in memory only?

A JWT held in memory avoids XSS exfiltration only until the first time you
need to survive a page reload — at which point people reach for
`localStorage` or `sessionStorage`, both of which are JS-readable and
therefore defeated by a single XSS bug. HttpOnly cookies are unreachable
from JS even in the presence of an XSS compromise, so an attacker cannot
steal the token with a content script.

## CSRF model

`SameSite=Strict` prevents the browser from attaching the cookie to any
cross-site navigation, form POST, or `<img>` request. Combined with the
fact that every mutating endpoint demands `Content-Type: application/json`
(browsers block JS-originated cross-origin JSON without a CORS preflight,
which this API does not grant), we have no unauthenticated CSRF surface.

If in the future the API is embedded in third-party contexts, the stronger
mitigation is a double-submit token: the server sets a non-HttpOnly
`workforce_csrf` cookie alongside the session cookie, and every mutating
request must echo it back in an `X-CSRF-Token` header. That change is
local to the auth layer; the rest of the application does not care.

## Accepted credentials on incoming requests

`App\Http\Request::bearerToken()` looks at the `Authorization` header
*first*, then falls back to the `workforce_session` cookie. Both paths
call the same `AuthService::authenticate` — there is exactly one
authorisation surface.

## Tradeoffs left on the table

- **No refresh tokens.** The 8-hour TTL matches the existing session
  contract; if the UX demands longer idle without re-auth, a refresh-token
  ladder fits cleanly in the existing `auth_sessions` table.
- **No rotating session ids on privilege escalation.** `selectRole` and
  `switchRole` already rotate the token — the cookie is overwritten on
  each call, so fixation attacks are mitigated.
- **Cookie is domain-bound.** Cross-subdomain SSO would need to relax
  `SameSite` and add a domain scope. Not required for the current
  deployment topology.

## Environment knobs

| Var | Default | Purpose |
|-----|---------|---------|
| `SESSION_COOKIE_SECURE` | `true` | Emit the cookie with the `Secure` flag. Disable only for plain-HTTP local development. |
