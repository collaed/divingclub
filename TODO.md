# DivingClub-Manager — TODO

## Social Login Providers

- [ ] **Yahoo OAuth** — Add Yahoo as social login provider (covers 11 members: yahoo.com, yahoo.fr, yahoo.co.uk)
- [ ] **EU Login (CAS)** — Integrate EU Login (ecas.ec.europa.eu) for EU institution staff (covers 8 members: ec.europa.eu, curia.europa.eu, eib.org, eif.org). EU Login supports CAS protocol natively — no OAuth client registration needed in simple CAS mode. Use `apereo/phpcas` or `subfission/cas` package. Also supports SAML and OAuth2 but CAS is simplest. Endpoint: `https://ecas.ec.europa.eu/cas/`. **Code is ready** (`EuLoginController`, routes, button) — blocked on DNS: EU Login rejects bare IP service URLs, needs a proper domain in APP_URL.

## Infrastructure

- [ ] **DNS fix** — Submit GreatHeberg support ticket: .eu registry delegates to topdns.com but zone transfer from heberg.ch master is broken (~1 year)
- [ ] **GitHub deploy key** — Add Hetzner ed25519 key to GitHub repo deploy keys (`ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMxLCl4Q47VL3CFnISw1vw6KW1IXwvEyBYH+M6kRD8BB hetzner-deploy`)

## Code Quality (P2)

- [ ] N+1 eager loading audit
- [ ] Remove `$guarded = []` on 3 models
- [ ] Set `SESSION_SECURE_COOKIE=true` in production
- [ ] Review CORS config
