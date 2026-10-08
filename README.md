# Nawasara Aspirations

Citizen complaint and aspiration channel for the Nawasara framework. It is the backend behind **Lapor Bunda** in the GO super-app for Kabupaten Ponorogo.

A citizen files a report from their phone. It is dispatched to the responsible department automatically, worked on under a binding SLA, verified by a superior inside that department, and handed back to the citizen to rate.

---

## What it does

- **Automatic dispatch.** A report reaches the responsible OPD the moment it arrives. There is no central inbox in front of it.
- **Three SLA clocks.** First response, resolution, and verification, each stamped on arrival and escalated hourly when missed.
- **In-department verification.** The officer nominates their own Kabid, who approves or returns the work. Nobody can approve their own.
- **Per-OPD isolation.** Enforced by a global scope, not by where-clauses.
- **Anonymous reporting.** Hides the name from staff, never the reporter from the system.
- **Duplicate detection.** Offers "Saya Juga Mengalami" instead of blocking.
- **Content screening.** Flags profanity and slurs for review, never rejects.
- **Photo evidence.** Stored in MinIO behind short-lived presigned URLs.

## Installation

```bash
composer require nawasara/aspirations

php artisan migrate
php artisan db:seed --class="Nawasara\Aspirations\Database\Seeders\PermissionSeeder"
php artisan db:seed --class="Nawasara\Aspirations\Database\Seeders\CategorySeeder"
```

The category seeder reports any category whose OPD is missing from the registry. Those categories cannot be dispatched automatically, so reports on them stop at the front door until the department is registered.

```bash
# Registers the five OPD this package's categories point at
php artisan db:seed --class="Nawasara\Registry\Database\Seeders\OpdSeeder"
```

### Sample data (development only)

An empty panel cannot be judged. Every list looks fine when there is nothing in it, and the states that actually break are the ones no empty database produces.

```bash
php artisan db:seed --class="Nawasara\Aspirations\Database\Seeders\SampleReportSeeder"
```

16 reports across every status, with photos, timelines, internal notes, ratings and support counts. It refuses to run in production, and every row it creates is coded `LBX-…` so `purge()` can remove exactly those and nothing else. Without a marker like that, sample and real data cannot be told apart once they mix.

The distribution is deliberately uneven: real queues pile up in `in_progress` and only a couple of reports are ever overdue. One report per status looks tidy and hides precisely what needs testing.

Re-running it replaces the sample rows rather than duplicating them.

## Requirements

| Package | Used for |
|---|---|
| `nawasara/api` | `api.citizen` and `api.staff` JWT middleware |
| `nawasara/citizen` | Reporter profile and email |
| `nawasara/registry` | OPD master and `ScopedToOpd` |
| `nawasara/notification` | Notifying citizens |

## Configuration

Most numbers are **admin-editable at runtime** through `nawasara_settings`. The config values are only the starting point and the fallback.

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

Read them through `Nawasara\Aspirations\Support\Settings`, never `config()` directly. The config path is the fallback, not the source of truth.

### Storage

Server credentials live in **Vault**, not in `.env`, under the group `minio` (Pengaturan, then Vault). An admin fills in the endpoint, access key and secret key there. The Test button writes a probe object and deletes it again, so a read-only key fails at setup rather than on the first citizen upload.

The bucket this package writes to is its own:

```php
// config/nawasara-aspirations.php
'storage' => [
    'disk'   => 'minio',
    'bucket' => 'nawasara-aspirations',
],
```

Written plainly rather than through `env()`. The bucket name is not a secret and does not differ between environments, so an env var would only add a second place to look when the value in effect turns out not to be the expected one.

One MinIO server, one set of keys, a bucket per package. The bucket actually used is recorded on each attachment row, so changing this setting later does not orphan existing photos.

The bucket must be **private**. Citizen photos carry faces, plates and the inside of people's homes, so access is always through presigned URLs with a short TTL. Use credentials scoped to this server's buckets, not a MinIO admin key.

### Geocoding

Optional. Without a key the package runs on `NullGeocoder`: reports still arrive, still dispatch, and still get handled. Only the area columns stay empty.

```env
ASPIRATIONS_GEOCODER=google
ASPIRATIONS_GOOGLE_MAPS_KEY=...
```

The key stays server-side. A key compiled into an APK can be extracted and spent by strangers on the regency's account, which is why lookups run in a job here rather than on the phone.

## API

### Public (no account at all)

| Method | Path |
|---|---|
| `GET` | `/api/v1/aspirations/reports/map` |

The only aspirations endpoint that is open without a token. It has two shapes: a per-district tally, or the reports inside one district.

Pass `lat` and `lng` (and optionally `radius`, default 5000 m) and each report also carries `distance_meters`, for the app's "reports near you" panel.

Note: **report coordinates are never returned, and the distance is rounded on purpose.** Exact distances taken from three positions are enough to trilaterate the report's location, which is the very thing withholding the coordinates protects. Rounding leaves a circle instead of a point: 50 m steps below 100 m, 10 m above. Close range is rounded more coarsely, not less, because that is where a small circle nearly names a single house.

Reports without coordinates stay in the list with `distance_meters: null` rather than being dropped. Most older reports have no point, and hiding them would make the panel look empty when it is not.

Privacy filtering for this endpoint lives in one place, `Services\PublicMap`. Sensitive categories, flagged reports, reporter names, descriptions, and photos never appear here.

### Citizen (behind `api.citizen`)

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

### Staff (behind `api.staff`)

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

Reports are addressed by their public `code` (`LB-2026-08-0412`), never by id. Sequential ids in a URL invite guessing at other people's reports.

## Status flow

```
submitted ──► dispatched ──► in_progress ──► awaiting_verification
    │              │                                  │
    │              │                                  ├──► resolved
    └──────────────┴──► rejected                      └──► in_progress
                                                          (Kabid returns it)
```

There is no `scheduled` status. Work waiting on next year's budget is answered in the reply and the report closes. A holding status creates reports that never resolve and appear in nobody's count.

## Working with the code

**Never set `status` directly.** Use `ReportWorkflow`; it enforces the authorisation rules and writes the timeline entry. Assigning the column skips both silently.

```php
$workflow->startWork($report, $officer, 'Sudah kami survei');
$workflow->submitForVerification($report, $officer, $kabid, 'Sudah diperbaiki');
$workflow->approve($report, $kabid);
$workflow->rejectWork($report, $kabid, 'Foto bukti tidak sesuai lokasi');
```

**Never create reports directly.** `ReportSubmission::submit()` is what applies the daily limit, dispatch, SLA stamping and content screening.

**Resources are allow-lists.** Add a column to a resource deliberately, not by switching to `toArray()`. With a deny-list every future column ships by default, including ones that should not.

## Staff panel

The Livewire pages under `nawasara-aspirations/`. They follow the standard Nawasara list-page shape (`AGENTS.md` §1a): one `filter-panel` holding every filter, `search-input` beside it, active-filter chips below, and row actions in a three-dot dropdown.

| Page | What it is for |
|---|---|
| `/dashboard` | Summary figures, trend, top categories, map, attention list |
| `/reports` | The queue: filter by status, category, kecamatan, overdue |
| `/reports/{code}` | One report: photos, handling actions, timeline, feedback |
| `/categories` | Report categories and their target OPD |
| `/settings` | SLA windows and policy |

**The panel is read-and-handle, not a second workflow.** Every status change goes through `ReportWorkflow`, the same object the API uses. That is deliberate: when the panel and the app each own a copy of the rules, the two drift and a report becomes legal to approve in one and not the other.

### What the detail page shows

Three things were held in the database for months without any page rendering them, and staff judged reports without them:

- **Photos.** A pothole the width of a hand and one the width of a car read identically as text. Report photos and evidence photos are shown apart, because mixed together they look alike while meaning opposite things.
- **Timeline.** Sourced from `responses`, which already records every status change. There is no separate history table because here the history and the reply are the same object. Internal notes are shown to staff (they explain why a report stopped moving) and clearly marked, since they never reach the citizen.
- **Citizen feedback.** Rating and "Saya Juga Mengalami". A report five people file is no longer one person's complaint, and that is what separates a pothole in one alley from one on a road a whole kelurahan uses.

A photo whose file has gone missing renders as a broken card rather than being hidden. A report that lost its evidence is something staff need to see, not something to make look like a report that never had any.

### Export

`aspirations.report.export` gates a CSV download of **the current filtered result**, not the whole table. The recap leadership asks for is nearly always "overdue reports in kecamatan X", and downloading everything to filter again in Excel is the work this page already did.

It streams and chunks, because one export can span tens of thousands of rows, and it writes a UTF-8 BOM. Without it, Excel on Windows guesses the encoding and kecamatan names come out corrupted. The file still downloads and still opens, so nobody notices until staff report the data looks "aneh".

## Scheduled jobs

| Job | Frequency |
|---|---|
| `CheckSlaJob` | hourly |
| `CheckVerificationDueJob` | hourly |
| `AutoCloseJob` | daily, 02:00 WIB |
| `GeocodeReportJob` | on demand |

None of them change a report's status. Escalation is attention, not resolution: quietly marking overdue work as done would erase the problem it exists to surface.

## Permissions

```
aspirations.report.view              aspirations.report.reject
aspirations.report.respond           aspirations.report.reassign
aspirations.report.dispatch          aspirations.report.reveal-identity
aspirations.report.verify            aspirations.report.export
aspirations.category.view            aspirations.dashboard.view
aspirations.category.manage
```

`verify` is separate from `respond` on purpose: one person must not both do the work and approve it.

## Desa and kecamatan

A report's `district_code`, `village` and `village_id` come from its coordinates, checked against the real village boundaries (`Services\BoundaryLocator`), when the report is received.

- **Boundaries, not centroids.** This used to pick the nearest kecamatan centre. Centres are never in the middle of the area they stand for, so a strip several kilometres wide along every border landed in the neighbour: when this was replaced, 7 of 30 production reports sat in the wrong kecamatan (six from Desa Janti, Slahung, recorded as Balong). Do not bring a centroid shortcut back because it looks simpler.
- **Source:** BIG administrative boundaries, update of 13 June 2023, with Kemendagri codes. All 307 village codes and names match the region master exactly. The data is committed as `database/boundaries/ponorogo-villages.json`; the app never calls the source. `database/boundaries/build.php` documents where it came from and rebuilds it, which is only needed when village boundaries change.
- **Simplified to ~3 m** (187k points down to 62k, 6 MB to 1.4 MB). On 20,000 random points, 4 landed in a different village than with the full-detail data, all within a few metres of a border, well under phone GPS error.
- **200 m snap.** A point outside every village but within 200 m of one is placed in it: GPS drift and slivers between simplified borders. Further than that is outside Ponorogo and stays empty, which is more honest than forcing it into the outermost village. Madiun, Trenggalek and Pacitan points are rejected.
- **Existing reports do not fix themselves.** The columns are written once. After any change to the boundaries, run `php artisan aspirations:backfill-districts --force`; it prints every report that moved.

Report routing to an OPD does **not** use the kecamatan; it comes from the category. A wrong kecamatan skews the map, the per-kecamatan statistics and the panel filter, and would misroute reports the day routing to kecamatan offices is added.

## Notes for future work

- **SLA figures in config are placeholders.** The OPD meeting set departments and categories but no deadlines. These are the promise shown to a citizen before they submit, so they need agreeing before launch.
- **Push notifications are not built.** Email works; FCM is one line in `ReportNotifier::channels()` once a Firebase project exists, which needs the final Android package name.
- **Working-day mode only skips weekends.** Public holidays are not handled; the list must exist before any category switches to `uses_working_days`.

## Author

Pringgo J. Saputro, Dinas Komunikasi, Informatika dan Statistik Kabupaten Ponorogo.

## License

MIT
