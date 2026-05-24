# QMS Manual Stamper — Pre-Build Code Review

> Branch: `fix/tauri-config`  
> Reviewer: Claude Sonnet 4.6  
> Scope: Full stack — Rust/Tauri, Laravel backend, Vue 3 frontend, migrations, build packaging

---

## 🔴 Critical — must fix before build

---

### C1. `LARAVEL_STORAGE_PATH` is not a standard Laravel environment variable

`lib.rs:263` sets `.env("LARAVEL_STORAGE_PATH", &runtime_paths.storage_dir)` — an env var pointing to the writable `app_local_data_dir`. But Laravel's Application class does not read `LARAVEL_STORAGE_PATH` natively. The standard Laravel variable is `APP_STORAGE_PATH` (Laravel 11+). Unless `desktop/runtime/laravel-app/bootstrap/app.php` explicitly calls `$app->useStoragePath(env('LARAVEL_STORAGE_PATH'))`, the storage path resolves to `laravel_app_dir/storage` — inside the read-only Tauri resource bundle.

**Consequence:** Every storage write in production fails silently — temp stamp files, session files, log files, and Blade view cache all go to an unwritable path. The stamp job reaches `ManualStampController` but `Storage::disk('local')->putFileAs()` throws or returns false. The user gets a 500 page with no explanation.

**Verification needed:** Read `desktop/runtime/laravel-app/bootstrap/app.php` and confirm `useStoragePath` is called.

---

### C2. `APP_KEY` in the runtime `.env` is unverified

`desktop/runtime/laravel-app/.env` exists (1155 bytes) but its contents were not read. Laravel throws `RuntimeException: No application encryption key has been specified` if `APP_KEY` is empty or `base64:` with no value, halting every request.

**Consequence:** The Rust code successfully spawns PHP, `artisan serve` binds to port 8000, `laravel_is_ready()` returns true, the window opens — and every page shows a 500 error with no route to recovery for the end user.

**Verification needed:** Read `desktop/runtime/laravel-app/.env` and confirm `APP_KEY=base64:...` is set to a real value.

---

### C3. `navigate_main_window_when_ready` spawns an unbounded polling thread with no exit condition

`lib.rs:202–216`:
```rust
thread::spawn(move || loop {
    if laravel_is_ready() { /* navigate + return */ }
    thread::sleep(SERVER_RETRY_INTERVAL);  // 250ms
});
```
If Laravel never starts (port conflict, PHP crash, missing artisan), this thread polls every 250ms forever. There is no timeout, no counter, no channel to cancel it on `RunEvent::Exit`. When the user closes the window, `stop_packaged_laravel_server` (`lib.rs:304`) kills the PHP child, but this thread keeps running, preventing clean shutdown.

**Consequence on a port conflict:** Port 8000 is in use → PHP server fails to bind → polling thread runs indefinitely → app appears hung after user closes the window.

---

### C4. `convert_esign_to_array` migration will crash on any row where `esign` is NULL

`2026_05_12_121840_convert_esign_to_array.php` iterates all presets and reads `$preset->esign['enabled']`. If any row has a NULL `esign` column (e.g., presets created before the esign feature was added), this array access on null throws a PHP TypeError, halting the entire migration batch and leaving the database in a partially-migrated state.

The bundled `database.sqlite` (102,400 bytes) already exists and may contain rows from earlier schema versions. Whether those rows have NULL `esign` values cannot be confirmed without inspecting the SQLite file directly.

---

### C5. PHP binary at `desktop/runtime/php/php.exe` is 183,296 bytes — likely a stub launcher, not a full runtime

A full PHP CLI binary on Windows is typically 8–15 MB. 183 KB is the size of a launcher wrapper or phprc stub, not a functional PHP interpreter. If this is not a complete PHP runtime (including the required DLLs and extensions in the same directory), `artisan serve` will fail immediately at PHP startup with "The program can't start because..." — the same silent failure path as C3.

**Verification needed:** Confirm `desktop/runtime/php/` contains the full PHP runtime (php.exe + php8x.dll + ext/*.dll), not just a launcher.

---

## 🟡 Medium — should fix before release

---

### M1. Silent failure when PHP does not start — user sees blank webview, no error dialog

`lib.rs:243–257`: When `php_exe` is missing or `artisan` is missing, the code calls `eprintln!` and `return Ok(())`. The app continues, creates the window, and navigates to `http://127.0.0.1:8000` — which never responds. The user sees a blank or "connection refused" screen with no diagnostic.

A user-facing error dialog via the Tauri v2 dialog API should be shown in this case.

---

### M2. `ManualStampService` base64 prefix stripping is unverified

`Presets.vue:246` uses `reader.readAsDataURL(file)` which produces `data:image/png;base64,<data>`. This full data URI is sent as `esign[].image`. `ManualStampService.php:276` "accepts base64 data URIs" and "decodes base64" — but if the service passes the full data URI string to `base64_decode()` without stripping the `data:image/...;base64,` prefix, the decoded bytes are garbage and the fallback placeholder renders instead of the user's signature. This is a silent visual defect, not an exception.

**Verification needed:** Read `ManualStampService.php` lines 270–290 to confirm `preg_replace('/^data:image\/[^;]+;base64,/', '', $image)` or equivalent stripping is present.

---

### M3. `StampPresetController@setDefault` return value unverified — Inertia state may not refresh

`Presets.vue:271–277` calls `router.patch(...)` with `preserveScroll: true` but has no `onSuccess` callback that mutates local state. The `is_default` flag on other presets only updates if the server returns an Inertia redirect that triggers a page prop refresh. If `setDefault()` returns `response()->json(...)` instead of `redirect()->back()`, Inertia receives a non-redirect response, does not update props, and the UI shows stale `is_default` values until the user manually refreshes.

**Verification needed:** Read `StampPresetController.php:54–66` to confirm the return value is a redirect (not JSON).

---

### M4. `2026_04_23_063315_refactor_stamp_presets_to_multi_stamp.php` is labeled "empty skeleton" but referenced a non-existent table

This migration was described as an "empty migration skeleton (operates on non-existent 'multi_stamp' table)". An empty `up()` is harmless — Laravel will record it as run and continue. But the description "operates on non-existent table" suggests it may contain a `Schema::table('multi_stamp', ...)` call. If the table does not exist when the migration runs, this will throw a `QueryException` and halt all subsequent migrations.

**Verification needed:** Read the actual contents of `2026_04_23_063315_refactor_stamp_presets_to_multi_stamp.php`.

---

### M5. `convert_esign_to_array` has no `down()` method — rollbacks will silently no-op

The migration intentionally skips `down()`. This is acceptable in production, but in the build workflow where `php artisan migrate --fresh` or `migrate:rollback` may be called during testing, the lack of a rollback silently leaves the schema in the forward state while other migrations roll back. This can corrupt the migration sequence during development.

---

### M6. `stage-laravel-runtime.ps1` contents unknown — build plan assumes manual steps that may already be scripted

`desktop/scripts/stage-laravel-runtime.ps1` exists and is tracked in git. The build plan lists "copy `public/build/` into `desktop/runtime/laravel-app/public/build/`" as a manual step. This script may already automate that copy. Conversely, if the script is incomplete or incorrect, following the manual build sequence would bypass it and diverge from the intended workflow.

**Verification needed:** Read `desktop/scripts/stage-laravel-runtime.ps1` before finalizing the build sequence.

---

### M7. Temp directory cleanup in `ManualStampController` does not run on uncaught PHP exceptions

`ManualStampController.php` registers cleanup via `app()->terminating()`. This hook fires after the HTTP response is sent — it does **not** fire if a fatal PHP error, unhandled exception, or OOM kills the process mid-stamp. A corrupt or extremely large PDF that causes TCPDF to exhaust memory will leave a `manual-stamping/generated/{uuid}/` directory orphaned on disk with three in-progress PDFs. On a desktop app running for weeks, these can accumulate.

---

### M8. `StampPreview.vue` drag event listeners not cleaned up on unmount

`StampPreview.vue:95–96` and `123–124` add `document.addEventListener('mousemove', onMove)` and `document.addEventListener('mouseup', onUp)`. Cleanup (`removeEventListener`) only happens in `onUp` — i.e., when the drag completes. If the user begins dragging a stamp and then navigates away (closing the component), the `mousemove` listener remains attached to `document`. On a long session with many tab switches, this accumulates stale handlers. The `onMove` closure holds a reference to the component's reactive state, preventing garbage collection.

---

### M9. No maximum stamp count enforced — unbounded array accepted by controller

`StampPresetController@validatePreset` accepts `master_stamps`, `controlled_stamps`, `uncontrolled_stamps` as arrays with no `max:N` rule. A preset with 50 stamps per page would produce PDFs with overlapping rectangles and be functionally unusable, but the API accepts it without complaint.

---

### M10. Bundled PHP ini limits unknown — upload limits may differ from host PHP

The host PHP reports `upload_max_filesize=512M` and `post_max_size=512M`. The bundled PHP at `desktop/runtime/php/` has its own `php.ini`. If that ini has `upload_max_filesize=8M` (PHP default), a 10 MB PDF upload fails at the PHP level before reaching Laravel validation, producing a generic 500 or empty response instead of a user-facing validation error.

---

## 🔵 Low — fix when convenient

---

### L1. `capabilities/default.json:2` still has `$schema` pointing to gitignored path

`"$schema": "../gen/schemas/desktop-schema.json"` references `desktop/src-tauri/gen/` which is excluded by `.gitignore`. Fresh clones have no schema file, generating the same VS Code warning that was just fixed in `tauri.conf.json`.

### L2. `desktop/src-tauri/binaries/php-x86_64-pc-windows-msvc.exe` is a 0-byte tracked file

This empty file is tracked in git but serves no purpose — there is no `externalBin` entry in `tauri.conf.json` to reference it. It will be packed into every installer unless gitignored. Remove or gitignore it.

### L3. Window dimensions 800×600 will clip the Presets UI

The stamp preset editor in `Presets.vue` has a three-tab layout with a drag-to-position canvas, esign controls, and a sidebar. 800×600 is insufficient vertical space. Recommend `width: 1280, height: 800, minWidth: 1024, minHeight: 720`.

### L4. `Cargo.toml` metadata is placeholder — affects installer UI on Windows

`description = "A Tauri App"`, `authors = ["you"]` — these values appear in the Windows Add/Remove Programs entry and MSI metadata.

### L5. Dead `greet` Tauri command registered but unreachable from frontend

`lib.rs:41–43, 327` — `greet` is compiled and registered but no Vue file imports or calls it. Remove the function and handler entry.

### L6. `tauri-plugin-shell = "2"` in `Cargo.toml:23` compiled but never initialized

`lib.rs` never calls `tauri_plugin_shell::init()`. The plugin compiles into the binary, increasing size, but contributes nothing. Remove from `Cargo.toml`.

---

## ❓ Questions for the developer

**Q1.** Read `desktop/runtime/laravel-app/bootstrap/app.php`. Does it call `$app->useStoragePath(env('LARAVEL_STORAGE_PATH'))`? If not, how does the production app write temp files, logs, and cache to a writable path? *(Blocks C1)*

**Q2.** What is the `APP_KEY` value in `desktop/runtime/laravel-app/.env`? Is it a real `base64:...` key or a placeholder? This is a hard launch blocker if empty. *(Blocks C2)*

**Q3.** Is `desktop/runtime/php/php.exe` (183 KB) the full PHP CLI binary, or a launcher stub? Are the required DLLs (`php8x.dll`, extension `.dll` files) present in the same directory? *(Blocks C5)*

**Q4.** What does `desktop/scripts/stage-laravel-runtime.ps1` actually do? Does it automate the `npm run build → copy to runtime/` step? Before finalizing the build sequence, this script must be read. *(Affects M6)*

**Q5.** Read `2026_04_23_063315_refactor_stamp_presets_to_multi_stamp.php` in full. Does `up()` contain any `Schema::table(...)` or `DB::statement(...)` calls that reference a non-existent table? *(Affects M4)*

**Q6.** Read `ManualStampService.php` lines 270–290. Does the service strip the `data:image/...;base64,` prefix before calling `base64_decode()`? If not, e-sign images will silently render as placeholders in every produced PDF. *(Affects M2)*

**Q7.** What does `StampPresetController@setDefault` return — a `redirect()` or `response()->json()`? If it does not return a redirect, Inertia will not refresh the preset list and `is_default` flags will be stale in the UI. *(Affects M3)*

**Q8.** Has the bundled `database.sqlite` ever contained rows with a NULL `esign` column? If yes, `convert_esign_to_array` will crash on those rows and leave the database in a broken migration state. *(Blocks C4)*

---

## ✅ Final verdict

**DO NOT BUILD YET**

Five items block a safe production build. C1 (storage path redirection) and C2 (APP_KEY) are unverified but would each independently cause every user request to fail on a clean install. C5 (PHP binary size anomaly) means the runtime may not be a functional PHP interpreter. C3 (unbounded polling thread) will cause the app to hang on exit whenever port 8000 is unavailable. C4 (NULL esign migration crash) could silently corrupt the bundled SQLite. Before any build is run, the developer must read the five files called out in Q1–Q5 and confirm each blocker is actually resolved in the runtime files — several of these may already be handled by code not yet read, but none can be assumed safe without verification.
