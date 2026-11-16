<?php

namespace App\Console\Commands;

use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\BarangKeluar;
use App\Models\FinanceOffline;
use App\Models\WarehouseStock;
use App\Models\ReturOfflineSale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Command untuk menghapus penjualan offline yang double (duplikat).
 * 
 * Flow hapus (urutan WAJIB):
 *   1. Ambil data offline_sale berdasarkan surat_jalan_number
 *   2. Hapus retur_offline_sales terkait (jika ada)
 *   3. Restore stok dari barang_keluar (kembalikan qty ke warehouse_stock)
 *   4. Hapus barang_keluar
 *   5. Hapus finance_offline jika tidak dipakai barang_keluar lain
 *   6. Hapus offline_sale_items
 *   7. Hapus offline_sale
 */
class DeleteDoubleSJOffline extends Command
{
    protected $signature = 'offline:delete-double-sj
                            {sj_numbers* : Nomor surat jalan yang akan dihapus (pisah spasi)}
                            {--dry-run : Preview saja tanpa hapus data}
                            {--force : Langsung hapus tanpa konfirmasi}';

    protected $description = 'Hapus penjualan offline double berdasarkan nomor surat jalan (restore stok otomatis)';

    public function handle(): int
    {
        $sjNumbers = $this->argument('sj_numbers');
        $isDryRun  = $this->option('dry-run');
        $isForce   = $this->option('force');

        $this->info('=== DELETE DOUBLE SJ OFFLINE ===');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (tidak ada yang dihapus)' : 'LIVE'));
        $this->newLine();

        // ── 1. Cari semua offline_sale target ─────────────────────────────
        $sales = OfflineSale::withoutGlobalScopes()
            ->whereIn('surat_jalan_number', $sjNumbers)
            ->with(['items.barangKeluar', 'returOfflineSales'])
            ->get();

        if ($sales->isEmpty()) {
            $this->error('Tidak ada offline_sale ditemukan untuk SJ: ' . implode(', ', $sjNumbers));
            return self::FAILURE;
        }

        // ── 2. Tampilkan preview ───────────────────────────────────────────
        $this->table(
            ['ID', 'Surat Jalan', 'Tanggal', 'Customer', 'Total', 'Status', 'Items', 'BarangKeluar'],
            $sales->map(fn($s) => [
                $s->id,
                $s->surat_jalan_number,
                $s->sale_date,
                $s->customer_name,
                number_format($s->total_amount, 0, ',', '.'),
                $s->status,
                $s->items->count(),
                $s->items->sum(fn($i) => $i->barangKeluar->count()),
            ])
        );

        // Tampilkan detail barang keluar & stok yang akan direstore
        $this->newLine();
        $this->info('Detail Barang Keluar yang akan di-restore stoknya:');

        $allBarangKeluar = collect();
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                foreach ($item->barangKeluar as $bk) {
                    $allBarangKeluar->push($bk);
                }
            }
        }

        $this->table(
            ['BK ID', 'Kode BK', 'WH Stock ID', 'Qty Keluar', 'Finance Offline ID'],
            $allBarangKeluar->map(fn($bk) => [
                $bk->id,
                $bk->kode_barang_keluar,
                $bk->warehouse_stock_id,
                $bk->qty,
                $bk->finance_offline_id ?? '-',
            ])
        );

        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN selesai. Jalankan tanpa --dry-run untuk menghapus.');
            return self::SUCCESS;
        }

        // ── 3. Konfirmasi ─────────────────────────────────────────────────
        if (!$isForce) {
            if (!$this->confirm('⚠️  Yakin hapus semua data di atas? Stok akan direstore.', false)) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
        }

        // ── 4. Eksekusi dalam transaksi ───────────────────────────────────
        DB::beginTransaction();
        try {
            foreach ($sales as $sale) {
                $this->info("Memproses SJ: {$sale->surat_jalan_number} (ID: {$sale->id})");

                // 4a. Hapus retur_offline_sales terkait (dan detail-nya)
                $returSales = ReturOfflineSale::withoutGlobalScopes()
                    ->where('offline_sale_id', $sale->id)
                    ->get();

                foreach ($returSales as $retur) {
                    $retur->details()->delete();
                    $retur->delete();
                    $this->line("  ✓ Retur offline dihapus: ID {$retur->id}");
                }

                // 4b. Restore stok & hapus barang_keluar
                foreach ($sale->items as $item) {
                    foreach ($item->barangKeluar as $bk) {
                        // Restore stok ke warehouse
                        $stock = WarehouseStock::find($bk->warehouse_stock_id);
                        if ($stock) {
                            $oldQty = $stock->qty;
                            $stock->qty += $bk->qty;
                            $stock->save();
                            $this->line("  ✓ Stok WH {$stock->id} direstore: {$oldQty} → {$stock->qty} (+{$bk->qty})");
                        }

                        // Hapus finance_offline jika tidak ada barang_keluar lain
                        if ($bk->finance_offline_id) {
                            $otherBkCount = BarangKeluar::where('finance_offline_id', $bk->finance_offline_id)
                                ->where('id', '!=', $bk->id)
                                ->count();

                            if ($otherBkCount === 0) {
                                FinanceOffline::withoutGlobalScopes()->find($bk->finance_offline_id)?->delete();
                                $this->line("  ✓ FinanceOffline ID {$bk->finance_offline_id} dihapus");
                            } else {
                                // Lepas foreign key saja
                                $bk->finance_offline_id = null;
                                $bk->save();
                                $this->line("  ⚠ FinanceOffline ID {$bk->finance_offline_id} masih dipakai BK lain, hanya di-detach");
                            }
                        }

                        $bk->delete();
                        $this->line("  ✓ BarangKeluar ID {$bk->id} dihapus");
                    }

                    $item->delete();
                    $this->line("  ✓ OfflineSaleItem ID {$item->id} dihapus");
                }

                // 4c. Hapus offline_sale
                $sale->delete();
                $this->info("  ✅ OfflineSale ID {$sale->id} ({$sale->surat_jalan_number}) DIHAPUS");
            }

            DB::commit();

            Log::info('DeleteDoubleSJOffline: Berhasil hapus SJ', [
                'sj_numbers' => $sjNumbers,
                'deleted_count' => $sales->count(),
                'by' => 'artisan command',
            ]);

            $this->newLine();
            $this->info('✅ Selesai. ' . $sales->count() . ' SJ berhasil dihapus.');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Gagal: ' . $e->getMessage());
            Log::error('DeleteDoubleSJOffline error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }
    }
}
