# Nawasara Aspirations

Citizen complaint and aspiration channel for the Nawasara framework — the
backend behind **Lapor Bunda** in the GO super-app for Kabupaten Ponorogo.

A citizen files a report from their phone; it is dispatched to the responsible
department automatically, worked on under a binding SLA, verified by a superior
inside that department, and handed back to the citizen to rate.

---

## What it does

- **Automatic dispatch** — a report reaches the responsible OPD the moment it
  arrives. There is no central inbox in front of it.
- **Three SLA clocks** — first response, resolution, and verification, each
  stamped on arrival and escalated hourly when missed.
- **In-department verification** — the officer nominates their own Kabid, who
  approves or returns the work. Nobody can approve their own.
- **Per-OPD isolation** — enforced by a global scope, not by where-clauses.
- **Anonymous reporting** — hides the name from staff, never the reporter from
  the system.
- **Duplicate detection** — offers "Saya Juga Mengalami" instead of blocking.
- **Content screening** — flags profanity and slurs for review; never rejects.
- **Photo evidence** — stored in MinIO behind short-lived presigned URLs.

## Installation

```bash
composer require nawasara/aspirations

php artisan migrate
php artisan db:seed --class="Nawasara\Aspirations\Database\Seeders\PermissionSeeder"
php artisan db:seed --class="Nawasara\Aspirations\Database\Seeders\CategorySeeder"
```

The category seeder reports any category whose OPD is missing from the registry.
Those categories cannot be dispatched automatically — reports on them stop at
the front door until the department is registered.

```bash
# Registers the five OPD this package's categories point at
php artisan db:seed --class="Nawasara\Registry\Database\Seeders\OpdSeeder"
```

## Requirements

| Package | Used for |
|---|---|
| `nawasara/api` | `api.citizen` and `api.staff` JWT middleware |
| `nawasara/citizen` | Reporter profile and email |
| `nawasara/registry` | OPD master and `ScopedToOpd` |
| `nawasara/notification` | Notifying citizens |

## Configuration

Most numbers are **admin-editable at runtime** through `nawasara_settings`;
config values are only the starting point and the fallback.

| Setting | Default | Where |
|---|---|---|
| Reports per citizen per day | 5 | Panel |
| Photos per report | 3 | Panel |
| Max photo size | 2048 KB | Panel |
| Description length | 500 | Panel |
| Duplicate radius | 50 m | Panel |
| Duplicate window | 7 days | Panel |
| First-response deadline | 72 h | Panel |
| Verification deadline | 48 h | Panel |
| Auto-close without rating | 7 days | Panel |
| Reopen threshold | 2 stars | Panel |

Read them through `Nawasara\Aspirations\Support\Settings`, never `config()`
directly — the config path is the fallback, not the source of truth.

### Storage

Server credentials live in **Vault**, not in `.env` — group `minio`
(Pengaturan → Vault). An admin fills in endpoint, access key and secret key
there, and the Test button writes a probe object and deletes it again, so a
read-only key fails at setup rather than on the first citizen upload.

The bucket this package writes to is its own:

```php
// config/nawasara-aspirations.php
'storage' => [
    'disk'   => 'minio',
    'bucket' => 'nawasara-aspirations',
],
```

Written plainly rather than through `env()`. The bucket name is not a secret
and does not differ between environments, so an env var would only add a second
place to look when the value in effect turns out not to be the expected one.

One MinIO server, one set of keys, a bucket per package. The bucket actually
used is recorded on each attachment row, so changing this setting later does
not orphan existing photos.

The bucket must be **private**. Citizen photos carry faces, plates and the
inside of people's homes, so access is always through presigned URLs with a
short TTL. Use credentials scoped to this server's buckets, not a MinIO admin
key.

### Geocoding

Optional. Without a key the package runs on `NullGeocoder`: reports still
arrive, still dispatch, still get handled — only the area columns stay empty.

```env
ASPIRATIONS_GEOCODER=google
ASPIRATIONS_GOOGLE_MAPS_KEY=...
```

The key stays server-side. A key compiled into an APK can be extracted and spent
by strangers on the regency's account, which is why lookups run in a job here
rather than on the phone.

## API

### Citizen — behind `api.citizen`

| Method | Path |
|---|---|
| `GET` | `/api/v1/aspirations/categories` |
| `GET` | `/api/v1/aspirations/reports` |
| `POST` | `/api/v1/aspirations/reports` |
| `GET` | `/api/v1/aspirations/reports/similar` |
| `GET` | `/api/v1/aspirations/reports/{code}` |
| `POST` | `/api/v1/aspirations/reports/{code}/photos` |
| `POST` | `/api/v1/aspirations/reports/{code}/rate` |
| `POST` | `/api/v1/aspirations/reports/{code}/support` |
| `DELETE` | `/api/v1/aspirations/reports/{code}/support` |

### Staff — behind `api.staff`

| Method | Path |
|---|---|
| `GET` | `/api/v1/staff/aspirations/reports` |
| `GET` | `/api/v1/staff/aspirations/reports/verification-queue` |
| `GET` | `/api/v1/staff/aspirations/reports/{code}` |
| `POST` | `/api/v1/staff/aspirations/reports/{code}/start` |
| `POST` | `/api/v1/staff/aspirations/reports/{code}/submit` |
| `POST` | `/api/v1/staff/aspirations/reports/{code}/approve` |
| `POST` | `/api/v1/staff/aspirations/reports/{code}/reject` |
| `POST` | `/api/v1/staff/aspirations/reports/{code}/evidence` |

Reports are addressed by their public `code` (`LB-2026-08-0412`), never by id.
Sequential ids in a URL invite guessing at other people's reports.

## Status flow

```
submitted ──► dispatched ──► in_progress ──► awaiting_verification
    │              │                                  │
    │              │                                  ├──► resolved
    └──────────────┴──► rejected                      └──► in_progress
                                                          (Kabid returns it)
```

There is no `scheduled` status. Work waiting on next year's budget is answered
in the reply and the report closes — a holding status creates reports that never
resolve and appear in nobody's count.

## Working with the code

**Never set `status` directly.** Use `ReportWorkflow`; it enforces the
authorisation rules and writes the timeline entry. Assigning the column skips
both silently.

```php
$workflow->startWork($report, $officer, 'Sudah kami survei');
$workflow->submitForVerification($report, $officer, $kabid, 'Sudah diperbaiki');
$workflow->approve($report, $kabid);
$workflow->rejectWork($report, $kabid, 'Foto bukti tidak sesuai lokasi');
```

**Never create reports directly.** `ReportSubmission::submit()` is what applies
the daily limit, dispatch, SLA stamping and content screening.

**Resources are allow-lists.** Add a column to a resource deliberately, not by
switching to `toArray()` — with a deny-list every future column ships by
default, including ones that should not.

## Scheduled jobs

| Job | Frequency |
|---|---|
| `CheckSlaJob` | hourly |
| `CheckVerificationDueJob` | hourly |
| `AutoCloseJob` | daily, 02:00 WIB |
| `GeocodeReportJob` | on demand |

None of them change a report's status. Escalation is attention, not resolution:
quietly marking overdue work as done would erase the problem it exists to
surface.

## Permissions

```
aspirations.report.view              aspirations.report.reject
aspirations.report.respond           aspirations.report.reassign
aspirations.report.dispatch          aspirations.report.reveal-identity
aspirations.report.verify            aspirations.report.export
aspirations.category.view            aspirations.dashboard.view
aspirations.category.manage
```

`verify` is separate from `respond` on purpose — one person must not both do the
work and approve it.

## Notes for future work

- **SLA figures in config are placeholders.** The OPD meeting set departments
  and categories but no deadlines. These are the promise shown to a citizen
  before they submit, so they need agreeing before launch.
- **Push notifications are not built.** Email works; FCM is one line in
  `ReportNotifier::channels()` once a Firebase project exists, which needs the
  final Android package name.
- **`village_id` is never populated.** The region master table does not exist in
  registry yet, and a guessed id is worse than an empty one.
- **Working-day mode only skips weekends.** Public holidays are not handled; the
  list must exist before any category switches to `uses_working_days`.

## Author

Pringgo J. Saputro — Dinas Komunikasi, Informatika dan Statistik Kabupaten
Ponorogo.

## License

MIT
