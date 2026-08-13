# CHANGELOG.md

## fix/bug-livemart-v2 (merged ke main)

### Bug Fixes & Refactor
- **Retur konsisten full/sebagian** — perbaiki konsistensi logika retur full vs sebagian di semua alur (finance analytics, export mapped, controller retur).
- **Retur lintas kategori** — relasi retur pakai `withoutGlobalScope(mainCategory)` supaya tidak null saat session kategori berbeda.
- **Finance offline single source of truth** — tambah `FinanceOfflineCalculator` service; hapus duplikasi perhitungan di `FinanceOfflineController` & export.
- **Race condition** — tambah `lockForUpdate` pada kalkulasi finance retur.
- **Anti hardcode** — `resolvePlatform` shopee2/tiktok2 lookup nama dinamis; ganti hardcode `main_category_id` di middleware, command import, dan seeder dengan `MainCategoryHelper`.
- **Route cleanup** — bersihkan route mati di analytics; amankan API master (auth) dan database restore.

### File Baru
- `app/Services/FinanceOfflineCalculator.php`
- `shared/src/Helpers/MainCategoryHelper.php`

---

## v1.0 (Initial Build)

### Tech Stack Decisions
- **Laravel 10** sebagai framework backend
- **MySQL** sebagai database
- **Filament v3** sebagai admin panel
- **Blade** sebagai templating engine (tanpa SPA)
- **Sanctum** untuk API authentication
- **Maatwebsite/Laravel Excel** untuk export/import Excel
- **DomPDF** untuk export PDF

### Arsitektur Decisions
- **Monorepo structure** — aplikasi di `apps/livemart/`, shared code di `shared/`
- **Query Layer pattern** — SQL mentah dipisah ke class query terpisah untuk performa
- **Service Layer** — orchestrator tipis tanpa kalkulasi berat
- **Multi-Category (Main Category)** — session-based scoping untuk mendukung multiple business lines (KOPI, SKINCARE)
- **Global Scope** — otomatis filtering berdasarkan main category di Model

### Business Decisions
- **Mapping barang versioned** — Setiap perubahan mapping barang tercatat dengan versioning
- **Invoice format** — `{counter}/{yearMonth}/{suffix}/{taxCode}` untuk mendukung PKP/NON-PKP
- **Monthly reset** — Counter invoice di-reset setiap bulan
- **4 platform accounts** — Shopee, Shopee2, TikTok, TikTok2
- **Import-based sales** — Penjualan online diimport dari Excel, bukan real-time API

### Key Milestones
- Setup Laravel 10 + Filament 3
- Database migrations untuk semua entity
- Penerimaan barang system (batch processing)
- Warehouse & stock management
- Penjualan offline system (with surat jalan & invoice)
- Platform sales import (Shopee, TikTok)
- Mapping barang with versioning
- Finance management (offline + online per platform)
- Role-based permissions system
- Analytics & reporting modules
- Return management (pembelian, penjualan, offline)
- Admin management (roles, permissions, users)

### Artisan Commands
- **Import commands** — Import data master, penerimaan, warehouse stock dari CSV/SQL
- **Fix commands** — Perbaikan data (stock discrepancy, invoice numbers, split orders, dll)
- **Sync commands** — Resync invoices, financial transactions
- **Audit commands** — Check invoice gaps, stock discrepancy, finance mismatches
