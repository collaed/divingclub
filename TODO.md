# DivingClub-Manager — TODO

## Social Login Providers

- [ ] **Yahoo OAuth** — Add Yahoo as social login provider (covers 11 members: yahoo.com, yahoo.fr, yahoo.co.uk)
- [ ] **EU Login (CAS)** — Code is ready (`EuLoginController`, routes, button, `apereo/phpcas`). **Blocked on ECAS service registration**: EU Login requires application registration even for basic CAS — service URLs must be whitelisted. Action: email `EC-IAM-SERVICE-DESK@ec.europa.eu` or open SMT ticket to `DIGIT NUPS IAM BO` with service URL `https://test.clubcep.eu/auth/eulogin/callback` requesting PASSWORD authentication method. Covers 8 members (ec.europa.eu, curia.europa.eu, eib.org, eif.org).

## Infrastructure

- [ ] **DNS fix** — Submit GreatHeberg support ticket: .eu registry delegates to topdns.com but zone transfer from heberg.ch master is broken (~1 year)
- [ ] **GitHub deploy key** — Add Hetzner ed25519 key to GitHub repo deploy keys (`ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMxLCl4Q47VL3CFnISw1vw6KW1IXwvEyBYH+M6kRD8BB hetzner-deploy`)

## Code Quality (P2)

- [ ] N+1 eager loading audit
- [ ] Remove `$guarded = []` on 3 models
- [ ] Set `SESSION_SECURE_COOKIE=true` in production
- [ ] Review CORS config
