# Audit Metadata Sumber Mutasi Stok

Tanggal audit: 2026-08-06

## Tujuan

Dokumen ini mencatat record `warehouse_stock` yang perlu dipastikan karena metadata sumber mutasinya tidak lengkap atau relasinya tidak konsisten.

Audit ini tidak menganggap perbedaan `source_date` dan `tanggal_penerimaan` sebagai kesalahan otomatis. Perbedaannya dapat terjadi karena:

- `penerimaan.tanggal_penerimaan` adalah tanggal penerimaan/PO.
- `warehouse_stock.created_at` adalah waktu record dipindahkan atau dibuat di gudang.
- `source_date` pada implementasi saat ini biasanya diisi dengan tanggal penerimaan, bukan tanggal pemindahan ke gudang.

## Status Perbaikan

### Sudah diperbaiki

12 item dari penerimaan berikut sudah diperbaiki langsung di database:

- Kode penerimaan: `GR-HGN-20260418093542`
- Nomor PO: `BA/0011/2602/HGN-SDA`
- Penerimaan ID: `62`
- Tanggal penerimaan: `2026-02-04`
- Perubahan: `source_type = penerimaan`, `source_id = 62`, `source_date = 2026-02-04`

SKU yang sudah diperbaiki:

`BB0058`, `BB0060`, `BB0063`, `BB0062`, `BB0067`, `BB0069`, `BB0073`, `BB0075`, `BB0078`, `BB0080`, `BB0081`, `BB0082`.

## Prioritas 1: Tidak Ada Relasi Penerimaan

Record berikut memiliki `source_type = penerimaan`, tetapi tidak memiliki `penerimaan_detail_id`, `source_id`, maupun `source_date`. Karena itu, PO, kode penerimaan, dan tanggal penerimaan asal tidak dapat ditentukan dari relasi database saat ini.


| No | warehouse_stock_id | SKU | Nama produk | Qty | Dibuat pada | Barang keluar | Yang perlu dipastikan |
|---:|---:|---|---|---:|---|---:|---|
| 1 | 161 | KE1318 | 02 - EXTRA BUBBLE WRAP | 0 | 2026-01-27 08:47:53 | 0 | Cari SKU `KE1318`; pastikan PO/kode penerimaan dan tanggal asal. |
| 2 | 577 | NN0900 | 02 - NIVEA EXTRA BRIGHT MIRACLE SWEET BODY SERUM 180ML | 0 | 2026-01-27 08:47:54 | 0 | Cari SKU `NN0900`; pastikan PO/kode penerimaan dan tanggal asal. |
| 3 | 580 | NN0862 | 02 - NIVEA EXTRA WHITENING DEODORANT ROLL ON 50ML | 0 | 2026-01-27 08:47:54 | 0 | Cari SKU `NN0862`; pastikan PO/kode penerimaan dan tanggal asal. |
| 4 | 677 | SS1013 | 02 - SHINZUI BODY CLEANSER 225ML - MYORI | 0 | 2026-01-27 08:47:54 | 0 | Cari SKU `SS1013`; pastikan PO/kode penerimaan dan tanggal asal. |
| 5 | 927 | MP0918 | 02 - PUTERI BODY SPLASH W LILY 135ML RJ | 0 | 2026-01-27 08:47:54 | 0 | Cari SKU `MP0918`; pastikan PO/kode penerimaan dan tanggal asal. |

### Catatan

Kelima record di atas tidak memiliki barang keluar yang terhubung dan qty saat ini sudah `0`. Jangan mengisi `source_id` atau tanggal berdasarkan perkiraan. Data asal perlu dicari dari dokumen penerimaan lama, backup, atau histori input.

## Prioritas 2: Source ID Mengarah ke Penerimaan yang Sudah Tidak Ada

Record berikut masih memiliki detail penerimaan, tetapi `source_id` menunjuk ke penerimaan yang tidak ditemukan di tabel `penerimaan`.


| No | warehouse_stock_id | SKU | Detail ID | Source ID | Source date | Qty | Barang keluar | Yang perlu dipastikan |
|---:|---:|---:|---:|---:|---|---:|---:|---|
| 1 | 928 | BB0046 | 929 | 8 | 2026-01-27 | 0 | 1 baris / qty 1 | Cari penerimaan asal untuk detail ID `929` atau SKU `BB0046`; pastikan apakah penerimaan ID `8` pernah dihapus. |
| 2 | 929 | ZZ1316 | 930 | 9 | 2026-01-27 | 0 | 1 baris / qty 1 | Cari penerimaan asal untuk detail ID `930` atau SKU `ZZ1316`; pastikan apakah penerimaan ID `9` pernah dihapus. |

### Cara mencari

Gunakan urutan pencarian berikut:

1. Cari SKU di halaman Penerimaan.
2. Cari `penerimaan_detail_id` jika tersedia.
3. Cocokkan dengan dokumen PO sekitar tanggal `2026-01-27`.
4. Pastikan nomor PO, kode penerimaan, tanggal, dan qty sebelum metadata diperbaiki.

Karena kedua record memiliki histori barang keluar, jangan menghapus atau mengganti relasi tanpa memastikan penerimaan asalnya.

## Prioritas 3: Perbedaan Tanggal yang Belum Tentu Salah


| No | warehouse_stock_id | SKU | Detail ID | Source ID | Source date | Tanggal penerimaan | Kode penerimaan | Nomor PO | Qty detail | Qty stok |
|---:|---:|---:|---:|---:|---|---|---|---|---:|---:|
| 1 | 1295 | SS1388 | 1366 | 43 | 2026-04-10 | 2026-03-10 | PNR-000042 | WINGS-PO-KOS-AMP-2603-0002 (03.26) | 12 | 0 |
| 2 | 1296 | S00003 | 1367 | 43 | 2026-04-10 | 2026-03-10 | PNR-000042 | WINGS-PO-KOS-AMP-2603-0002 (03.26) | 24 | 0 |

### Yang perlu dipastikan

- Apakah `2026-04-10` adalah tanggal barang benar-benar masuk Gudang A?
- Apakah `2026-03-10` adalah tanggal penerimaan pada dokumen PO?
- Jika benar, tidak perlu mengubah data hanya karena tanggal berbeda.
- Jika `source_date` harus selalu sama dengan tanggal penerimaan, kedua record perlu dikoreksi ke `2026-03-10`.

## Record yang Tidak Otomatis Dianggap Bermasalah

Record dengan jenis berikut tidak dimasukkan sebagai error metadata penerimaan normal:

- `retur_penjualan`
- `retur_offline`
- `penyesuaian`

Jenis tersebut memang dapat memiliki sumber selain penerimaan normal. `penerimaan_detail_id` pada retur dapat digunakan untuk melacak batch asal, sedangkan `source_id` menunjuk ke transaksi retur.

## Ringkasan Tindakan


Backup sebelum perbaikan 12 item:

```text
/Users/10969sosho/.local/share/opencode/backup_before_bioaqua_12_source_fix_20260806.sql
```
