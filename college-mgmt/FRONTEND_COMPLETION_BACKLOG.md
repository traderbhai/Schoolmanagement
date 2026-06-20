# Frontend Completion Backlog

This is the short active frontend backlog. Full historical frontend migration notes are archived at `docs/archive/FRONTEND_COMPLETION_BACKLOG_FULL_ARCHIVE_2026-06-20.md`.

## Purpose

Track frontend readiness without mixing it with backend feature work.

## Current Verified Baseline

- `npm run frontend:build`: passed.
- `npm run frontend:smoke`: passed `127 tests / 3729 assertions`.
- `npm run frontend:smoke:mobile`: passed `29 tests / 1380 assertions`.
- Full PHP suite for same code baseline: `1490 tests / 12645 assertions` passed.

## Global Frontend Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Route/navigation manifest | release_ready | `App\Support\FrontendNavigation` groups primary role/module routes. |
| Shared sidebar shell | release_ready | Primary role shells use `x-ui.manifest-sidebar` for grouped desktop/mobile navigation. |
| Sidebar scroll/mobile shell | release_ready | Desktop/mobile smoke verifies mobile toggle, offcanvas, and scroll contracts. |
| Shared UI primitives | release_ready | Components exist for headers, KPIs, filters, tables, badges, empty states, timelines. |
| Compact operational CSS | release_ready | `public/css/app.css` contains compact dashboard/table/sidebar utilities. |
| Frontend scripts | release_ready | `frontend:build`, `frontend:smoke`, and `frontend:smoke:mobile` are registered and passing. |
| Debug/broken-page checks | release_ready | Smoke tests fail on debug traces, missing primary content, and visible broken links in sampled role pages. |

## Module Frontend Status

| Module / Surface | Status | Current direction | Future polish |
| --- | --- | --- | --- |
| Global shell/navigation | release_ready | Manifest-driven grouped navigation is the standard. | Continue label/group refinements only when users report friction. |
| Admission OS | release_ready | Command Center, Calling Desk, Counsellor Desk, Assessment Control, Offer/Seat Control covered. | More visual polish and browser create-flow checks. |
| Academics Dean OS | release_ready | Dean OS operating pages have filters, source links, exports, and saved-view coverage. | More interactive calendar/browser checks later. |
| PMC OS | release_ready | PMC Command and timetable/course allocation surfaces are covered. | Richer planner drag/drop interactions later. |
| CoE / Exam OS | release_ready | CoE dashboard/source lists are compact, filterable, and exportable. | Legacy visual parity if legacy pages remain heavily used. |
| IQAC OS | release_ready | IQAC quality source lists are compact, filterable, and exportable. | Legacy visual parity later. |
| Program Leadership OS | release_ready | Program source lists and dashboard surfaces are covered. | More program-specific browser flows later. |
| Course Delivery OS | release_ready | Delivery source lists and dashboard surfaces are covered. | More faculty action browser flows later. |
| Student / Teacher / Parent / Applicant portals | release_ready | Portal dashboards, safe action entries, ownership boundaries, and mobile shell covered. | More positive submit/browser flows later. |
| Admin / Accounts / CMC / Operations | release_ready | Admin, Accounts, CMC, Library, Hostel, Transport, Assets covered for action entries, exports, mobile/table usability. | Dedicated operator-role UX and richer sorting/table density later. |

## Frontend Development Rules

- Keep Laravel Blade, Bootstrap, Vite, and existing CSS architecture.
- Do not introduce a SPA framework for this app without explicit approval.
- Use compact, professional, data-dense layouts for operational pages.
- Dashboard numbers should link to source lists where practical.
- Every growing table should have search/filter/pagination/export when the workflow needs it.
- Avoid `href="#"` for actionable UI.
- Prefer useful operational empty states over placeholder cards.
- Run focused frontend tests before smoke; run full smoke only at stage gates.

## Current Blockers

No known release-blocking frontend issue is open.

## Future Polish

- Richer sorting on some operations tables.
- More browser-level modal/dropdown/create/edit interaction tests.
- More dense Admin command surface to reduce reliance on the long Admin sidebar.
- Dedicated Library/Hostel/Transport/Assets operator UX if those roles are introduced.
