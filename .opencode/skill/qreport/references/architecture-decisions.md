# Architecture Decisions

Reasoning and tradeoffs only — no code. If the stack ever changes, update
this file's conclusion but keep the reasoning table, since the constraints
(LAN-only, solo coder, capstone timeline) rarely change even when tooling
does.

## Deployment constraint (fixed, not up for debate)

LAN-only — a single on-campus PC/server, not internet-accessible. This
shapes every other decision below: no cloud-native assumptions, no
email/SMS flows, no reliance on external APIs for anything functional.

## Deployment approach (proposed, not yet confirmed as final)

The server-hosting PC (referred to as "Admin" in team diagrams — this is a
physical/operational role, running the server and broadcasting the
network, not an additional in-system user role; the system's only two
user roles remain Student and OJT Coordinator per Section 3 of the
blueprint) broadcasts its own OS-level WiFi hotspot, and Students/
Coordinators connect their own devices to that hotspot and reach the
server via its LAN IP address (e.g. `http://192.168.x.x`) — never
`localhost`, since that only resolves on the server machine itself, and
never a domain name, since there's no DNS on a LAN with no internet
access. A QR code encoding that LAN URL can be posted for convenience
(see workflows.md's Onboarding section) — it is not part of
authentication.

**Still open:** whether this hotspot-from-the-server-PC approach is the
intended final deployment, or specifically a defense-day demo
convenience. OS-level hotspots typically cap out around 8–10 connected
devices with limited range, which is fine for a defense demo but may not
scale to an actual full OJT cycle's worth of students/coordinators. If
this is meant as the real deployment, connecting the server to the
school's existing WiFi/router infrastructure instead would remove that
device-count ceiling. Resolve this alongside open-items.md's item on
final server hardware/OS.

## Stack decision (current)

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel 11 (PHP) | Deepest well of PH capstone-specific tutorials/adviser familiarity of the realistic options; convention-heavy, which matters for review-ability even with a solo coder; built-in auth scaffolding (Breeze) removes a large chunk of manual work. |
| Database | MySQL (or MariaDB) | Reliable, free, pairs naturally with Laravel/Eloquent, easy local backup on a single machine. |
| Frontend | Blade (server-rendered) + Vue 3 (via Vite) for the genuinely interactive pieces only | No need to SPA-ify the whole app. Reserve Vue for things that benefit from it: DAR's auto-computing time-in/out, the WAR's 4-week progressive form, the Coordinator's review queue. Everything else (dashboards, login, static reports) stays plain Blade. |
| PDF generation | barryvdh/laravel-dompdf | Mature, widely documented, gives exact layout control needed to match the official LLCC forms field-for-field. |
| Auth | Laravel Breeze | Login + forced password change scaffolding out of the box — matches the coordinator-issued-temp-password flow directly. |
| Hosting (dev) | XAMPP or Laravel Herd | Local dev before deploying to the on-campus PC. |
| Hosting (prod) | Single on-campus PC/server, exact OS/hardware TBD | Matches the confirmed LAN-only, single-server deployment (see open-items.md). |

## Why not the alternatives considered

- **Node.js + Express** — more control, unified JS codebase, but auth/roles/
  PDF rendering all need to be hand-built rather than scaffolded, and it's a
  more flexible (= more divergence-prone) choice for a single solo coder to
  keep consistent over time.
- **Django (Python)** — comparably fast to Laravel for this kind of
  role-gated CRUD-and-forms app, with excellent built-in admin tooling, but
  a noticeably thinner PH capstone support community to fall back on when
  stuck under deadline pressure.

## Design principle carried from the stack choice

Because Laravel is convention-heavy, the codebase should lean into that
rather than fight it — standard Eloquent relationships, standard
FormRequest validation, standard Policy classes for the BR-14 backend
isolation requirement — rather than custom architecture that a future
reviewer (adviser, panel, or future-you on a different account) would have
to learn from scratch.
