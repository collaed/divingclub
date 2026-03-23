# DivingClub-Manager — TODO

## Social Login Providers

- [ ] **Yahoo OAuth** — Add Yahoo as social login provider (covers 11 members: yahoo.com, yahoo.fr, yahoo.co.uk)
- [ ] **EU Login (CAS)** — Code is ready (`EuLoginController`, routes, button, `apereo/phpcas`). **Blocked: ECAS restricts service URLs to European institution domains by default.** External URLs like `test.clubcep.eu` need explicit approval from the ECAS service team. Use `laxValidate` endpoint (not `proxyValidate`) to allow external users. Contact: `EC-IAM-SERVICE-DESK@ec.europa.eu` or SMT ticket to `DIGIT NUPS IAM BO`. Realistically may not be approved for a non-EU-institution site. Covers 8 members (ec.europa.eu, curia.europa.eu, eib.org, eif.org). Official PHP lib: `ecphp/cas-lib` at code.europa.eu.

## Infrastructure

- [ ] **DNS fix** — Submit GreatHeberg support ticket: .eu registry delegates to topdns.com but zone transfer from heberg.ch master is broken (~1 year)
- [ ] **GitHub deploy key** — Add Hetzner ed25519 key to GitHub repo deploy keys (`ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMxLCl4Q47VL3CFnISw1vw6KW1IXwvEyBYH+M6kRD8BB hetzner-deploy`)

## Code Quality (P2)

- [ ] N+1 eager loading audit
- [ ] Remove `$guarded = []` on 3 models
- [ ] Set `SESSION_SECURE_COOKIE=true` in production
- [ ] Review CORS config
