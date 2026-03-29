# PasteChi

PasteChi is a self-hosted, zero-knowledge paste service for PHP shared hosting and VPS setups.

## Highlights

- Browser-side encryption/decryption (Web Crypto API)
- Short 6-digit code routing for paste retrieval
- Optional encrypted attachments
- TTL / max views / optional unique-view counting
- Simple installation with one installer form
- Relative URL strategy for easier deployment under different domains/subfolders

## New built-in pages

- `documents.php` — brief explanation of how the service works, security boundaries, and AI-assisted development note
- `privacy.php` — privacy policy summary and data handling notes
- `mirror.php` — “Create your mirror” page with:
  - GitHub project link
  - Auto-generated ZIP archive link
  - SHA-256 hash display for archive integrity

## Easy installer

Open `install.php` and set only:

1. Site title (`APP_NAME`)
2. Database info (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`)
3. Payload policy (`MAX_PAYLOAD_BYTES`)
4. Attachment policy (`ATTACHMENT_MAX_BYTES`, `ATTACHMENT_ALLOWED_EXTENSIONS`)

Installer writes these settings to `.env`.

## Hosting requirements

- PHP 8.1+
- PDO MySQL
- MySQL/MariaDB
- Apache with `mod_rewrite` (or equivalent Nginx rewrite)
- Optional but recommended: PHP `zip` extension for mirror ZIP generation

## Shared hosting quick start

1. Upload project files.
2. Create MySQL database and user.
3. Open `install.php` in browser and submit the installer form.
4. Run migrations (or use included migration script):
   - `php migrate.php`
5. Open homepage.

## Security notes

- Keep your browser and server updated.
- Cryptographic guarantees rely on browser crypto libraries/runtime.
- Use HTTPS in production.
- For stronger access control, use password + fragment key options when creating pastes.

## Mirror integrity

From `mirror.php`, generate/update the ZIP and verify the shown SHA-256 before distribution.

GitHub repository: https://github.com/alisentinel/pastechi
