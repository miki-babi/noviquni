# Noviquni — V1 task tracker

**Gift the files. Sell the study system.**

Telegram-first academic study system for Ethiopian freshmen. Public web is catalog CTA only. Product source of truth: `Noviquni-V1-Offer-One-Pager.md`.

Horizon: focused engineering + content wiring. Product contact: Luna.

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
| Positioning | Gift the files. Sell the study system — organized path, not a PDF dump |
| Surface | Telegram = student product; `/admin` = Filament; public web = SEO catalog + bait CTA into Telegram (**no in-browser study**) |
| Student web login | Out of scope |
| Campus focus | Stream/course scoped; university optional metadata — sales copy uses [Campus/Stream] when known |
| Content wedge | Freshman · Natural · Semester 1 core courses |
| Premium price | ~**30 ETB** (admin-configurable) |
| Referral unlock | **N = 3** qualified referrals = same Premium as pay (admin-configurable; default **3**) |
| Premium model | **One SKU** — pay OR refer unlocks the same `premium` access |
| Payments | Manual verify (Telebirr instructions OK); no gateway this slice |
| Metric | **Weekly active study** — not resource count |
| Flashcards | Always premium; always module-linked; not free bait; not a headline |
| Assignment / homework mill | Never ships — keep enum for leftovers; do not create or surface |

### Free vs Premium matrix

**Free (bait only)**

| Type | Rule |
| --- | --- |
| Short notes | 1–2 thin notes flagged `is_bait` |
| Past exam | 1 mid/final sample flagged `is_bait` |
| Quiz | Sample quiz (few questions) flagged `is_bait` |
| Week-1 checklist | One published `bait_checklist` study plan per course |
| Free path | Note → sample quiz only |
| Catalog / browse / onboarding / profile | Free |
| Referral invite + progress | Free (**never** gate) |
| Public web | Teaser/meta + “Grab free Week-1 / course bait” Telegram CTA — **never** full study UI |

**Do not include on free:** full stacks, full week plans, full archive, flashcards.

**Premium (pay 30 ETB OR 3 referrals)**

| Type | Rule |
| --- | --- |
| Modules | Full ordered spine (`sort_order`) |
| Short notes | Aligned to modules |
| Worksheets | Per module in the path |
| Quizzes | Full banks |
| Flashcards | Module-linked decks only |
| Past exams | Mid + final packs |
| Week / exam-sprint plans | Wrap the path |
| Course archive | Hubs as secondary browse |

**Premium path (per course):** Module → short note → worksheet → quiz + flashcards → past mid/final.

**Packaging rules**

1. One entitlement: `premium_until` unlocks all premium-flagged resources — no per-course SKUs.
2. Free students only open `is_bait` resources + bait checklist; other steps show locked titles + CTA.
3. Flashcards: `is_premium = true` and `module_id` required.
4. Web: never render study payload; teaser/meta + Telegram CTA only.
5. Bot copy (adapt): `Premium = the full Natural S1 study path — modules, notes, worksheets, quizzes, flashcards, past exams — 30 ETB or invite 3 friends.`

---

## Now

**Align product with V1 Offer One-Pager** — path schema, bait gating, CTA-only web, study plans, weekly active study metric.

---

## Built

Do not rebuild these.

### Telegram

- [x] `/start`, referral attach, button onboarding (stream → university → semester → courses)
- [x] Main menu, resource browse, premium gating
- [x] Deep link URL builders (`resource_*`, `course_slug`)

### Catalog & admin

- [x] Streams, universities, courses, semesters, learning resources
- [x] Filament CRUD, College API resource wizard, payments verify/reject
- [x] Broadcasts, referral rewards/withdrawals, settings, activity logs

### Monetization

- [x] Binary `is_premium` gating in bot and web
- [x] Pay **or** referral unlock the same premium entitlement
- [x] Withdrawals from bot; settings-driven price and referral N

### Public site & College API

- [x] Routes: home, universities, streams, courses, resources, hubs
- [x] SEO: meta, JSON-LD, sitemap, robots, breadcrumbs
- [x] College API client + mapper
- [x] Pest coverage for webhook, resource access, SEO/sitemap, College API, payments, referrals, broadcasts, admin

### Seeder baseline

- [x] Streams (Natural/Social), sample universities, semesters, Natural S1 **course shells** (Math, Physics, Chemistry, English)

---

## Next (V1 offer slice)

### WP0 — Tracker + defaults

- [ ] README locked decisions match one-pager
- [ ] Defaults: `premium_price = 30`, `required_referrals = 3`
- [ ] Tests lock N=3 default

### WP1 — Path schema + Filament

- [ ] `module_id`, `sort_order`, `is_bait` on learning resources
- [ ] `ResourceType::Worksheet`; StudyPlan + items
- [ ] Filament: parent module, sort order, bait; hide Assignment create
- [ ] Flashcards require module + premium

### WP2 — Telegram ordered path

- [ ] `CoursePathService`; course UI = path not hubs-first
- [ ] Continue = next path step
- [ ] Free: bait only; premium: full path
- [ ] Hubs = premium archive secondary (include exams)

### WP3 — CTA-only web + deep links

- [ ] No in-browser study
- [ ] Home CTA: “Grab free Week-1 / course bait”
- [ ] `/start` handles `bait`, `resource_*`, `course_*`

### WP4 — Study plans + WAU

- [ ] Filament StudyPlan CRUD
- [ ] Mini-app: bait checklist free; week / exam-sprint premium
- [ ] Cohort urgency setting on premium screen
- [ ] Filament: Weekly active study (7d distinct openers)

### WP5 — Soft launch verification

- [ ] Free bait path → premium wall → pay unlock
- [ ] Referral unlock at N=3
- [ ] Cold visitor: web CTA → Telegram bait

### Definition of Done

- [ ] One-pager free/premium/never-ships reflected in code + README
- [ ] Ordered path + bait gating in Telegram
- [ ] Web is CTA only
- [ ] Study plans + weekly active study metric
- [ ] Pest green for changed behaviors

---

## Later

- Tune N weekly from admin
- Social stream spine
- Telebirr / automated PSP when manual verify becomes painful
- Opportunities as curated TG posts only (ops), not a module
- Contests/streaks into week plans
- PPT file storage as module companions
- Keyboard builder, bookmarks product, student web login

---

## Out of scope / Never ships (V1)

- Campus Survival OS (registration, dorm, ID, costs, logistics)
- Career paths / internship / opportunity marketplace
- Portfolio builder
- Assignment / homework mill
- National “all Ethiopia PDFs” factory
- SEO as the product (site = CTA only)
- Payment gateway / Telebirr automation
- Student web accounts / dashboard
- Multi-tier subscription plans
- Feature-checklist arms race

---

## North-star checks

- New student can open bait notes + sample quiz without premium
- Flashcards and week plans never free
- Premium resources / path steps show clear unlock CTA (30 ETB **or** invite 3 friends)
- After 3 qualified referrals **or** admin-verified payment → full path access
- Public pages never render study payload — Telegram CTA only
- Admin dashboard shows weekly active study

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
