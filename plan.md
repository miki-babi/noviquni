Absolutely. Here is the **updated locked MVP plan**, with **University and Semester optional**.

# 🎓 Freshman Academic Platform — Locked MVP

## 1. Core concept

A platform for freshman students to find academic resources.

Students can access:

- Free resources
- Premium resources

Premium content can be unlocked by:

**💰 Paying** OR **👥 referring 5 students**

Students can also earn money from successful referrals.

The main growth loop is:

```text
Student
   ↓
Finds useful resources
   ↓
Wants Premium
   ↓
Pay OR refer 5 students
   ↓
New students join
   ↓
Those students do the same
   ↓
Organic growth
```

---



# 2. Student onboarding



### Required

**Stream**

- Natural
- Social



### Optional

**University / College**

**Semester**

### Course selection

Students select the courses they are taking.

The system uses existing student behavior to recommend courses.

For example:

> **Students from AAU + Natural commonly select:**
>
> ☑ Mathematics
> ☑ Physics
> ☑ Chemistry
> ☑ English
>
> You can change these selections.

The student can:

- Accept recommendations
- Remove courses
- Add courses



### Referral-based personalization

If the student comes through a referral:

> Referrer: AAU + Natural

Then:

- AAU appears at the top of the university list
- Natural can be suggested
- Courses can be preselected based on similar students

But **nothing is forced**.

---



# 3. Resource structure

The core resource structure is:

```text
Stream
   ↓
Course
   ↓
Resources
```

With optional metadata:

```text
Semester ─── optional
University ─ optional
```

So a resource could be:

### General

```text
Natural
└── Mathematics
    └── Calculus Notes
```

Available to everyone taking Mathematics.

### Semester-specific

```text
Natural
└── Mathematics
    └── Semester 1
        └── Calculus Notes
```



### University-specific

```text
Natural
└── Mathematics
    └── AAU
        └── Calculus Notes
```

Or both:

```text
Natural
└── Mathematics
    └── Semester 1
        └── AAU
            └── Calculus Notes
```

This keeps the system flexible.

---



# 4. Resource types

Admin can categorize resources as:

- Modules
- Lecture notes
- Summaries
- Past exams
- Assignments
- Practice questions
- Other

Each resource has:

- Title
- Description
- File/content
- Stream
- Course
- Optional semester
- Optional university
- Free/Premium
- Published/unpublished

---



# 5. Student home

After onboarding:

> **Welcome 👋**
>
> Your courses:
>
> 📐 Mathematics
> 12 resources
>
> ⚛️ Physics
> 8 resources
>
> 🧪 Chemistry
> 15 resources
>
> 📚 English
> 7 resources

Students primarily see resources related to their selected courses.

---



# 6. Premium system

Premium resources show:

> 🔒 **Premium Resource**
>
> Unlock for **30 ETB**
>
> **OR**
>
> 👥 Refer **5 students**
>
> Progress: **3 / 5**

Required referrals should be configurable from the admin panel.

Premium duration should also be configurable.

---



# 7. Referral system

Every student gets:

- Referral code
- Referral link
- Referral dashboard

Example:

```text
Your referrals

3 / 5 completed

🟢 Hana
🟢 Dawit
🟢 Abel
⚪ Samuel
⚪ Yonas
```

The system tracks:

- Referrer
- Referred student
- Referral status
- Whether referred student is free/premium
- Reward
- Reward status

---



# 8. Referral rewards

Reward depends on the referred student's value.

Example:


| Referred student | Reward |
| ---------------- | ------ |
| Free user        | 2 ETB  |
| Premium user     | 10 ETB |


These are **configurable**, not hardcoded.

Reward lifecycle:

```text
Pending
   ↓
Qualified
   ↓
Approved
   ↓
Paid
```

This also gives you protection against fake referrals.

---



# 9. Payments

Students can purchase Premium.

Payment records contain:

- Student
- Amount
- Payment provider
- Transaction ID
- Status
- Purpose
- Date

Premium should only activate after verified payment.

---



# 10. Notifications

Students can receive:

- New resource notifications
- New course resources
- Premium promotions
- Referral progress
- Referral rewards
- Important announcements

Initial channels:

**Telegram + web/in-app notifications**

The notification system should be designed so push notifications can be added later.

---



# 11. Personalized broadcasts

Admin can create broadcasts and target specific students.

### Targeting

- Everyone
- University
- Stream
- Course
- University + Stream
- University + Course
- Premium users
- Free users

For example:

> **AAU + Natural + Mathematics**

Only matching students receive the message.

### Personalization variables

Support:

```text
{{first_name}}
{{university}}
{{stream}}
{{course}}
```

Example:

> Hi {{first_name}} 👋
>
> New Mathematics resources are available for {{university}} {{stream}} students.

The system replaces the variables automatically.

Admin can:

- Preview
- Send immediately
- Schedule
- See delivery status

---



# 12. Admin panel



## Dashboard

Show:

- Total students
- New students
- Active students
- Premium users
- Revenue
- Referral rewards
- Referral conversions
- Resource usage

---



## Resource management

Admin can:

- Create
- Edit
- Delete
- Upload
- Bulk upload
- Set free/premium
- Publish/unpublish
- Assign stream
- Assign course
- Optionally assign university
- Optionally assign semester

---



## Student management

Admin can:

- Search students
- Filter students
- View profile
- See university
- See stream
- See courses
- See premium status
- See referrals
- Manually grant Premium
- Disable accounts

---



## University management

Admin can:

- Add university
- Edit university
- Disable university
- Set display order

---



## Course management

Admin can manage:

- Natural courses
- Social courses
- Course names
- Active/inactive courses

---



## Broadcast management

Admin can:

- Create broadcast
- Select audience
- Personalize message
- Preview
- Send
- Schedule
- View delivery statistics

---



## Reward management

Admin can:

- View referral rewards
- Approve rewards
- Reject suspicious rewards
- Mark rewards as paid
- View withdrawal requests

---



## Settings

Configurable:

```text
Premium price
Required referrals
Free referral reward
Premium referral reward
Premium duration
```

---



# 13. Telegram bot

Telegram is the initial distribution channel.

Main actions:

```text
/start

📚 My Courses
📖 Resources
⭐ Premium
👥 Refer & Earn
🔔 Notifications
👤 My Profile
```

Referral links use Telegram deep linking:

```text
/start REF123
```

So every signup can be attributed to a referrer.

---



# 14. Technology



### Backend

**Laravel**

### Database

**MySQL**

### Admin

**Filament**

### Student web

**Blade**

### Bot

**Telegram Bot API**

### Queue/cache

**Redis**

### Storage

**S3-compatible storage**

### Payments

Local payment provider(s) selected for launch.

---



# 15. Core database

```text
users

universities
streams
courses

user_courses

resources
resource_downloads
bookmarks

referrals
referral_rewards
withdrawals

subscriptions
payments

notifications
notification_deliveries

broadcasts

admin_users
activity_logs
```

Important: **Semester doesn't need to be a mandatory part of every student or resource.**

---



# 16. Explicitly NOT in MVP

Don't build these yet:

❌ Native mobile app
❌ AI tutor
❌ Student chat
❌ Social feed
❌ Tutor marketplace
❌ Complex gamification
❌ AI recommendation engine
❌ Advanced analytics
❌ Multiple complicated payment systems

First prove the core loop.

---



# 17. Development order



### Phase 1 — Foundation

Laravel + database + authentication

### Phase 2 — Academic structure

Streams + universities + courses + optional semesters

### Phase 3 — Resources

Upload, management, browsing and premium/free access

### Phase 4 — Student onboarding

Stream → optional university → optional semester → course selection

### Phase 5 — Telegram

Bot + onboarding + resource access

### Phase 6 — Referral engine

Referral links + tracking + 5-referral unlock

### Phase 7 — Payments

Premium purchases + verification

### Phase 8 — Rewards

Referral earnings + withdrawal system

### Phase 9 — Notifications

Targeted broadcasts + personalization + scheduling

### Phase 10 — Launch

Analytics, testing, anti-abuse and polish

---



## 🎯 The one metric I'd obsess over

Not downloads.

Not registered users.

**Referral conversion rate.**

> **Of every 100 new students, how many successfully bring another student?**

If that number is strong, you have the beginning of a **self-propagating student network**.