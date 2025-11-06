# Pre‑Trip Inspections Form — v6.4 (full, r3)

Includes:
- `index.php` (editable colored dropdowns; responsive; file upload last; optional "Your e‑mail")
- `process.php` (DB migrations for `inspections` + `companies.color`; 27 placeholders; env‑based email; optional user copy)
- `config.php` (set DB creds; email via env)
- `style.css` (combobox, dropzone, sr‑only, error states)
- `script.js` (combobox logic + dropzone + "required file" inline message)
- `seed_companies_with_colors.sql` (preload options with colors)
- Folders: `uploads/`, `logs/`, `images/` (logo.png)

Setup:
1) Create DB and set creds in `config.php`.
2) (Optional) `mysql < seed_companies_with_colors.sql`
3) (Optional) Set env:
   MAIL_TO=ops@example.com
   MAIL_FROM=inspections@example.com
4) Open `index.php`. Submit with at least one photo.
