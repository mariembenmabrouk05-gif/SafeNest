# 🛡️ SafeNest

A PHP 8 + MySQL web application for child online safety monitoring.

---

## Quick Start

### 1. Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.4+
- A web server (Apache / Nginx / PHP built-in)

### 2. Create the database

```bash
mysql -u root -p < schema.sql
```

Or let PHP do it automatically on first load — `config.php` calls
`install_schema()` which runs `CREATE TABLE IF NOT EXISTS` for both tables.

### 3. Configure the database connection

Edit `config.php` and update:

```php
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

### 4. Serve the app

```bash
# Quick dev server from the project root:
php -S localhost:8000

# Then open: http://localhost:8000
```

### 5. Create accounts

- Visit `http://localhost:8000/signup.php`
- Register a **Parent** account and a **Child** account.

### 6. Test the API

```bash
pip install requests
# Edit CHILD_ID in python_client_example.py to match a real child user id
python3 python_client_example.py
```

---

## Folder Structure

```
safenest/
├── index.php                 # Login page
├── signup.php                # Registration page
├── parent_dashboard.php      # Parent dashboard (event monitor)
├── child_dashboard.php       # Child dashboard (safe zone)
├── logout.php                # Session destruction + redirect
├── config.php                # DB connection, session helpers, schema install
├── schema.sql                # SQL DDL for manual DB setup
├── python_client_example.py  # Python API client demo
├── api/
│   └── add_event.php         # POST endpoint: insert threat_events
├── templates/
│   └── header.php            # Shared nav + dark-mode toggle
└── static/
    └── style.css             # Tailwind CDN import + custom design system
```

---

## API Reference — `POST /api/add_event.php`

### Request

```http
POST /api/add_event.php HTTP/1.1
Content-Type: application/json
X-Api-Key: your_secret_key    (optional, if API_KEY is set in add_event.php)
```

```json
{
  "child_id":    1,
  "threat_type": "cyberbullying",
  "severity":    "high",
  "context":     "Received threatening messages from unknown user."
}
```

| Field | Type | Required | Values |
|-------|------|----------|--------|
| `child_id` | int | ✓ | ID of a user with `role = 'child'` |
| `threat_type` | string | ✓ | Any string ≤ 100 chars |
| `severity` | string | ✓ | `low` · `medium` · `high` |
| `context` | string | ✓ | Descriptive text |

### Success response `201`

```json
{ "success": true, "id": 42, "message": "Threat event #42 created successfully." }
```

### Error response `422`

```json
{ "error": "Validation failed.", "details": ["child_id must be a positive integer."] }
```

---

## Optional: Enable API Key Auth

In `api/add_event.php`, set:

```php
define('API_KEY', 'your-secret-key-here');
```

Then pass it in requests via:
- HTTP header: `X-Api-Key: your-secret-key-here`
- POST field:  `api_key=your-secret-key-here`

---

## Design

The UI uses a **dark-first cyber-security monitor** aesthetic:
- Deep navy/slate backgrounds with electric cyan accents
- `Space Mono` display font + `DM Sans` body font
- Animated scanline overlay and noise texture
- Smooth dark ↔ light mode toggle (persisted in `localStorage`)

---

## License

MIT — use freely.
