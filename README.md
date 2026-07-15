# SK Mobility ERP

PHP + MySQL dealership management platform for Hostinger shared hosting (VPS-ready later).

## Requirements

- PHP 8.0+ (with PDO MySQL)
- MySQL 8
- Apache with `mod_rewrite` (Hostinger default)

## Quick setup (local or Hostinger)

### 1. Database

1. Create a MySQL database (e.g. `sk_mobility`)
2. Import **in order**:
   - `database/schema.sql`
   - `database/seed.sql`

### 2. Environment

```bash
cp .env.example .env
```

Edit `.env`:

```
APP_URL=https://yourdomain.com
APP_BASE=
DB_HOST=localhost
DB_NAME=sk_mobility
DB_USER=your_db_user
DB_PASS=your_db_password
```

If the app lives in a subdirectory (e.g. `public_html/skmobility/public`), set:

```
APP_URL=https://yourdomain.com/skmobility/public
APP_BASE=/skmobility/public
```

### 3. Hostinger shared hosting deploy

**Preferred:** Point the domain document root to the `public` folder (hPanel → Domains → Document Root).

**Alternative:** Upload the whole project, then copy everything from `public/` into `public_html/` and set `BASE_PATH` correctly — or keep structure and set document root to `.../public`.

Ensure these are writable:

- `public/uploads/vehicles/`
- `public/uploads/dealer-documents/`

### 4. Default login

| Field | Value |
|-------|-------|
| Email | `admin@skmobility.com` |
| Password | `Admin@123` |

**Change this password immediately** after first login (Profile → Change password).

## Features delivered

### Phase 1–2
- Auth, RBAC, audit logs
- Dealers, Vehicles, Orders (GST + auto-bill), Payments, Billing PDF, Dashboards

### Phase 3–6
- Inventory (warehouses, adjust, transfer)
- Leads CRM (funnel, status, follow-ups)
- Services + job cards + technicians
- Spare parts + usage
- HR employees & salaries
- Partners & transactions
- Office expenses & categories
- Finance (bank accounts & loans)
- Admin panel (users, roles/permissions, audit logs, settings)
- Reports export (CSV)
- Notifications + 60s unread polling
- Global header search (orders, dealers, vehicles, leads)

## PDF invoices

Without Composer, **Download PDF** opens a GST invoice HTML page — use browser **Print → Save as PDF**.

Optional Dompdf:

```bash
composer require dompdf/dompdf
```

Place `vendor/` in the project root; PDF binary download activates automatically.

## Directory layout

```
public/           ← web root
app/              ← PHP application
database/         ← schema + seed
.env              ← secrets (do not commit on production)
```

## Moving to VPS later

Same codebase. Point Nginx/Apache to `public/`, keep MySQL credentials in `.env`. No module rewrite required.
