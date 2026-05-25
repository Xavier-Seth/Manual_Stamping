# QMS Manual Stamping App — Claude Instructions

## Project Overview

Laravel 12 + Vue 3 desktop app (Tauri-wrapped) for QMS document control. Users upload a PDF, select a stamp preset, and receive a ZIP containing three stamped copies: Master, Controlled, and Uncontrolled. Stamp presets are fully configurable with a live drag-to-position visual editor.

## Tech Stack

- **PHP 8.2+** — Laravel 12 (MVC backend)
- **Vue 3** — Composition API, `<script setup>` syntax
- **Inertia.js 3** — SPA routing without a separate API layer (`@inertiajs/vue3`, `@inertiajs/vite`)
- **Tailwind CSS 4** — utility-first styling (`@tailwindcss/vite` plugin)
- **Vite 7** — frontend build tool with `laravel-vite-plugin`
- **TCPDF 6.11 + FPDI 2.6** — PDF stamping engine (`tecnickcom/tcpdf`, `setasign/fpdi`)
- **SQLite** — default dev database; `.env` uses MySQL (`qms_stamper`) in production
- **Tauri** — wraps the web app as a native desktop application
- **PHPUnit 11** — backend testing

## Project Structure

```
app/
  Http/Controllers/
    ManualStampController.php     # Upload + zip workflow (thin: validate → service → download)
    StampPresetController.php     # Preset CRUD + validation/normalisation
  Models/
    StampPreset.php               # Eloquent model; JSON casts for all stamp columns
  Services/ManualStamping/
    ManualStampService.php        # Core PDF stamping engine (TCPDF/FPDI)

database/migrations/
  0001_01_01_000000_create_users_table.php
  0001_01_01_000001_create_cache_table.php
  0001_01_01_000002_create_jobs_table.php
  2026_04_21_051743_create_stamp_presets_table.php   # Initial flat-column schema
  2026_04_23_062004_migrate_stamp_presets_to_json.php  # Migrated to JSON columns
  2026_04_23_063315_refactor_stamp_presets_to_multi_stamp.php  # Per-copy-type columns
  2026_04_23_071717_refactor_stamp_presets_to_multi_stamp.php  # (no-op)

resources/js/
  Pages/ManualStamping/
    Index.vue      # Upload page: drag-drop, preset selector, fetch-based ZIP download
    Presets.vue    # Preset manager: create/edit, tabbed copy-type UI, drag-to-position
  Components/
    StampPreview.vue  # A4 canvas preview — draggable stamp & esign boxes

routes/web.php     # All routes (no api.php)
public/images/     # template_page1.png used as StampPreview background
desktop/ (src-tauri/)  # Tauri config and Rust shell
```

## Routes

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/` | `ManualStampController@index` | — |
| POST | `/upload` | `ManualStampController@upload` | — |
| GET | `/manual-stamping/presets` | `StampPresetController@index` | `manual.stamping.presets.index` |
| POST | `/manual-stamping/presets` | `StampPresetController@store` | `manual.stamping.presets.store` |
| PUT | `/manual-stamping/presets/{stampPreset}` | `StampPresetController@update` | `manual.stamping.presets.update` |

## Key Files and Their Roles

### `ManualStampController.php`
Handles PDF upload workflow. Validates file (max 20 MB, PDF only) and optional `preset_id`. Builds three independent preset payloads from the `StampPreset` model (`master_stamps`, `controlled_stamps`, `uncontrolled_stamps`, each with shared `esign`). Delegates stamping to `ManualStampService`. Creates a ZIP with three stamped PDFs and streams it as a download. Cleans up via `app()->terminating()`.

### `StampPresetController.php`
CRUD for presets. Thin controller — delegates to `validatePreset()` which normalises all three stamp arrays and the `esign` object before writing to the DB. Route model binding on `update()`.

### `ManualStampService.php`
Core PDF engine. Three public methods: `stampMasterCopy()`, `stampControlledCopy()`, `stampUncontrolledCopy()`. Each accepts an `$inputPath`, `$outputPath`, and an optional `$preset` array. When `$preset` is `null` the **legacy default layout** is applied (hardcoded LNU stamp positions). When a preset is active, `applyStampsForPage()` iterates stamp definitions and applies each per its `page_rule`. E-sign overlays drawn by `applyESignIfNeeded()`.

Internal draw primitive is `drawTwoLineStamp()` — renders a rectangle + two centred text lines in TCPDF mm units.

### `StampPreset.php`
Eloquent model. Fillable: `name`, `description`, `master_stamps`, `controlled_stamps`, `uncontrolled_stamps`, `esign`, `is_active`. All stamp/esign columns cast to `array`.

### `Index.vue`
Upload page. No Inertia form helpers — uses raw `fetch()` with `FormData` for the binary ZIP response. CSRF token read from cookie (`XSRF-TOKEN`). Preset dropdown bound to `selectedPresetId`; appends `preset_id` to `FormData` when set. Triggers browser download via `URL.createObjectURL`.

### `Presets.vue`
Preset manager. Separate reactive state trees for create and edit forms (`createMasterStamps`, `editMasterStamps`, etc.). `buildPayload()` normalises all stamps before submission via `router.post/put`. `StampPreview` receives stamps for the currently active tab; drag events write back to the correct reactive array.

### `StampPreview.vue`
Interactive A4 preview canvas (pure CSS/HTML, no `<canvas>`). Scaling: `SCALE = 320 / 297` (px/mm). Emits `stamp-drag { index, x, y }` and `esign-drag { x, y }` on drag — never mutates props. Accepts optional `backgroundImage` prop (URL to rasterised template PNG at `public/images/template_page1.png`).

## Stamp Data Shape

Each copy type column stores an **array** of stamp objects:

```json
{
  "label": "MASTER COPY",
  "sub_label": "LNU",
  "type": "red",
  "x": 140.0,
  "y": 250.0,
  "width": 34.0,
  "height": 16.0,
  "page_rule": "all",
  "page_number": null
}
```

- `type`: `"red"` → RGB(220,38,38) | `"black"` → RGB(0,0,0)
- `page_rule`: `"all"` | `"first"` | `"last"` | `"specific"`
- `page_number`: integer (only used when `page_rule === "specific"`), otherwise `null`

E-sign is a single JSON object (or `null` when disabled):

```json
{
  "enabled": true,
  "x": 10.0,
  "y": 270.0,
  "width": 30.0,
  "height": 10.0,
  "page_rule": "last",
  "page_number": null
}
```

E-sign draws a dark-grey rectangle (RGB 31,41,55) with italic "E-SIGN" label. `page_rule` for esign accepts `"first"` | `"last"` | `"specific"` only.

## Coding Conventions

### PHP / Laravel
- Service layer holds all heavy logic — controllers stay thin (validate → delegate → respond)
- No `api.php` routes; all data flows through Inertia props
- JSON columns cast to `array` in the model; never read raw JSON strings in controllers
- Never modify existing migrations — always create new ones
- Temp files live in `storage/app/manual-stamping/generated/{timestamp}_{uuid}/`; always clean up via `app()->terminating()` or explicit deletion
- TCPDF uses **mm units**, origin top-left

### Vue 3
- All components use `<script setup>` Composition API
- Props received from Inertia via `defineProps()`
- Stamp forms use raw `reactive()`/`ref()` arrays + `router.post/put` (not `useForm()`)
- `StampPreview` emits events, never mutates props
- `SCALE = 320 / 297` px/mm keeps canvas ↔ PDF coordinates in sync

## How to Run

### Prerequisites
PHP 8.2+, Composer, Node.js 18+, npm, SQLite (dev) or MySQL (prod)

### One-shot setup
```bash
composer run setup
# Equivalent to: composer install, key:generate, migrate, npm install, npm run build
```

### Dev (web)
```bash
composer run dev
# Starts: php artisan serve + queue + pail + npm run dev (concurrently)
```

### Build (web only)
```bash
npm run build
```

### Desktop (Tauri)
```bash
npm run tauri dev    # dev mode (requires web server running)
npm run tauri build  # production binary (requires npm run build first)
```

### Tests
```bash
composer run test
# or: php artisan test
```

## Vendor Patches (must reapply after `composer update`)

- `vendor/tecnickcom/tcpdf/config/tcpdf_config.php` line 226: the `define('K_TCPDF_THROW_EXCEPTION_ERROR', false)` call **must** be wrapped in `if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR'))`. Without this guard `AppServiceProvider::register()` cannot override the value to `true` (PHP fatal: constant already defined).

## Important Rules

1. **Never edit existing migrations** — add new ones only.
2. **Stamp JSON shape is a contract** — `ManualStampService.php` reads keys by exact name (`label`, `sub_label`, `type`, `x`, `y`, `width`, `height`, `page_rule`, `page_number`). Adding or renaming keys requires updating the service.
3. **Three copy types always travel together** — features affecting stamp behaviour must handle `master_stamps`, `controlled_stamps`, and `uncontrolled_stamps` consistently.
4. **No API routes** — all data flows through Inertia props. Do not add `api.php` routes.
5. **Temp file cleanup** — always via `app()->terminating()` or explicit deletion after download.
6. **PDF coordinates** — TCPDF uses mm, origin top-left. `StampPreview.vue` scales A4 (210×297mm) to `PREVIEW_H=320px`. Keep `SCALE = PREVIEW_H / 297` in sync.
7. **Tauri build** — `npm run build` must complete before packaging the desktop binary.
8. **`is_active` flag** — `ManualStampController::index()` filters by `is_active = true`. Inactive presets are hidden from the upload dropdown but visible in the presets manager.
9. **Legacy layout** — when `$preset === null` in `ManualStampService`, the hardcoded LNU layout runs. Do not break those code paths when adding preset features.

## Stitch MCP — Stamp Preset Redesign

This project uses the **Google Stitch MCP** for AI-assisted redesign of stamp preset layouts. Stitch is configured in `.mcp.json` at the project root.

**When to use Stitch:** Any work involving stamp layout changes, including:
- Repositioning or resizing stamp boxes (`x`, `y`, `width`, `height` in mm)
- Changing stamp colors (`type: red | black`) or adding new color options
- Modifying font sizes, line gaps, or text offsets in `ManualStampService.php`
- Redesigning the legacy default layout methods (`masterCopyLayout()`, `controlledCopyLayout()`, `uncontrolledCopyLayout()`)
- Adjusting e-sign box positioning or sizing
- Proposing new stamp preset schemas or canvas preview changes in `StampPreview.vue`

**How to invoke:** Use the Stitch MCP tool when the user asks to redesign, adjust, or fine-tune stamp positioning, sizing, colors, or layout in `ManualStampService.php` or `StampPreview.vue`. Stitch can generate and iterate on visual layout configurations that map directly to the stamp JSON schema above.
