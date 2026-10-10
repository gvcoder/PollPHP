# Technical Design Document - PollPHP

## 1. Executive Summary & Architecture Overview

PollPHP is a lightweight, responsive web application built with vanilla PHP, MySQL, HTML5, CSS3, and JavaScript, designed to be deployed cleanly onto standard shared hosting servers (cPanel / Apache / LiteSpeed) without heavy frameworks, Node build pipelines, or third-party server-side plugins.

```
+---------------------------------------------------------------+
|                       Client Browser                          |
|  - Vanilla HTML5 / CSS3 (Mobile-first, Modern UI)             |
|  - Vanilla JS (Fetch API, Share API, Dynamic DOM)             |
|  - Firebase Auth JS SDK (v9/v10 Modular via CDN)              |
+-------------------------------+-------------------------------+
                                | HTTP(S) / REST-like JSON & HTML
                                v
+---------------------------------------------------------------+
|                 Shared Hosting Web Server                     |
|  .htaccess (URL rewriting & directory protection)             |
|  Routing / Controllers (Vanilla PHP, Clean procedural / OOP)  |
|  Security Middleware (CSRF, XSS, Sessions, Rate Limit)        |
+-------------------------------+-------------------------------+
                                | PDO (Prepared Statements)
                                v
+---------------------------------------------------------------+
|                      MySQL (gvxphp)                           |
|  InnoDB, utf8mb4, indexed relational tables                   |
+---------------------------------------------------------------+
```

---

## 2. Directory Structure (Shared-Hosting Optimized)

```text
PollPHP/
├── .github/
│   └── workflows/
│       └── deploy.yml          # GitHub Actions FTP deploy pipeline
├── config/
│   ├── config.php              # App environment config (DB, base URL, Firebase keys)
│   ├── config.sample.php       # Template for production deployment
│   └── db.php                  # PDO singleton connection with error handling
├── includes/
│   ├── auth.php                # Session & authentication helpers (User & Admin)
│   ├── functions.php           # Security sanitization, CSRF tokens, helpers
│   ├── header.php              # Common HTML head, modern CSS, navbar
│   └── footer.php              # Common footer, scripts
├── api/
│   ├── vote.php                # Anonymous & user voting handler (JSON response)
│   ├── firebase-auth.php       # Verifies Firebase token and registers/logs in session
│   └── poll-stats.php          # Real-time stats API for charts
├── admin/
│   ├── index.php               # Admin platform dashboard & analytics
│   ├── login.php               # Dedicated credential-only admin login
│   ├── users.php               # User account management (suspend / activate)
│   └── polls.php               # Poll management (suspend / toggle status / delete)
├── assets/
│   ├── css/
│   │   └── style.css           # Premium vanilla CSS, mobile-first design tokens
│   └── js/
│       ├── app.js              # Vanilla JS for copy/share, dynamic options
│       ├── vote.js             # AJAX voting handler with instant visual feedback
│       └── firebase-config.js  # Client-side Firebase Google/Social login
├── .htaccess                   # URL rewrite rules, security headers, protected files
├── index.php                   # Home page / Public poll explorer
├── login.php                   # Poll Creator email/password & Firebase Social Login
├── register.php                # Poll Creator registration
├── logout.php                  # Session destruction
├── dashboard.php               # Poll Creator dashboard & account-level metrics
├── create-poll.php             # Simple wizard to create poll
├── poll.php                    # Public poll voting & results view
├── schema.sql                  # Database migration script
└── README.md
```

---

## 3. Database Schema (`gvxphp`)

### 3.1. `users`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Unique user ID |
| `name` | VARCHAR(100) | NOT NULL | Display name |
| `email` | VARCHAR(191) | NOT NULL UNIQUE | User email |
| `password_hash` | VARCHAR(255) | NULLABLE | Nullable for purely social-login users |
| `auth_provider` | ENUM('local', 'google', 'firebase') | DEFAULT 'local' | Authentication provider |
| `firebase_uid` | VARCHAR(128) | NULL UNIQUE | Firebase user ID mapping |
| `role` | ENUM('creator', 'admin') | DEFAULT 'creator' | Role distinction |
| `status` | ENUM('active', 'suspended') | DEFAULT 'active' | Account state |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Account creation date |
| `updated_at` | DATETIME | ON UPDATE CURRENT_TIMESTAMP | Modification date |

### 3.2. `polls`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Internal poll ID |
| `user_id` | INT UNSIGNED | FOREIGN KEY -> users(id) | Poll owner |
| `slug` | VARCHAR(64) | NOT NULL UNIQUE | URL-friendly unique identifier (e.g. `p/x9a8f2`) |
| `question` | VARCHAR(500) | NOT NULL | Poll question |
| `description` | TEXT | NULLABLE | Optional details/context |
| `duration_days` | TINYINT UNSIGNED | NOT NULL (3, 5, 7) | Allowed duration preset |
| `expires_at` | DATETIME | NOT NULL INDEX | Calculated expiration timestamp |
| `status` | ENUM('active', 'suspended', 'closed')| DEFAULT 'active' INDEX | Poll moderation & lifecycle |
| `results_published` | TINYINT(1) UNSIGNED | DEFAULT 0 | 1 = Public results page enabled by creator |
| `total_votes` | INT UNSIGNED | DEFAULT 0 | Denormalized count for performance |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Created time |

### 3.3. `poll_options`
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | INT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Option ID |
| `poll_id` | INT UNSIGNED | FOREIGN KEY -> polls(id) ON DELETE CASCADE | Parent poll |
| `option_text` | VARCHAR(255) | NOT NULL | Option label |
| `vote_count` | INT UNSIGNED | DEFAULT 0 | Incremented on vote |
| `sort_order` | TINYINT UNSIGNED | DEFAULT 0 | Display sequence |

### 3.4. `votes` (Anonymous & Authenticated Tracking)
| Column | Type | Attributes | Description |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | AUTO_INCREMENT PRIMARY KEY | Vote entry ID |
| `poll_id` | INT UNSIGNED | FOREIGN KEY -> polls(id) ON DELETE CASCADE | Voted poll |
| `option_id` | INT UNSIGNED | FOREIGN KEY -> poll_options(id) | Chosen option |
| `voter_identifier` | VARCHAR(64) | NOT NULL INDEX | SHA-256 hash of `IP + UserAgent + Cookie Salt` or User ID |
| `ip_address` | VARCHAR(45) | NOT NULL | For geo/device analytics & fraud check |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Vote timestamp |

*Unique constraint on `(poll_id, voter_identifier)` prevents duplicate votes from the same visitor.*

---

## 4. Technical Implementation Details

### 4.1. Authentication Architecture
1. **Poll Creators:**
   * **Email / Password:** PHP standard `password_hash()` and `password_verify()` with `PASSWORD_DEFAULT` (bcrypt).
   * **Social Login (Firebase):**
     * Frontend initiates Firebase Google Auth popup via standard Firebase JS SDK (loaded via CDN, no npm).
     * On successful login, the client receives the ID token and sends it via POST to `/api/firebase-auth.php`.
     * Backend extracts email/UID, matches or creates the user in `users`, and initializes a secure PHP session (`$_SESSION['user_id']`).
2. **System Admin:**
   * Strict separation: `/admin/login.php`.
   * **No Social Login buttons allowed** for admin accounts.
   * Direct database check enforcing `role = 'admin'` and `status = 'active'`.
   * Session-based privilege checks via `require_admin()` guard helper.

### 4.2. Poll Expiration & Status Lifecycle
* When a poll is created with duration (3 days, 5 days, 1 week), `expires_at` is precalculated: `NOW() + INTERVAL X DAY`.
* A poll is considered open for voting only if:
  `status = 'active' AND expires_at > NOW()`.
* **Poll Editing & Lock Policy:** Once at least 1 vote has been cast, options are permanently locked to preserve data integrity. Poll creators can close or delete the poll at any time.
* **Public Results Lifecycle (Controlled by Poll Owner):**
  * While active: Public users cannot see results (even after voting, they only receive a thank-you confirmation) to prevent bandwagon bias.
  * Once the poll active period is completed (expired): The creator has an option in their dashboard to **"Publish Results Page"** (sets `results_published = 1` and generates a dedicated public URL, e.g., `/results.php?poll=slug`).
  * The creator can **Unpublish / Remove** this public results page at any time with a single toggle (`results_published = 0`). If unpublished, the public URL returns a clean 404/not available page.

### 4.3. Admin & User Setup
* Admin creation handled securely via `php create-admin.php` CLI utility (avoids exposing hardcoded credentials or hashes in public version control).
* Stored using standard `password_hash()` bcrypt.

### 4.3. Anonymous Voting & Duplicate Prevention (Zero-Login)
To allow frictionless anonymous voting without registration while avoiding trivial vote spamming:
1. **Device Fingerprint Hash:** `hash('sha256', $client_ip . $user_agent . $poll_id . $salt)`.
2. **HTTP-only Cookie:** Set a long-lived cookie `voted_{poll_id}=1` on successful vote submission.
3. Both checks are verified before inserting into `votes`. If either matches, return a friendly message showing current results without recording a duplicate.

### 4.4. Social Sharing & WhatsApp Integration
* Built-in Web Share API (`navigator.share`) for mobile browsers with fallback.
* One-click WhatsApp share: `https://api.whatsapp.com/send?text={encoded_question}%20{encoded_url}`.
* OpenGraph & Twitter Card `<meta>` tags on every `poll.php` page so WhatsApp, Facebook, and Twitter show dynamic rich preview cards with the poll question.
* Short clean link copy button with instant clipboard feedback.

### 4.5. Data Analysis & Insights
* **Per Poll Analysis:**
  * Option breakdown with percentages and bar visualizer (pure CSS, no heavy charting dependencies).
  * Votes over time (hourly/daily distribution).
* **Account-Level Creator Dashboard:**
  * Total polls created (active vs expired).
  * Total aggregate votes received.
  * Average engagement per poll.
* **Admin Performance Dashboard:**
  * Overall platform statistics (Total users, active polls, total votes, daily vote volume).
  * Account suspension / reactivation toggles.
  * Poll suspension / reactivation toggles.

### 4.6. Frontend UI/UX (Mobile-First & Modern)
* Clean, responsive CSS with CSS variables (theme tokens, dark mode accent touches, card shadows, responsive grids).
* Accessible form inputs and touch-friendly vote radio buttons (minimum 48px tap targets).
* Instant AJAX voting experience with animated progress fill upon submission.

---

## 5. Security & Shared-Hosting Best Practices

1. **SQL Injection Defense:** 100% prepared statements via PDO with parameterized inputs.
2. **XSS Protection:** Strict `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')` on all outputs.
3. **CSRF Protection:** Synchronizer token pattern (`$_SESSION['csrf_token']`) verified on all POST submissions.
4. **Configuration Safety:** Sensitive database credentials in `config/config.php` protected by `.htaccess` rules (`Deny from all` on `/config/` and SQL files).
5. **Session Security:** `session.cookie_httponly = 1`, `session.use_only_cookies = 1`, `session.cookie_samesite = 'Lax'`.

---

## 6. CI/CD & Deployment Strategy (GitHub Actions)

A GitHub Actions workflow `.github/workflows/deploy.yml` triggers on push to `main`:
* Uses standard `SamKirkland/FTP-Deploy-Action`.
* Uses GitHub Secrets:
  * `FTP_SERVER`
  * `FTP_USERNAME`
  * `FTP_PASSWORD`
  * `FTP_PORT`
* Excludes development files: `.git/`, `.github/`, `README.md`, `requirements.md`, `design.md`, `schema.sql`, `config/config.php` (config is configured directly on host or injected securely).
