# Competition Voting (PHP + MySQL)

Server-rendered PHP app for:
- passwordless email login (magic link; dev shows link on screen)
- registering exactly one competition entry per user
- voting 1–5 on weighted aspects
- results page visible only to whitelisted users

## Setup

1. Create `.env` from `.env.example` and set DB credentials.
2. Create the database and tables:
   - Run `database/schema.sql` in your MySQL client.
   - If upgrading from 1–5 scoring to 1–10, also run `database/migrations/001_scores_1_10.sql`.
3. Insert aspects (example):
   - `INSERT INTO aspects (label, weight, sort_order, active) VALUES ('UX', 2.0, 1, 1);`
4. Start a dev server:
   - `php -S localhost:8000 -t public public/router.php`
5. Open:
   - `http://localhost:8000/register`

## cPanel / Shared Hosting (Single Supported Layout)

Deploy by uploading the whole project to the domain’s **document root**, keeping the folder structure:
- `public/` (web entry, assets, uploads)
- `src/` (PHP code, must NOT be directly accessible)
- `database/` (SQL files, must NOT be directly accessible)
- root `.htaccess` (required)

The included root `.htaccess` blocks `/src`, `/database`, and `/.env`, and rewrites all requests into `/public` so `/login`, `/vote`, etc. work from the domain root.

## Notes
- Requires PHP 7.4+ (PHP 8+ recommended) with PDO MySQL and `fileinfo` enabled.
- If uploads fail for files under 10 MiB, raise PHP limits: `upload_max_filesize` and `post_max_size` (common default is 2M).
- In `APP_ENV=dev`, the login page shows the magic-link URL instead of sending email.
- In `APP_ENV=prod`, `mail()` is used; configure your server's mail transport and set `MAIL_FROM`.
- Magic-link tokens expire after 30 minutes.
- Uploads are stored in `public/uploads/` (image/* MIME only); actual max size depends on server limits.
