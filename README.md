# PollPHP

A lightweight, responsive, and mobile-friendly polling system built with vanilla PHP, MySQL, HTML5, CSS3, and JavaScript, designed for standard shared hosting environments (cPanel / Apache / LiteSpeed) with zero build steps or third-party server-side dependencies.

---

## 🌟 Key Features

### For Poll Creators:
* Simple, fast poll creation (Question, optional description, dynamic options, and preset durations: 3, 5, or 7 days).
* One-click link copying and instant WhatsApp / Social sharing with rich OpenGraph preview cards.
* **Controlled Public Results Lifecycle:** 
  * Results remain hidden while the poll is active to prevent bandwagon bias.
  * Once the poll ends, creators can **Publish** a dedicated public results page (`/results.php?id={slug}`) or **Unpublish** it at any time.
* Dedicated **Poll Analysis view** with vote distribution percentages and daily activity timelines.
* **Account-Level Overview:** Total polls, active polls, aggregate votes received, and average engagement per poll.

### For Public / General Users:
* **Frictionless Anonymous Participation:** Vote immediately without any sign-up or login.
* Dual-layer duplicate vote protection (SHA-256 IP/User-Agent/Salt fingerprinting + long-lived HTTP-only cookie).

### For System Administrators:
* **Strict Secured Credential Login:** Dedicated login (`/admin/login.php`) where social login is strictly disallowed.
* **Platform Performance Dashboard:** Real-time visibility into total registered members, active polls, platform vote volume, and system health.
* **Account Moderation:** Suspend or resume any member's account.
* **Poll Moderation:** Filter polls by status (*All, Active, Inactive, Suspended*) and suspend, resume, or delete any poll.

---

## 💻 Tech Stack & Requirements

* **PHP:** 8.0+ (PDO with MySQL driver enabled)
* **MySQL:** 5.7+ / 8.0+ or MariaDB 10.3+
* **Web Server:** Apache 2.4+ (with `mod_rewrite` enabled) or PHP Built-in Server
* **Frontend:** Modern Vanilla CSS & Vanilla JavaScript (Mobile-first, fully responsive)

---

## 🚀 Local Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone <repo-url>
   cd PollPHP
   ```

2. **Set up the MySQL Database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS gvxphp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p gvxphp < schema.sql
   ```

3. **Verify or Edit Configuration:**
   Inspect `config/config.php` and set your database credentials:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'gvxphp');
   define('DB_USER', 'root');
   define('DB_PASS', 'YourPassword');
   ```

4. **Start the Local Development Server:**
   ```bash
   php -S 127.0.0.1:8000 -t .
   ```
   Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

## 🛡️ Creating an Administrator Account

To prevent exposing credentials in public repositories, default administrator accounts are **not** pre-seeded.

Create your administrator account securely via the CLI helper:
```bash
# Interactive prompt:
php create-admin.php

# Or passing arguments directly:
php create-admin.php "System Admin" "admin@yourdomain.com" "YourStrongPassword!"
```

Once created, log in at `http://127.0.0.1:8000/admin/login.php`.

---

## 🌐 Deployment to Shared Hosting (cPanel / FTP)

### Automated CI/CD (GitHub Actions)
A pre-configured GitHub Actions workflow is available in `.github/workflows/deploy.yml`. When you push to the `main` branch, it automatically synchronizes files via FTP.

To enable it, add the following **Repository Secrets** in GitHub (*Settings > Secrets and variables > Actions*):
* `FTP_SERVER`: Your shared hosting FTP host (e.g., `ftp.yourdomain.com`)
* `FTP_USERNAME`: Your FTP account username
* `FTP_PASSWORD`: Your FTP password
* `FTP_PORT`: `21` (default)
* `FTP_REMOTE_ROOT`: Remote destination directory (e.g., `public_html/`)

*Note: Sensitive files such as `config/config.php`, `schema.sql`, `requirements.md`, and Git folders are automatically excluded from FTP uploads. On your remote server, copy `config/config.sample.php` to `config/config.php` and configure your cPanel database details.*
