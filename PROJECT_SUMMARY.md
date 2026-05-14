# QMS Manual Stamping App — Project Summary

## What This Project Does

This is a desktop application that automates the stamping of Quality Management System (QMS) documents. In document-controlled environments (e.g., manufacturing, ISO-certified companies), every printed document must be labeled to show whether it is a "Master," "Controlled," or "Uncontrolled" copy — a manual, error-prone process. This app lets a user upload any PDF, select a stamp layout preset, and instantly download a ZIP file containing three correctly stamped versions of the document, ready for distribution.

## Who It's Built For

Quality officers, document controllers, and administrators in ISO-certified or regulated organizations who manage physical document distribution and need to stamp copies consistently without printing and hand-stamping each page.

## Key Features

- **One-click PDF stamping** — upload a PDF, choose a preset, download a ZIP with all three copy types stamped
- **Configurable stamp presets** — create and save named presets with custom stamp text, size, position, and color per copy type
- **Visual drag-to-position editor** — an interactive A4 canvas lets users drag stamps and e-signatures to exact positions before saving a preset
- **Per-page stamp rules** — stamps can target all pages, first page only, last page only, or a specific page number
- **E-signature overlay** — optional e-sign image can be embedded on any page at a configurable position
- **Native desktop app** — runs as a standalone Windows/macOS application, no browser or internet required

## Tech Stack

| Technology           | Role in This Project                                                               |
| -------------------- | ---------------------------------------------------------------------------------- |
| PHP 8.2 + Laravel 12 | Backend framework — handles routing, file processing, database, and business logic |
| Vue 3                | Frontend UI framework — reactive components, form management, live preview canvas  |
| Inertia.js           | Connects Laravel and Vue without a separate API — pages receive data as props      |
| Tailwind CSS 4       | Utility-based CSS framework for all styling                                        |
| Vite                 | Compiles and bundles frontend assets                                               |
| TCPDF + FPDI         | PHP PDF libraries used to open existing PDFs and draw stamp overlays               |
| SQLite               | Local database storing stamp preset configurations                                 |
| Tauri                | Wraps the entire web app into a native desktop binary (Rust-based)                 |
| PHPUnit              | Automated testing framework for backend logic                                      |

## System Architecture

The app follows a layered architecture:

```
User (Desktop Window via Tauri)
        ↓
  Vue 3 Frontend (Inertia.js)
        ↓
  Laravel Controllers  ←→  SQLite (Stamp Presets)
        ↓
  ManualStampService
        ↓
  TCPDF/FPDI (PDF Engine)
        ↓
  ZIP file → downloaded to user
```

1. **Tauri shell** renders the Laravel web app inside a native desktop window — no server needed for end users.
2. **Inertia.js** eliminates the need for a REST API: the backend sends page data as props, and Vue handles rendering.
3. **ManualStampService** is the core engine — it opens the uploaded PDF, loops through pages applying stamps per the preset rules, and writes three separate stamped files.
4. **Stamp Presets** are stored in SQLite as JSON arrays, one per copy type, giving flexible per-copy stamp configuration without additional tables.

## Notable Technical Decisions

**1. JSON columns instead of a relational stamp table**
Rather than creating a separate `stamps` table with foreign keys (which would require multiple queries and complex joins), stamp configurations are stored as JSON arrays inside the preset record. This keeps preset reads to a single query and makes the data self-contained — a reasonable tradeoff given that stamp lists are always loaded with their parent preset and rarely exceed a handful of items.

**2. Inertia.js over a REST API**
The project uses Inertia.js to bridge Laravel and Vue, meaning there are no JSON API endpoints. The backend returns fully-typed page props directly to Vue components. This significantly reduced boilerplate (no serializers, no Axios route management) and kept the codebase small for a single-user desktop tool where an API contract with external consumers is not needed.

**3. Tauri instead of Electron for desktop packaging**
Tauri uses a Rust-based shell rather than bundling a full Chromium browser (as Electron does). This results in a dramatically smaller binary size and lower memory usage — critical for an internal business tool expected to run on modest office hardware. The trade-off is a more complex build pipeline, but the production binary is leaner.

## Challenges & What I Learned

One of the hardest parts was getting the stamp to appear in the correct position on the PDF. I had no idea beforehand that it was even possible to overlay stamps on existing PDFs programmatically — this was something I discovered during development. The drag-to-position editor was also a new concept for me, and I learned a lot about how interactive canvas-based UIs work. Packaging the app as a native desktop binary using Tauri was another challenge — I had never heard of Tauri before this project and had to learn from scratch how it wraps a web app into a standalone desktop application. This project taught me that sometimes the most interesting solutions come from discovering tools you didn't know existed.

_Generated from source code analysis_
