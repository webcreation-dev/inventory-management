# PrintManager Module

Centralized printing for OFF POS using the **official QZ Tray 2.x security workflow**.

- Docs: https://qz.io/docs/signing
- Key storage: `storage/app/qz/` (see `storage/app/qz/README.md`)

## 1. Generate real signing keys (OpenSSL)

Run from project root:

```bash
mkdir -p storage/app/qz

openssl genrsa -out storage/app/qz/private-key.pem 2048

openssl req -new -x509 -key storage/app/qz/private-key.pem \
  -out storage/app/qz/digital-certificate.txt -days 3650 \
  -subj "/CN=Your Company Name/O=Your Organization/C=US"
```

**Where files go:**

| File | Path | Purpose |
|------|------|---------|
| Private key (secret) | `storage/app/qz/private-key.pem` | Server-side SHA512 signing only |
| Certificate (public) | `storage/app/qz/digital-certificate.txt` | Served to QZ Tray via API |

Never commit these files. They are ignored by `storage/app/qz/.gitignore`.

**Alternative (local dev):** QZ Tray → Advanced → Site Manager → Create New → copy demo keys from desktop `QZ Tray Demo Cert` folder into `storage/app/qz/`.

## 2. Laravel configuration

Add to `.env`:

```env
PRINT_ENV=production
PRINT_DEFAULT_METHOD=qz_tray
PRINT_LOG_ENABLED=true

QZ_CERTIFICATE_PATH=
QZ_PRIVATE_KEY_PATH=
QZ_SIGNATURE_ALGORITHM=SHA512
QZ_TRAY_HOST=localhost
QZ_TRAY_RECONNECT_INTERVAL_MS=3000
QZ_TRAY_CONNECTION_TIMEOUT_MS=10000
```

| Variable | Description |
|----------|-------------|
| `PRINT_ENV` | `development` = unsigned QZ prompts; `production` = requires cert + key + signing |
| `QZ_CERTIFICATE_PATH` | PEM x509 path (default: `storage/app/qz/digital-certificate.txt`) |
| `QZ_PRIVATE_KEY_PATH` | PEM RSA private key (default: `storage/app/qz/private-key.pem`) |
| `QZ_SIGNATURE_ALGORITHM` | Must be `SHA512` for QZ Tray 2.1+ |
| `QZ_TRAY_HOST` | QZ websocket host (usually `localhost`) |

Config file: `config/printmanager.php` (merged from module config).

## 3. Security API endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/print/qz/certificate` | Returns real PEM certificate from disk |
| POST | `/print/qz/sign` | Signs QZ `request` payload with OpenSSL SHA512 |
| GET | `/print/qz/status` | Certificate/key configuration diagnostics |

All routes require auth + CSRF on POST. Private key is **never** exposed.

## 4. Development vs production

**Development (`PRINT_ENV=development`):**

- If key files are missing, JS skips `setCertificatePromise` / `setSignaturePromise`
- QZ Tray shows Allow/Block trust prompts (unsigned mode)
- Once you add demo keys, signing works automatically without changing env

**Production (`PRINT_ENV=production`):**

- Certificate and private key files are **required**
- Every QZ connection is signed with SHA512
- Missing files return JSON errors (503) — no fake signatures

## 5. JavaScript integration

Include in any Blade view:

```blade
@include('printmanager::partials.integration-scripts')
```

Or manually:

```blade
@include('printmanager::partials.scripts')
@include('printmanager::partials.config-script')
<script src="{{ asset('public/backend_assets/js/printmanager/qz-manager.js') }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/printer.js') }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/receipt-printer.js') }}"></script>
```

Files:

- `qz-manager.js` — official security promises, connect/reconnect/disconnect
- `printer.js` — printer detection, QZ print (HTML, PDF, raw ESC/POS, cash drawer)
- `receipt-printer.js` — receipt API + job execution
- `settings.js` — settings UI + QZ status display

## 6. Print types supported

| Type | Endpoint | QZ payload |
|------|----------|------------|
| Receipt | `POST /print/receipt` | ESC/POS raw (base64) |
| HTML | `POST /print/html` | HTML plain |
| Raw ESC/POS | `POST /print/raw` | Raw base64 |
| PDF | `POST /print/pdf` | PDF base64 |
| Cash drawer | receipt/raw with `open_cash_drawer` | ESC/POS kick command |

Barcode/QR are embedded in receipt ESC/POS data via `ReceiptBuilder`.

## 7. Quick usage (PHP)

```php
use Modules\PrintManager\Support\PrintManager;

$result = PrintManager::printReceipt([
    'title' => 'My Store',
    'items' => [['name' => 'Coffee', 'qty' => 2, 'price' => 3.50]],
    'totals' => [['label' => 'Grand Total', 'amount' => 7.00, 'emphasis' => true]],
], ['module' => 'Sale', 'branch_id' => session('outlet.outlet_id')]);
```

## 8. Testing checklist

1. Install [QZ Tray](https://qz.io/download/) on the client PC
2. Generate or copy keys into `storage/app/qz/`
3. Set `PRINT_ENV=production` in `.env`
4. Visit `/print/settings` — security status should show certificate & key OK
5. Click **Refresh Printers** — installed printers should populate
6. Visit `/print/test` — run test print
7. Configure counter printer to **QZ Tray Print** and complete a POS sale

## Architecture

- `QzSecurityService` — certificate loading + OpenSSL SHA512 signing
- `QzTraySecurityController` — `/print/qz/*` API
- `QZTrayService` — print job preparation for QZ driver
- `PrinterService` — orchestrator with browser fallback
- `ReceiptBuilder` — text/html/escpos receipt builder
