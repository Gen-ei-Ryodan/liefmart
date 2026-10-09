<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SuratJalanSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_month',
        'counter',
        'last_updated',
        'main_category_id',  // Kategori utama (HPNSDA, HGNSDA)
        'tax_id'            // Tax ID untuk menentukan suffix
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];

    private static function resolveTaxCode($taxId)
    {
        $taxCodeNonPkp = config('invoice.format.non_pkp_tax_code', '02');
        $taxCodePkp = config('invoice.format.pkp_tax_code', '01');

        $tax = \App\Models\TaxCategory::withoutGlobalScopes()->find($taxId);
        if (!$tax) {
            return $taxCodeNonPkp;
        }

        $name = strtolower(trim((string) $tax->name));
        if ($name === '') {
            return $taxCodeNonPkp;
        }

        if (str_contains($name, 'non') || $name === 'lm' || str_contains($name, 'lm')) {
            return $taxCodeNonPkp;
        }

        if (str_contains($name, 'pkp') || $name === 'hgn') {
            return $taxCodePkp;
        }

        return $taxCodeNonPkp;
    }

    private static function getMaxExistingCounter($yearMonth, $taxCode)
    {
        $maxCounter = DB::table('offline_sales')
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(surat_jalan_number, "/", 1) AS UNSIGNED)) AS max_counter')
            ->whereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(surat_jalan_number, "/", 2), "/", -1) = ?', [$yearMonth])
            ->whereRaw('SUBSTRING_INDEX(surat_jalan_number, "/", -1) = ?', [$taxCode])
            ->value('max_counter');

        return (int) ($maxCounter ?? 0);
    }

    /**
     * Mendapatkan nomor Surat Jalan berikutnya berdasarkan tanggal ORDER
     * 
     * @param int $taxId Tax ID untuk menentukan suffix
     * @param int $mainCategoryId Main category ID
     * @param string $orderDate Tanggal ORDER (format: Y-m-d)
     * @return array Array berisi nomor SJ dan counter
     */
    public static function getNextSuratJalanNumber($taxId, $mainCategoryId, $orderDate = null)
    {
        // Jika tidak ada tanggal ORDER, gunakan tanggal saat ini
        if (!$orderDate) {
            $orderDate = Carbon::now()->format('Y-m-d');
        }
        
        if (!$taxId) {
            $defaultTax = \App\Models\TaxCategory::withoutGlobalScopes()->where('is_active', true)->first() 
                ?? \App\Models\TaxCategory::withoutGlobalScopes()->first();
            $taxId = $defaultTax ? $defaultTax->id : 1;
        }

        // Format tahun-bulan berdasarkan tanggal ORDER (YYMM)
        $yearMonth = Carbon::parse($orderDate)->format('ym');
        $taxCode = self::resolveTaxCode($taxId);
        $sequenceMainCategoryId = 0;
        
        // Menggunakan transaksi database untuk memastikan counter tidak duplikat
        $counter = DB::transaction(function() use ($yearMonth, $taxId, $taxCode, $sequenceMainCategoryId) {
            $maxExisting = self::getMaxExistingCounter($yearMonth, $taxCode);

            $sequenceCounter = self::where('year_month', $yearMonth)
                ->where('tax_id', $taxId)
                ->where('main_category_id', $sequenceMainCategoryId)
                ->lockForUpdate()
                ->first();

            if (!$sequenceCounter) {
                self::create([
                    'year_month' => $yearMonth,
                    'tax_id' => $taxId,
                    'main_category_id' => $sequenceMainCategoryId,
                    'counter' => 0,
                    'last_updated' => now(),
                ]);

                $sequenceCounter = self::where('year_month', $yearMonth)
                    ->where('tax_id', $taxId)
                    ->where('main_category_id', $sequenceMainCategoryId)
                    ->lockForUpdate()
                    ->first();
            }

            $currentCounter = (int) ($sequenceCounter->counter ?? 0);
            $nextCounter = max($currentCounter, $maxExisting) + 1;

            $sequenceCounter->counter = $nextCounter;
            $sequenceCounter->last_updated = now();
            $sequenceCounter->save();

            return $nextCounter;
        });
        
        // Format nomor SJ berdasarkan kombinasi tax_id dan main_category_id
        $suratJalanNumber = self::formatSuratJalanNumber($taxId, $mainCategoryId, $yearMonth, $counter);
        
        return [
            'surat_jalan_number' => $suratJalanNumber,
            'counter' => $counter
        ];
    }
    
    /**
     * Format nomor Surat Jalan berdasarkan tax_id dan main_category_id
     * 
     * @param int $taxId Tax ID
     * @param int $mainCategoryId Main category ID
     * @param string $yearMonth Tahun dan bulan format YYMM
     * @param int $counter Nomor urut
     * @return string Nomor SJ terformat
     */
    private static function formatSuratJalanNumber($taxId, $mainCategoryId, $yearMonth, $counter)
    {
        // Format counter (4 digit)
        $formattedCounter = sprintf('%04d', $counter);

        $suffix = config('invoice.format.suffix_offline', 'AMP-KOS');
        $taxCode = self::resolveTaxCode($taxId);

        return "{$formattedCounter}/{$yearMonth}/{$suffix}/{$taxCode}";
    }

    /**
     * Mendapatkan batch Surat Jalan numbers untuk banyak transaksi
     * 
     * @param int $taxId Tax ID
     * @param int $mainCategoryId Main category ID
     * @param int $count Jumlah nomor SJ yang diperlukan
     * @param string $orderDate Tanggal ORDER (format: Y-m-d)
     * @return array Array berisi nomor-nomor SJ
     */
    public static function getBatchSuratJalanNumbers($taxId, $mainCategoryId, $count, $orderDate = null)
    {
        if ($count <= 0) {
            return [];
        }

        // Jika tidak ada tanggal ORDER, gunakan tanggal saat ini
        if (!$orderDate) {
            $orderDate = Carbon::now()->format('Y-m-d');
        }
        
        // Format tahun-bulan berdasarkan tanggal ORDER (YYMM)
        $yearMonth = Carbon::parse($orderDate)->format('ym');
        $taxCode = self::resolveTaxCode($taxId);
        $sequenceMainCategoryId = 0;
        
        // Menggunakan transaksi database untuk memastikan counter tidak duplikat
        return DB::transaction(function() use ($yearMonth, $taxId, $mainCategoryId, $taxCode, $sequenceMainCategoryId, $count) {
            $maxExisting = self::getMaxExistingCounter($yearMonth, $taxCode);

            $sequenceCounter = self::where('year_month', $yearMonth)
                ->where('tax_id', $taxId)
                ->where('main_category_id', $sequenceMainCategoryId)
                ->lockForUpdate()
                ->first();

            if (!$sequenceCounter) {
                self::create([
                    'year_month' => $yearMonth,
                    'tax_id' => $taxId,
                    'main_category_id' => $sequenceMainCategoryId,
                    'counter' => 0,
                    'last_updated' => now(),
                ]);

                $sequenceCounter = self::where('year_month', $yearMonth)
                    ->where('tax_id', $taxId)
                    ->where('main_category_id', $sequenceMainCategoryId)
                    ->lockForUpdate()
                    ->first();
            }

            $currentCounter = (int) ($sequenceCounter->counter ?? 0);
            $startCounter = max($currentCounter, $maxExisting) + 1;
            $endCounter = $startCounter + $count - 1;

            $sequenceCounter->counter = $endCounter;
            $sequenceCounter->last_updated = now();
            $sequenceCounter->save();

            $suratJalanNumbers = [];
            for ($i = $startCounter; $i <= $endCounter; $i++) {
                $suratJalanNumbers[] = [
                    'surat_jalan_number' => self::formatSuratJalanNumber($taxId, $mainCategoryId, $yearMonth, $i),
                    'counter' => $i
                ];
            }

            return $suratJalanNumbers;
        });
    }
}
