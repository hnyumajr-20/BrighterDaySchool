# Brighter Day SMIS — Build PRD (for AI coding agent)

This is the spec to build from. It's written to remove ambiguity, not to read like a dissertation — every number, formula, and endpoint below is meant to be implemented exactly as written. Where something is still a placeholder, it's marked **[CONFIRM]** and you should stop and ask rather than guess.

---

## 0. Stack & Conventions

| | |
|---|---|
| Frontend | React (functional components, hooks) |
| Backend | Laravel (PHP 8.2+), REST API |
| Database | PostgreSQL 14+ |
| Auth | Laravel Sanctum (token-based) |
| Queue | Laravel queue (database driver is fine to start; upgrade to Redis if needed) |
| File storage | Laravel filesystem, local disk to start — **[CONFIRM]** size/type limits per Question 4 below |

**Conventions:**
- API routes: `snake_case` JSON keys, versioned under `/api/v1/`.
- DB tables: plural snake_case (`students`, `fee_transactions`).
- Every table has `id` (bigint PK), `created_at`, `updated_at`.
- Money stored as integer cents (e.g. `4500000` = $45,000.00) — never float.
- All dates in UTC, displayed in the school's local timezone on the frontend.
- Every mutating endpoint enforces RBAC via Laravel policy classes — never rely on frontend hiding alone.

---

## 1. Database Schema (DDL-ready)

```sql
-- ===== Identity & Auth =====
CREATE TABLE users (
  id BIGSERIAL PRIMARY KEY,
  role VARCHAR(20) NOT NULL CHECK (role IN ('admin','registrar','accountant','teacher','librarian','student','parent')),
  username VARCHAR(30) UNIQUE NOT NULL,      -- ID number, e.g. BDS-2026-0147
  email VARCHAR(255) UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
  status VARCHAR(10) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE password_resets (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE email_log (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  type VARCHAR(30) NOT NULL CHECK (type IN ('admission_letter','staff_credentials','password_reset')),
  sent_at TIMESTAMPTZ,
  status VARCHAR(10) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','sent','failed')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ===== People =====
CREATE TABLE parents (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(20) UNIQUE NOT NULL,       -- lookup key for matching
  email VARCHAR(255),
  address TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE classes (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(20) NOT NULL,               -- e.g. "JSS1"
  arm VARCHAR(10) NOT NULL,                -- e.g. "A"
  fee_amount_cents BIGINT NOT NULL DEFAULT 0,
  academic_year_id BIGINT NOT NULL REFERENCES academic_years(id),
  UNIQUE(name, arm, academic_year_id)
);

CREATE TABLE students (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  admission_no VARCHAR(30) UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  dob DATE NOT NULL,
  gender VARCHAR(10) NOT NULL CHECK (gender IN ('male','female')),
  email VARCHAR(255),
  image_path VARCHAR(255),
  is_transfer_student BOOLEAN NOT NULL DEFAULT FALSE,
  transcript_path VARCHAR(255),            -- required only if is_transfer_student = true
  contact VARCHAR(20),
  address TEXT,
  parent_id BIGINT REFERENCES parents(id),
  class_id BIGINT REFERENCES classes(id),
  status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE staff (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  staff_no VARCHAR(30) UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  dob DATE,
  gender VARCHAR(10) CHECK (gender IN ('male','female')),
  email VARCHAR(255),
  image_path VARCHAR(255),
  cv_path VARCHAR(255),
  contact VARCHAR(20),
  address TEXT,
  staff_role VARCHAR(20) NOT NULL CHECK (staff_role IN ('registrar','accountant','teacher','librarian')),
  salary_cents BIGINT NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ===== Academic structure =====
CREATE TABLE academic_years (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(20) NOT NULL,               -- "2026/2027"
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'upcoming' CHECK (status IN ('upcoming','active','closed'))
);

CREATE TABLE semesters (
  id BIGSERIAL PRIMARY KEY,
  academic_year_id BIGINT NOT NULL REFERENCES academic_years(id),
  name VARCHAR(30) NOT NULL,               -- "1st Semester" / "2nd Semester"
  sequence SMALLINT NOT NULL CHECK (sequence IN (1,2)),
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'upcoming' CHECK (status IN ('upcoming','active','closed'))
);

CREATE TABLE periods (
  id BIGSERIAL PRIMARY KEY,
  semester_id BIGINT NOT NULL REFERENCES semesters(id),
  name VARCHAR(30) NOT NULL,               -- "Period 1", "Period 3 - Exam"
  sequence SMALLINT NOT NULL CHECK (sequence IN (1,2,3)),
  is_exam_period BOOLEAN NOT NULL DEFAULT FALSE,   -- true for sequence 3
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'upcoming' CHECK (status IN ('upcoming','active','closed'))
);

CREATE TABLE subjects (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  code VARCHAR(10) UNIQUE
);

CREATE TABLE class_subjects (
  id BIGSERIAL PRIMARY KEY,
  class_id BIGINT NOT NULL REFERENCES classes(id),
  subject_id BIGINT NOT NULL REFERENCES subjects(id),
  teacher_id BIGINT REFERENCES staff(id),
  UNIQUE(class_id, subject_id)
);

CREATE TABLE timetable_slots (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(20) NOT NULL,               -- "Period 1" (daily slot, NOT the same as academic `periods`)
  day_of_week SMALLINT NOT NULL CHECK (day_of_week BETWEEN 1 AND 7),
  start_time TIME NOT NULL,
  end_time TIME NOT NULL
);

CREATE TABLE schedules (
  id BIGSERIAL PRIMARY KEY,
  class_subject_id BIGINT NOT NULL REFERENCES class_subjects(id),
  timetable_slot_id BIGINT NOT NULL REFERENCES timetable_slots(id),
  UNIQUE(timetable_slot_id, class_subject_id)
);

CREATE TABLE results (
  id BIGSERIAL PRIMARY KEY,
  student_id BIGINT NOT NULL REFERENCES students(id),
  subject_id BIGINT NOT NULL REFERENCES subjects(id),
  period_id BIGINT NOT NULL REFERENCES periods(id),
  score NUMERIC(5,2) NOT NULL CHECK (score >= 0 AND score <= 100),
  recorded_by BIGINT REFERENCES staff(id),
  UNIQUE(student_id, subject_id, period_id)
);

-- ===== Attendance =====
CREATE TABLE student_attendance (
  id BIGSERIAL PRIMARY KEY,
  student_id BIGINT NOT NULL REFERENCES students(id),
  date DATE NOT NULL,
  status VARCHAR(10) NOT NULL CHECK (status IN ('present','absent','late')),
  method VARCHAR(10) NOT NULL DEFAULT 'manual' CHECK (method IN ('manual','rfid')),
  recorded_by BIGINT REFERENCES staff(id),
  UNIQUE(student_id, date)
);

CREATE TABLE staff_attendance (
  id BIGSERIAL PRIMARY KEY,
  staff_id BIGINT NOT NULL REFERENCES staff(id),
  date DATE NOT NULL,
  status VARCHAR(10) NOT NULL CHECK (status IN ('present','absent','late')),
  method VARCHAR(10) NOT NULL DEFAULT 'manual' CHECK (method IN ('manual','rfid')),
  recorded_by BIGINT REFERENCES staff(id),
  UNIQUE(staff_id, date)
);

-- ===== Finance =====
CREATE TABLE fee_transactions (
  id BIGSERIAL PRIMARY KEY,
  student_id BIGINT NOT NULL REFERENCES students(id),
  amount_cents BIGINT NOT NULL,            -- positive = charge, negative = payment/credit
  type VARCHAR(20) NOT NULL CHECK (type IN ('charge','payment','discount','adjustment')),
  note TEXT,
  recorded_by BIGINT REFERENCES staff(id),
  academic_year_id BIGINT NOT NULL REFERENCES academic_years(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ===== Library =====
CREATE TABLE books (
  id BIGSERIAL PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(150),
  isbn VARCHAR(20),
  copies_total SMALLINT NOT NULL DEFAULT 1,
  copies_available SMALLINT NOT NULL DEFAULT 1
);

CREATE TABLE book_loans (
  id BIGSERIAL PRIMARY KEY,
  book_id BIGINT NOT NULL REFERENCES books(id),
  student_id BIGINT NOT NULL REFERENCES students(id),
  issued_at DATE NOT NULL DEFAULT CURRENT_DATE,
  due_date DATE NOT NULL,
  returned_at DATE
);

-- ===== Admissions (pre-approval pipeline; students table doubles as this once status flips) =====
-- No separate table needed: `students.status = 'pending'` IS the admissions queue.
```

---

## 2. Grading Logic — EXACT formula, confirmed with WAEC national standard

This is the single most consequence-sensitive piece of logic in the system. Implement exactly as follows — do not "simplify" or "optimize" this math without re-confirming with the product owner.

```
// Step 1: Period Average (one period, across all subjects, for one student)
PeriodAverage(student, period) =
    SUM(results.score WHERE student_id, period_id) / COUNT(subjects taken that period)

// Step 2: Subject Semester Average (one subject, across its 3 periods)
SubjectSemesterAverage(student, subject, semester) =
    (Period1.score + Period2.score + Period3.score) / 3

// Step 3: Semester Average (whole student, across all subjects)
SemesterAverage(student, semester) =
    SUM(SubjectSemesterAverage for each subject) / COUNT(subjects)

// Step 4: Yearly Average (whole student)
YearlyAverage(student) =
    (SemesterAverage(student, semester1) + SemesterAverage(student, semester2)) / 2

// Promotion decision
PROMOTE if YearlyAverage >= 60.0   // WAEC LJHSCE national pass mark — [CONFIRM] if Brighter Day uses a different internal threshold
REPEAT  if YearlyAverage <  60.0
```

**Implementation notes:**
- Compute these as derived values (a service/query), not stored columns — only `results.score` is ever written directly by a Teacher.
- Build this as a single `GradeCalculationService` class in Laravel with methods `periodAverage()`, `subjectSemesterAverage()`, `semesterAverage()`, `yearlyAverage()`, `promotionStatus()` — unit test each one independently before wiring up the report card.
- Write at least 5 unit tests with hand-calculated expected values before trusting this in the UI.

---

## 3. Academic Period Lifecycle

**[CONFIRM — still open]:** when a period ends and the next period's start date hasn't arrived yet, should grade entry lock during the gap, or should the next period auto-open immediately? **Default until told otherwise: lock during the gap** (safer — prevents grades silently landing in the wrong period). This is a one-line change in the scheduler job either way, so don't over-engineer it — just make the behavior configurable via a single constant/config value so it's a 30-second change if the answer comes back different.

**Scheduler job (run daily, e.g. Laravel scheduled task at midnight):**
```
for each period where status = 'active' and end_date < today:
    set status = 'closed'
for each period where status = 'upcoming' and start_date <= today:
    set status = 'active'
// A period's parent semester closes automatically once its period 3 (exam) closes.
// A semester's parent academic_year closes once both semesters close.
```

Grade entry (`POST /api/v1/results`) must check `period.status == 'active'` server-side before accepting a score — never trust the frontend to only show the "current" period.

---

## 4. API Surface (v1)

Grouped by module. All routes prefixed `/api/v1/`. Auth required unless noted.

### Auth
```
POST   /auth/login                 { username_or_email, password } → token
POST   /auth/logout
POST   /auth/change-password       { old_password, new_password }
POST   /auth/forgot-password       { email }
POST   /auth/reset-password        { token, new_password }
```

### Admin
```
POST   /staff                      create staff → triggers credential email job
GET    /staff, GET /staff/{id}, PUT /staff/{id}, DELETE /staff/{id}
POST   /classes, GET /classes, PUT /classes/{id}
POST   /subjects, GET /subjects
POST   /academic-years, POST /semesters, POST /periods
PATCH  /users/{id}/status          activate/deactivate any account
POST   /users/{id}/reset-password  admin-triggered reset
GET    /reports/dashboard          consolidated school-wide stats
```

### Registrar
```
POST   /students                   intake form → status='pending'
GET    /parents?phone={number}     lookup — returns match or 404
POST   /parents                    create new parent
POST   /students/{id}/approve      → generates admission_no, user, sends email job
PUT    /students/{id}/class        update class placement
POST   /class-subjects             attach subject+teacher to class
POST   /schedules                  place class_subject into timetable_slot (validates no double-booking)
GET    /admissions?status=pending
```

### Teacher
```
POST   /results                    { student_id, subject_id, period_id, score } — checks period.status=='active'
GET    /results?class_id=&subject_id=&period_id=
POST   /attendance/students        { student_id, date, status }
GET    /attendance/students?class_id=&date=
```

### Accountant
```
POST   /fee-transactions           { student_id, amount_cents, type, note }
GET    /fee-transactions?student_id=
GET    /students/{id}/balance
```

### Librarian
```
POST   /books, GET /books, PUT /books/{id}
POST   /book-loans                 issue
PATCH  /book-loans/{id}/return
GET    /book-loans?overdue=true
```

### Student / Parent (read-scoped to own data — enforced via policy, not just query filter)
```
GET    /me/results
GET    /me/attendance
GET    /me/fee-balance
GET    /me/admission-letter        (student only)
GET    /children                   (parent only — list linked students)
GET    /children/{id}/results etc. (parent only)
```

---

## 5. RBAC Enforcement

Implement as Laravel Policy classes, one per resource (`StudentPolicy`, `ResultPolicy`, `FeeTransactionPolicy`, etc.), registered in `AuthServiceProvider`. Every controller method calls `$this->authorize()` — no controller trusts the route alone. Reference the matrix below when writing each policy's `view`/`create`/`update`/`delete` methods.

| Resource | Admin | Registrar | Accountant | Teacher | Librarian | Student | Parent |
|---|---|---|---|---|---|---|---|
| students | CRUD | CRUD | R | R (own class) | — | R (self) | R (own child) |
| results | CRUD | R | — | CRUD (own subjects) | — | R (self) | R (own child) |
| fee_transactions | CRUD | R | CRUD | — | — | R (self) | R (own child) |
| books/book_loans | CRUD | — | — | R | CRUD | R (own loans) | R (child's loans) |
| staff | CRUD | — | — | R (self) | R (self) | — | — |

---

## 6. UI/UX Specification

The goal is a plain, fast, functional tool — closer to a bank's internal admin panel than a consumer app. Nobody using this (a registrar, an accountant, a parent on a low-end phone) should have to figure out what an icon means. If it feels like it needs explaining, it's wrong.

### 6.1 Explicit "don't" list
These are the specific things that make an interface look generic and AI-generated — avoid all of them:
- No icon-only buttons in primary actions. Every button has a text label. An icon can *accompany* a label, never replace it.
- No decorative icons next to every menu item, stat, or table row "just because." A sidebar nav item is text first; an icon is optional and only for items where the icon is genuinely unambiguous (home, settings, logout).
- No gradient backgrounds, no glassmorphism, no card-with-colored-icon-in-a-circle pattern repeated for every stat.
- No hero sections, no illustrated empty states, no mascot/avatar graphics.
- No more than one accent color. Status (paid/unpaid, present/absent) uses text + a small color dot or badge, not a full-color card.
- No animation beyond a simple fade/slide on page transitions. No hover-scale effects, no confetti on success.

### 6.2 Layout shell (same for all seven dashboards)
```
┌─────────────────────────────────────────────┐
│ Brighter Day SMIS        [User name ▾] [Logout] │  ← top bar, plain, no logo animation
├───────────┬─────────────────────────────────┤
│ Dashboard │  Page Title            [+ New]  │  ← one primary action, top right, text label
│ Students  │  ─────────────────────────────  │
│ Results   │  [Table or form content]        │
│ Fees      │                                  │
│ ...       │                                  │
└───────────┴─────────────────────────────────┘
```
- Left sidebar: plain text list of the modules that role has access to (per RBAC matrix). No icons required — if used, one small monochrome icon per item, same style throughout, never colored per-item.
- Every list view (students, results, fees, books) is a **plain table**: sortable columns, pagination, search box above it. Row actions are text links ("Edit", "View") right-aligned, not icon buttons.
- Every create/edit view is a **plain form**: label above field, one column on mobile, two columns max on desktop, inline validation text below the field (red text, no icon badge).

### 6.3 Component conventions
- **Buttons:** one solid-fill primary style (used once per screen, for the main action), one outline/secondary style, one text-only tertiary style for low-stakes actions (Cancel). That's it — three button styles total, reused everywhere.
- **Status indicators:** text label first ("Paid", "Overdue"), optionally with a small colored dot before it. Never a full colored badge/pill for every single row of a table — it turns into visual noise fast at scale (e.g. 40 students).
- **Tables:** zebra striping optional and subtle if used; otherwise just hairline row dividers. No card-based "table replacement" on desktop — save card layouts for mobile only, where a table doesn't fit.
- **Forms:** group related fields under a plain text subheading (e.g. "Bio-data", "Contact", "Parent/Guardian") rather than one long unbroken list — this matters especially for the student intake form, which has ~10 fields.
- **Empty states:** one sentence of plain text ("No students in this class yet.") plus one action button. No illustration.
- **Error states:** plain text explaining what happened and what to do, in the interface's own voice, not an apology. E.g. "Score must be between 0 and 100." not "Oops! Something went wrong."

### 6.4 Typography & color
- System font stack (e.g. `-apple-system, Segoe UI, Roboto, sans-serif`) — no custom webfont. This is a deliberate performance choice given Liberia's connectivity context (Section 1), not a design shortcut.
- One neutral palette (grays) for structure, one single accent color for primary actions and links, one red for errors/destructive actions, one green for success/positive status. Four colors total, no more.
- Base font size 16px minimum on forms/tables — this will be used on phones by parents and students, not just desktop by office staff.

### 6.5 Performance & accessibility (ties to Section 1's connectivity data)
- No icon font libraries (e.g. loading all of Font Awesome for six icons) — use a handful of inline SVGs instead, or a tree-shaken icon set (e.g. lucide-react) importing only what's used.
- Every interactive element keyboard-navigable with a visible focus state.
- Respect `prefers-reduced-motion`.
- Images (student/staff photos) served at a reasonable compressed size, not full-resolution uploads rendered at 40×40px.

## 7. Build Phases & Acceptance Criteria

Each phase ships when its acceptance criteria pass — treat these as the Definition of Done, not a suggestion.

### Phase 0 — Foundation
- [ ] Laravel + React scaffolding, PostgreSQL connected
- [ ] Login/logout/forced password change/forgot-password all working end-to-end
- [ ] RBAC middleware rejects a cross-role request with 403 (write a test proving a Teacher token can't hit an Accountant-only route)
- [ ] Email queue sends a test email asynchronously without blocking the request

### Phase 1 — Admin Core
- [ ] Admin can create a class with a fee, a subject, an academic year/semester/period tree
- [ ] Admin can add a staff member; credential email is queued and logged in `email_log`
- [ ] Scheduler job correctly transitions a period from `upcoming` → `active` → `closed` (write a test that fast-forwards dates)

### Phase 2 — Registrar
- [ ] Student intake form saves with `status='pending'`
- [ ] Parent phone lookup returns an existing match or 404 correctly
- [ ] Approving a student generates admission_no + user + sends admission-letter email
- [ ] Schedule builder rejects a double-booked teacher or class (test both cases)

### Phase 3 — Teacher
- [ ] Result entry rejects a score outside 0–100
- [ ] Result entry rejects writing to a `closed` period
- [ ] `GradeCalculationService` unit tests pass for all 4 formula steps with hand-verified numbers
- [ ] Manual attendance recorded per class per day

### Phase 4 — Accountant
- [ ] Fee balance = SUM of all `fee_transactions` for a student, computed correctly with mixed charge/payment/discount types
- [ ] Class fee auto-populates as the first `charge` transaction when a student is approved into that class

### Phase 5 — Librarian
- [ ] Issuing a book decrements `copies_available`; returning increments it
- [ ] Cannot issue a book with `copies_available = 0`

### Phase 6 — Student/Parent dashboards
- [ ] A Student token can only ever see their own results/attendance/fees (test by attempting to query another student's ID)
- [ ] A Parent token can only see their linked children's data

### Phase 7 — Staff attendance
- [ ] Admin/Registrar can mark staff attendance; staff cannot self-mark

### Phase 8 — Integration testing
- [ ] Full RBAC matrix re-verified with automated tests, not manual clicking
- [ ] Closing a period correctly locks further grade entry to it

### Phase 9 — RFID (stretch, only if time remains)
- [ ] Reader endpoint accepts a scan and writes an attendance row with `method='rfid'` — no schema change required if Phase 0–8 was built correctly

---

## 8. Open Items — resolve before the relevant phase starts

1. **Promotion pass mark** — using **60%** as default (WAEC LJHSCE national standard). Confirm or override before Phase 3.
2. **Period-gap behavior** — defaulting to **lock during gap**. Confirm or override before Phase 0/1 (cheap now, annoying later).
3. **Fee adjustment approval** — does a discount/adjustment need Admin sign-off, or can Accountant apply directly? Blocks Phase 4.
4. **File upload limits** — size/type for transcripts, CVs, photos, based on actual hosting environment. Blocks Phase 0 filesystem config.

---

## 9. What changed from the academic dissertation version

If you're cross-referencing against Chapters 4–6 of the dissertation: this document is the same system, but every formula, table, and endpoint here is meant to be copy-paste-into-code accurate, whereas the dissertation version is written for a human reader/supervisor. When the two disagree (e.g. exact grading formula), **this document wins** — the dissertation should be updated to match it, not the other way around.
