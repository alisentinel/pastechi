# PasteChi

If the server cannot read it, nobody can.

PasteChi is a dependency-free, self-hosted, zero-knowledge paste service built for cheap shared hosting.
All encryption and decryption happen in the browser via Web Crypto API.

## Features

- Zero-knowledge E2EE: server stores only encrypted payload + minimal lifecycle metadata
- 6-digit tracking code routing (`/123456`) and homepage search by code
- Bootstrap 5 modern UI with minimal custom CSS
- Tracking code revealed only after encryption + successful save
- QR code shown for completed share URL (mobile scan)
- Key derivation with PBKDF2 using: tracking code + optional password + URL fragment secret
- AES-256-GCM encryption with per-paste random IV + salt
- Optional encrypted attachment (browser-side encrypted, server stores ciphertext only)
- Expiry controls:
	- TTL destroy-after-time
	- destroy-after-X-views
	- optional unique-viewer counting mode (cookie-based)
- Time-lock support (client-enforced decryption gate)
- Optional link binding:
	- hashed IP prefix binding
	- browser fingerprint hash binding
- Privacy-preserving forensics mode (aggregated hourly view buckets)
- E2EE discussion mode (encrypted message polling fallback)
- Brute-force/enumeration hardening:
	- endpoint window rate limits (no persisted client identifiers)
	- artificial randomized delay
	- generic unavailable responses

## Project Structure

```txt
.
├─ .htaccess
├─ index.php
├─ create.php
├─ paste.php
├─ cron_cleanup.php
├─ api/
│  ├─ bootstrap.php
│  ├─ context.php
│  ├─ create.php
│  ├─ get.php
│  └─ discussion.php
├─ lib/
│  ├─ config.php
│  ├─ utils.php
│  ├─ db-config.php
│  └─ db-config.local.php.example
├─ assets/
```txt
.
├─ .htaccess
├─ .env.example
├─ index.php
├─ create.php
├─ paste.php
├─ cron_cleanup.php
├─ api/
│  ├─ bootstrap.php
│  ├─ context.php
│  ├─ create.php
│  ├─ get.php
│  ├─ discussion.php
│  └─ log.php
├─ lib/
│  ├─ config.php
│  ├─ utils.php
│  └─ db-config.php
├─ migrations/
│  ├─ 001_create_pastes_table.sql
│  ├─ 002_create_discussions_table.sql
│  ├─ 003_create_rate_limits_table.sql
│  └─ 004_create_logs_table.sql
- KDF: `PBKDF2` (`SHA-256`, browser-native, configurable iterations)
- KDF input material:
	- 6-digit tracking code
	- optional password
	- URL fragment secret (`#k=...`) generated client-side
	- purpose label (`paste` / `discussion`)

The server never receives the URL fragment secret, so server-side compromise does not directly reveal keys.

### Envelope stored on server

- ciphertext (base64url)
- IV (base64url)
- salt (base64url)
- KDF iteration count
- lifecycle metadata (TTL / max views / lock timestamp / mode flags)

No plaintext is stored.

## UX Flow

1. User opens `create.php`
2. Client generates random 6-digit code + URL fragment key secret
3. Client encrypts payload (`title`, `content`, binding claim) locally
4. Client sends encrypted envelope to `api/create.php`
5. Share URL format: `/123456#k=<secret>`
6. Viewer loads `/123456`, client fetches ciphertext, derives key, decrypts locally

## API Endpoints

- `GET /api/context.php`
	- Returns `serverTime` + transient IP-derived context for optional binding (not persisted server-side)
- `POST /api/create.php`
	- Accepts encrypted envelope + lifecycle settings
- `GET /api/get.php?code=123456`
	- Returns encrypted envelope and public metadata
	- Increments view count atomically
	- Handles max-view deletion (including unique-view mode)
- `GET /api/discussion.php?code=123456&since=<id>`
	- Returns encrypted discussion messages
- `POST /api/discussion.php?code=123456`
	- Accepts encrypted discussion message
- `POST /api/log.php`
	- Accepts sanitized client diagnostics for bug tracing

## Shared Hosting Deployment

### Requirements

- PHP 8.1+ with PDO extension
- MySQL 5.7+ / MariaDB 10.2+
- Apache with `mod_rewrite` (or equivalent route adaptation on Nginx)

### Steps

1. Create MySQL database and user:
	```sql
	CREATE DATABASE pastechi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	CREATE USER 'pastechi_user'@'localhost' IDENTIFIED BY 'secure-password';
	GRANT ALL PRIVILEGES ON pastechi.* TO 'pastechi_user'@'localhost';
	FLUSH PRIVILEGES;
	```
2. Upload the entire folder to web root (or subfolder).
3. Copy `.env.example` to `.env` and configure DB credentials:
	 - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
	 - `ATTACHMENT_MAX_BYTES` (max attachment size in bytes, set `0` to disable)
	 - `ATTACHMENT_ALLOWED_EXTENSIONS` (comma-separated extensions or `*` for any)
	 - `MAX_CREATE_REQUEST_BYTES` (optional override for very large encrypted requests)
	 - Use a strong, unique password for `DB_PASS`
	 - **Do NOT commit `.env` to version control**
4. Run database migrations (from root directory):
	```bash
	mysql -u pastechi_user -p pastechi < migrations/001_create_pastes_table.sql
	mysql -u pastechi_user -p pastechi < migrations/002_create_discussions_table.sql
	mysql -u pastechi_user -p pastechi < migrations/003_create_rate_limits_table.sql
	mysql -u pastechi_user -p pastechi < migrations/004_create_logs_table.sql
	mysql -u pastechi_user -p pastechi < migrations/005_add_access_columns_to_pastes.sql
	```
5. Change `SERVER_PEPPER` in `lib/config.php` to a long random secret.
6. (Optional) configure cron:
	 - `php /path/to/pastechi/cron_cleanup.php`

Done. Open `/` in browser.

## Security Hardening Notes

- Use HTTPS only.
- Set strong random `SERVER_PEPPER` per deployment.
- Consider Web Application Firewall + fail2ban at host level.
- Keep `MAX_PAYLOAD_BYTES` conservative for shared hosting.
- Rotate server logs and avoid verbose request-body logging.

## Basic Threat Model

### Protected against

- Honest-but-curious server reading plaintext (cannot decrypt without client-held fragment)
- Bulk ciphertext scraping without fragment secret
- Basic online brute-force and code enumeration (rate limits + random delays)
- Metadata minimization (no accounts, no trackers, no plaintext)

### Partially protected / trade-offs

- Time-lock is client-enforced: a modified client can bypass UI checks.
- Optional IP binding depends on hash-context consistency across network changes.
- Traffic analysis and access timing at infrastructure/log level remain possible.
- 6-digit code space is small; security relies on encrypted payload + URL fragment + optional password + throttling.

### Out of scope in this minimal stack

- HSM-backed key custody
- anonymous network-level transport obfuscation
- multi-party deniable messaging protocols

## Operational Notes

- MySQL/MariaDB backend for storage.
- No Composer dependencies.
- No build toolchain.
- Works on shared hosting with PHP 8.1+ and database access.
- Logs are stored in SQL table `logs`.

## License

Open-source scaffold. Add your preferred license before production release.
