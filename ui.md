# SchoolBuds — Landing Page UI Specification

> Reference: marketing/landing page for "SchoolBuds", a school management SaaS product. This doc describes every section top-to-bottom so it can be rebuilt in code (React/HTML/Tailwind, etc.). Note: the reference screenshot itself is framed inside a decorative green-leaf background as a presentation mockup — that leaf border is **not part of the actual site**. Build the site as a normal full-width white page; ignore the outer foliage frame.

---

## 1. Design Tokens

### Colors


| Token            | Hex                                   | Usage                                                                 |
| ---------------- | ------------------------------------- | --------------------------------------------------------------------- |
| `primary-50`     | `#BDFFDE`                             | Light pill/badge backgrounds, active nav tint                         |
| `primary-100`    | `#0BD294`                             | Secondary chart series, "trend up" indicators                         |
| `primary-200`    | `#059467`                             | Main brand: CTA buttons, active states, big numbers, icon accents, filled stat cards |
| `primary-300`    | `#036D4B`                             | Badge text, hover/pressed states, darker chart series                 |
| `primary-400`    | `#025539`                             | Reserved high-contrast accents                                        |
| `primary-500`    | `#013D29`                             | Reserved very high-contrast accents                                   |
| `text-primary`   | `#151515`                             | Headlines, nav logo, body headings                                    |
| `text-secondary` | `#6B7280`                             | Paragraph copy, descriptions                                          |
| `text-muted`     | `#9CA3AF`                             | Inactive step labels, small meta text                                 |
| `surface-white`  | `#FFFFFF`                             | Page background, cards                                                |
| `surface-gray`   | `#F3F4F5`                             | Neutral stat card, section dividers, sidebar backgrounds              |
| `border-light`   | `#ECECEC`                             | Card borders, table row dividers                                      |
| `success-green`  | `#0BD294`                             | "▲ +5% / +6%" trend indicators (alias of `primary-100`)               |
| `star-gold`      | `#FBBF24`                             | Rating stars, sparkline accents                                       |


### Typography

- **Font family:** Geometric sans-serif (e.g. Inter, General Sans, or similar) throughout.
- **H1 (hero):** ~40–48px, weight 700–800, tight line-height (1.1), color `text-primary`.
- **H2 (section titles):** ~30–34px, weight 700, centered or left-aligned depending on section.
- **H3 (card/feature titles):** ~16–18px, weight 600.
- **Body copy:** 14–15px, weight 400, color `text-secondary`, line-height ~1.6.
- **Small/meta text:** 11–12px, weight 500, used in badges, stat labels, table headers.
- **Big stat numbers:** 28–32px, weight 700–800 (e.g. "50K+", "500+", "94%").

### Spacing & Shape

- **Page max-width:** ~1200–1280px centered container with horizontal padding ~64–96px on desktop.
- **Border radius:** generous rounding — 12–16px on cards, 999px (pill) on buttons/badges, ~20–24px on large image/dashboard mockup containers.
- **Shadows:** soft, low-opacity drop shadows on floating cards and the dashboard mockup (e.g. `0 20px 40px rgba(0,0,0,0.08)`).
- **Section vertical rhythm:** generous whitespace between sections (~96–120px padding top/bottom for major sections).

---

## 2. Global Layout (top to bottom)

1. Navigation bar (sticky/fixed, transparent white)
2. Hero section (headline + CTA + dashboard product screenshot)
3. Trust bar ("Used by schools across the world" + client logos)
4. Statement/mission section (large editorial headline with inline emoji accents)
5. Stats/highlights card row (4 cards)
6. Feature grid section ("Manage Every Part of Your School in One Place")
7. "How SchoolBuds Works" section (step list + product image/card, repeats for multiple steps)

---

## 3. Navigation Bar

**Layout:** single row, 3-column flex: left nav links | center logo | right actions. White/transparent background, no border, sits at very top with modest vertical padding (~20px).

- **Left:** horizontal text nav links, small font (~13px), medium weight, gray/black, separated by `·` or spacing: `Features For Schools For Parents About Us Contact`
- **Center:** Wordmark **"SchoolBuds"** — bold, black, ~16–18px, no icon (icon appears only inside the product screenshot, not in the site nav).
- **Right:**
  - `Sign in` — plain text link, black, no background.
  - `Get Started Free →` — solid pill button, `primary-200` background, white text, small circular arrow icon on the right inside a lighter circle.

---

## 4. Hero Section

Two-column layout: **left = text content**, **right = product screenshot**. Roughly 45/55 split, vertically centered.

### Left column

1. **Eyebrow badge:** small pill, `primary-50` background, `primary-300` text, contains a small star/sparkle icon + label: `✦ All-in-one School Management Platform`
2. **Headline (H1):** `Better Communication for Schools, Teachers, and Parents` — 3 lines, bold, dark, largest text on the page.
3. **Subtext (paragraph):** `Keep everyone informed, connected, and engaged with real-time updates, academic progress tracking, and seamless communication.`
4. **CTA row (2 buttons, inline):**
  - `Get Started Free →` — solid primary-200 pill button (primary).
  - `▶ Watch Video` — text-only link with a primary-200 play-icon in a small circle to its left, no background (secondary/ghost action).
5. **Social proof row** (below CTAs, smaller):
  - 3 overlapping circular avatar photos (people), slightly overlapping each other.
  - 5 filled gold star icons in a row.
  - Two-line caption text: `Trusted by 2,500+ Schools` / `and 1M+ users worldwide`.

### Right column — Product Dashboard Screenshot (mockup card)

A large rounded card (white, soft shadow, floats above the page, feels "elevated") showing an app dashboard UI. Structure inside the mockup:

- **Top bar:** small primary-200 rounded-square logo mark + "SchoolBuds" wordmark (top-left, on a primary-200 chip), page title `Good morning, Ms. Emma! 👋` with subtext `Here's what's happening at Greenfield Academy today.`, and a search input `Search anything...` with a search icon (top-right).
- **Left sidebar** (icon + label nav, narrow column): `Dashboard` (active — primary-50 highlighted pill background, primary-200 icon), `Classes`, `Students`, `Attendance`, `Homework`, `Messages` (with a small red notification dot), `Announcements`, `Calendar`, `Reports`, `Settings`.
  - Bottom of sidebar: a small promo card with primary-50 background: `Get the SchoolBuds Mobile App` / `Stay connected and never miss an update`, with a phone illustration graphic and a small primary-200 CTA button.
- **Top stat cards (row of 3):**
  - `Attendance Today` — **94%**, small primary-100/primary-200 sparkline chart, `▲ 6% vs yesterday`.
  - `Homework Submitted` — **78%**, small yellow sparkline chart, `▲ 5% vs yesterday`.
  - `Active Students` — **1,248**, green icon badge, `▲ 12% this month`.
- **Middle row (2 widgets side by side):**
  - **Class Overview** table — columns: `Class | Students | Attendance | Homework`; ~5 rows (e.g. Class 4A, 4B, 4C, 7C, 8A) each with a numeric student count and two horizontal progress bars (red for attendance %, yellow for homework %). "View all" link top-right.
  - **Homework Submission** bar chart — vertical bar chart, days of week (Mon–Sat) on x-axis, two-tone bars (solid yellow = "Submitted", outlined/pale = "Pending"), small legend below, dropdown filter "This Week" top-right.
- **Lower row (2 widgets side by side):**
  - **Recent Announcements** — list of 3 rows, each with a small colored square icon (primary-200/star-gold/primary-100), a title line, and a date (e.g. "May 15, 2025"). "View all" link top-right.
  - **Messages** — list of 3 rows, each with a circular avatar, sender name + "(Parent)" tag, message preview snippet, and a time/unread red-dot indicator. "View all" link top-right.
- **Bottom row — Top Performers:** horizontal row of ~4 student entries, each with a small rank icon (gold/silver/bronze medal style), circular avatar photo, name, class label, and a percentage badge in primary-200.

---

## 5. Trust Bar (below hero)

Simple centered/split row:

- Left: small caption `Used by schools across the world`.
- Right: 3 school "logos" (small icon + name), muted/grayscale style: `GreenField Academy` · `Maplewood High School` · `BrightFuture International`

---

## 6. Statement / Mission Section

Two-column layout: small badge on the left, large editorial statement text on the right (or right-aligned large block).

- **Small pill badge:** `SchoolBuds` (primary-50 background, primary-300 text) — sits alone on the left, vertically aligned near the top of the paragraph block.
- **Large statement (mixed-weight headline, ~28–32px):** bold black words emphasize key phrases, regular gray weight for connective words, with two inline emoji used as decorative icons mid-sentence:
  > **A connected platform** built to make school life 🎯 **more transparent, organized,** and 🔶 **informed.**
  (Bold/dark: "A connected platform", "more transparent, organized,", "informed." — Regular/gray: "built to make school life", "and". Emoji act as inline visual accents replacing/accompanying certain words.)

---

## 7. Stats / Highlights Card Row

Four cards in a single row (equal width, ~4-column grid, rounded corners, no/light borders). Each card is tall, content bottom-aligned or top+bottom split.

1. **Parents connected** — white/light-gray card. Top: a small grid/cluster of ~10 tiny circular photo thumbnails (real family/parent-child photos) arranged in 2 rows, overlapping slightly. Bottom: big heading `50K+`, subheading `Parents connected`, small description `Keeping families informed about their child's school journey.`
2. **Schools onboarded** — solid `primary-200` filled card, white text. Big heading `500+`, subheading `Schools onboarded`, small lighter/translucent-white description `Helping schools manage activities and communication.`
3. **Progress access** — solid light-gray (`surface-gray`) card, dark text. Big heading `24/7`, subheading `Progress access`, description `Parents can check scores, attendance, and updates anytime.`
4. **Attendance visibility** — full-bleed photo card (image of a father with two children outdoors, blue-toned), text overlaid bottom-left in white with a dark gradient scrim for legibility: big heading `98%`, subheading `Attendance visibility`, description `Making attendance tracking simple for teachers and parents.`

---

## 8. Feature Grid Section — "Manage Every Part of Your School in One Place"

Centered section header, followed by a 4-column feature grid separated by thin vertical divider lines.

- **H2 (centered):** `Manage Every Part of Your School in One Place`
- **Subtext (centered, max-width ~600px):** `From student records and attendance to parent communication and performance reports, SchoolBuds gives schools the tools they need to manage daily operations efficiently.`
- **4-column grid**, each column:
  - Circular icon badge: thin primary-200 outline ring (~44px), containing a simple line-icon (people/user icon).
  - **Title** (bold, ~16px)
  - **Description** (gray, ~14px)

  | #   | Title                  | Description                                                                                                     |
  | --- | ---------------------- | --------------------------------------------------------------------------------------------------------------- |
  | 1   | Student Management     | Manage student profiles, classes, academic records, and important information from one centralized platform.    |
  | 2   | Attendance & Academics | Record attendance, update scores, manage assignments, and give parents a clear view of academic progress.       |
  | 3   | Parent Communication   | Share announcements, updates, events, and important information with parents through one connected platform.    |
  | 4   | Reports & Insights     | Get a clear overview of attendance, performance, and student progress with simple reports and visual analytics. |

  Columns are separated by a thin vertical `border-light` line between each (like a 4-cell table), not by gap/whitespace alone.

---

## 9. "How SchoolBuds Works" Section

Two-column layout, left = step list (acts as a stepper/tab control), right = large image with an overlapping floating stat card. This section appears to repeat/scroll through multiple steps, each revealing a different supporting image+card below.

- **Small eyebrow badge:** `HOW SCHOOLBUDS WORKS` — uppercase, small, primary-300 text on primary-50 pill.
- **Step list (vertical, left column):** acts like a stepper — only the **current/active step is bold black**, the rest are muted gray (inactive/upcoming), implying scroll-driven or click-driven step switching:
  1. **School Sets Up** (active/bold in this state)
  2. Teachers Update (muted)
  3. Parents Stay Informed (muted)
  4. Progress Stays Connected (muted)
- **Right column — image + floating card (for the active step):**
  - Background: full-bleed rounded photo (outdoor/sky scene, child holding a toy plane) inside a large rounded-corner container.
  - **Floating overlay card** ("Performance Overview" panel), positioned lower-half of the image, white rounded card with shadow:
    - Header row: `Performance Overview` (bold) + dropdown pill `This Term ⌄` (right-aligned).
    - 3 stat sub-cards in a row (light rounded boxes):
      - `Attendance` → **92%** (primary-200, bold) → `▲ 5%` (primary-100) `vs last term`
      - `Average Score` → **84%** (primary-200, bold) → `▲ 6%` (primary-100) `vs last term`
      - `Assignments` → **18** (primary-200, bold) → `Completed` (no trend)
- **Below the image, supporting copy block:**
  - **H3:** `Connect Your School`
  - **Paragraph:** `Create a digital space for your school and bring classes, teachers, students, and academic information together in one organized platform. SchoolBuds makes it simple to set up your school community and keep everyone connected from the start.`
- **Next step preview (partially visible, cut off in reference):** another rounded image card with a floating stat panel titled `Subject Performance`, showing a simple bar chart (y-axis gridlines at 25/50/75/100, 4 primary-200 vertical bars of varying/descending height) — implies each of the 4 steps has its own themed image + data-card pairing that swaps in as the user progresses through the stepper.

---

## 10. Component Notes for Implementation

- **Buttons:** pill-shaped (`border-radius: 999px`), primary = solid primary-200 w/ white text + trailing circular arrow icon; secondary = ghost/text button with icon.
- **Badges/pills:** `primary-50` background, `primary-300` text, small uppercase or sentence case, used consistently across sections (hero eyebrow, statement section, how-it-works eyebrow).
- **Cards:** consistent rounded-corner (12–16px) treatment across stat cards, dashboard mockup, and floating overlay panels; soft shadows for anything "floating" over an image.
- **Icons:** thin-stroke line icons throughout (sidebar nav, feature grid circles); no heavy filled icon style except small colored square icons in the announcement list.
- **Data viz:** sparklines (hero mockup stat cards), bar charts (homework submission, subject performance), horizontal progress bars (class overview table) — all rendered in primary-200 / primary-100 tones with gray/outline "secondary" series.
- **Imagery:** warm, candid lifestyle photography of parents/children/teachers used in stat card #4, the "how it works" hero image, and the small avatar clusters — not illustrations.
- **Interaction implied:** the "How It Works" step list strongly suggests a tabbed/stepper component (click or scroll-linked) that swaps the image + stat-card content on the right as the active step changes.

---

## 11. Responsive Guidance (not directly visible, recommended defaults)

- **Desktop (≥1024px):** all multi-column layouts as described (hero 2-col, stats 4-col, features 4-col, how-it-works 2-col).
- **Tablet (768–1023px):** stats and feature grids collapse to 2 columns; hero remains 2-col or stacks with image below text.
- **Mobile (<768px):** everything stacks to 1 column; nav collapses to a hamburger menu; dashboard mockup scales down and may need a simplified/cropped version; step list and image stack vertically instead of side-by-side.

