# Noviquni — V1 task tracker

Acquisition + conversion loop for Ethiopian freshman students: **Telegram** is the student product, **`/admin`** is Filament, **public web** is SEO + free study + CTA into Telegram. Content wedge: **Freshman · Natural · Semester 1** (Math, Physics, Chemistry, English). No student web login.

Horizon: ~2 weeks focused engineering + content wiring. Product contact: Luna.

---

## How to use this file

1. Work **Next** top to bottom. Do not start **Later** or **Out of scope**.
2. Check items when they ship (code + tests, not just scaffolding).
3. Update **Now** to the single current engineering task.
4. Park ideas under **Later** or in a PR “Follow-ups” section — do not expand scope mid-slice.

---

## Locked decisions

| Decision | Value |
| --- | --- |
| Surface | Telegram = student product; `/admin` = Filament; public web = SEO + free study + Telegram CTA |
| Student web login | Out of scope |
| Campus focus | All-uni / online — no single-campus hardcode |
| Content wedge | Freshman · Natural · Semester 1 core courses; university is optional metadata/filter |
| Premium price | ~**30 ETB** (admin-configurable) |
| Referral unlock | **N = 3** qualified referrals = same Premium as pay (admin-configurable; default **3**) |
| Premium model | **One SKU** — pay OR refer unlocks the same `premium` access |
| Payments | Manual verify (instructions + reference); no gateway this slice |

### Free vs Premium matrix

**Free (always)**

| Type | Rule |
| --- | --- |
| Notes / modules | Chapter 1 (or first ~20–25%) of each Natural S1 core — full quality, not a stub |
| Practice | 1–2 sample mid-style questions per chapter with short solution |
| Quiz | Chapter 1 quiz only, **or** hard daily cap (e.g. 5 Q/day) — pick one and document |
| Flashcards | First deck **or** ~20 cards per course free; rest premium |
| Catalog / browse / onboarding / profile | Free |
| Referral invite + progress | Free (**never** gate) |
| Broadcasts | Free |
| Public web | Free published samples only + Telegram CTA |

**Premium (pay 30 ETB OR 3 referrals)**

| Type | Rule |
| --- | --- |
| Notes | Full Natural S1 packs (all chapters) |
| Quiz / exam trainer | Full banks |
| Past exams | Mids + finals packs (primary conversion lever) |
| Flashcards | Full decks |

**Packaging rules**

1. One entitlement: `is_premium` unlocks all premium-flagged resources — no per-course SKUs.
2. Teaser rule: every premium content *type* for a course should have at least one free sample.
3. Wedge focus: Natural S1 cores first — do not spray half-flagged content across Social / other years.
4. Web: never render full premium body; teaser/meta + “Open in Telegram” CTA.
5. Bot copy (adapt as needed): `Premium = full Natural S1 notes + quizzes + past finals — 30 ETB or invite 3 friends.`

**Pragmatic V1:** if chapter-partial notes are hard, publish two resources per unit — `… (Free preview)` and `… (Full)` — rather than a schema rewrite. Prefer admin settings over hardcoded price/N/copy. Keep existing referral qualification rules unless broken.

---

## Now

**WP0 — Commit/ship the uncommitted public SEO site + College API work** (still in the working tree). Keep Pest green, then move to WP1.

---

## Built

Do not rebuild these.

### Telegram

- [x] `/start`, referral attach, button onboarding (stream → university → semester → courses)
- [x] Main menu, resource browse, premium gating
- [x] Deep links from web (`resource_*`, `course_slug`)

### Catalog & admin

- [x] Streams, universities, courses, semesters, learning resources (module/notes/summary/exam/assignment/practice/flashcards)
- [x] Filament CRUD, College API resource wizard, payments verify/reject
- [x] Broadcasts, referral rewards/withdrawals, settings, activity logs

### Monetization

- [x] Binary `is_premium` gating in bot and web
- [x] Pay **or** referral unlock the same premium entitlement
- [x] Withdrawals from bot; settings-driven price and referral N

### Public site & College API (implemented in working tree — not yet committed)

- [x] Routes: home, universities, streams, courses, resources, hubs (`/modules`, `/notes`, `/exams`, `/practice`)
- [x] SEO: meta, JSON-LD, sitemap, robots, breadcrumbs
- [x] Free in-browser study; premium body not rendered (Telegram CTA)
- [x] College API client + mapper
- [x] Pest coverage for webhook, resource access, study pages, SEO/sitemap, College API, payments, referrals, broadcasts, admin

### Seeder baseline

- [x] Streams (Natural/Social), sample universities, semesters, Natural S1 **course shells** (Math, Physics, Chemistry, English)

---

## Next (V1 slice)

Work top to bottom. Check when done.

### WP0 — Repo hygiene

- [ ] Commit / PR the uncommitted public SEO site + College API work
- [ ] Ensure Pest still passes: webhook/onboarding, resource access, study pages, SEO/sitemap, College API, payment verification, referrals, broadcasts, admin access
- [ ] Fix deploy/env docs only if required to run locally

**Done when:** main (or release branch) contains the public site + College API; CI/tests green.

### WP1 — Settings & referral threshold

- [ ] Defaults: `premium_price = 30`, `required_referrals = 3` (code default is still `5` in `SettingsService`)
- [ ] Confirm referral unlock grants the same premium/subscription state as verified payment
- [ ] No hardcoded “5 friends” strings — use the setting (default 3)
- [ ] Tests: unlock at 3, not at 2; lock **default** N=3 (not only a test override)

**Done when:** changing N in admin changes unlock behavior without code deploy; tests lock N=3 default.

### WP2 — Free/premium packaging (matrix)

- [ ] Model or convention for teaser vs full (e.g. two resources, `preview_resource_id`, or structured free-until-chapter)
- [ ] Enforce matrix in Telegram delivery (notes, quiz, exam, flashcards)
- [ ] Enforce matrix on public study pages (free body only)
- [ ] Premium CTA: price + referral progress (X/3) + pay instructions entry
- [ ] Tests: free sample accessible; premium blocked; unlock after pay; unlock after 3 referrals

**Done when:** non-premium students get teasers only; premium students get full Natural S1 flagged content.

### WP3 — Ship public acquisition loop

- [ ] Public loop committed and deployable (routes/SEO/study/CTA already implemented — see Built)
- [ ] Cold-visitor path verified: free sample on web → Telegram on the right course/resource
- [ ] Premium pages do not leak full content in production

**Done when:** a cold visitor can study a free sample on web and land in Telegram on the right resource.

### WP4 — Natural S1 content spine

Use College API / admin wizard to **generate and publish** (not just scaffold) for Mathematics, Physics, Chemistry, English:

- [ ] Free: Ch1 (or ~20–25%) notes + sample mid Qs + ch1 quiz or capped quiz + flashcard teaser
- [ ] Premium: full notes + full quiz/trainer + past mid/final pack + full flashcards
- [ ] All published, correctly flagged, attached to Natural + course (+ optional semester S1)

**Done when:** a new Natural student sees a non-empty free path and a clear premium wall before “full exam readiness.”

### WP5 — Soft launch verification

- [ ] Checklist: onboard → open ≥3 free resources → premium wall → manual payment verify → premium access
- [ ] Second path: 3 referred students qualify → referrer unlocks premium
- [ ] Broadcasts still send; referral progress UI shows N=3
- [ ] Note friction for PM (copy, payment instructions)

**Done when:** both unlock paths proven on staging/non-prod bot; known issues listed.

### Definition of Done (slice)

- [ ] Public site + College API shipped (committed, deployable)
- [ ] Defaults: 30 ETB, referral unlock **3**
- [ ] Free/premium behavior matches the matrix for Telegram + web
- [ ] Natural S1 four-course spine published (free teasers + premium full)
- [ ] E2E: free path, pay unlock, referral unlock all verified
- [ ] No new work started on out-of-scope list

---

## Later

- Tune N weekly from admin
- Social stream spine
- Telebirr / automated PSP when manual verify becomes painful
- Opportunities as curated TG posts only (ops), not a module
- Keyboard builder, bookmarks product, student web login

---

## Out of scope (this slice)

Do **not** build these now:

- Opportunities / jobs / scholarships module
- Career paths / portfolio
- GPA calculator, study planner, Pomodoro, etc.
- Configurable Telegram Keyboard Builder (keep fixed menu)
- Payment gateway / Telebirr automation
- Full Amharic i18n (bilingual snippets OK if already trivial)
- Student web accounts / dashboard
- Bookmarks product, real file-download product
- Social stream parity, Year 2+, AI tutor, video library
- Multi-tier subscription plans (1mo / 6mo / 1yr)

If a task is not in WP0–WP5, do not build it in this slice.

---

## North-star checks

- New student can open ≥3 free resources without premium
- Premium resources show clear unlock CTA (30 ETB **or** invite 3 friends)
- After 3 qualified referrals **or** admin-verified payment → full premium access
- Public free pages study in browser; premium never fully exposed on web (CTA to Telegram)

---

## Local setup

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
composer run dev
```

- App: Herd site URL for this project
- Admin: `/admin` (seeded `admin@noviquni.test` / `password` after seed)
- Telegram: set bot token / webhook per `.env.example` and `services.telegram`
