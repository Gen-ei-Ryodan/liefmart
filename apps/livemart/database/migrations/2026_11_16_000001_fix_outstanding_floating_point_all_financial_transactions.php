<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix BUG: Outstanding floating point precision
 * 
 * Beberapa record punya outstanding = 0.0000000x (floating point error)
 * yang seharusnya = 0.00 (lunas).
 * Migration ini membulatkan semua kolom outstanding & nominal_fix ke 2 desimal
 * di seluruh tabel financial transactions.
 */
return new class extends Migration
{
    protected array $tables = [
        'financial_transactions',
        'shopee_financial_transactions',
        'tiktok_financial_transactions',
        'shopee2_financial_transactions',
        'tiktok2_financial_transactions',
        'lazada_financial_transactions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            // Round outstanding ke 2 desimal (fix floating point)
            DB::statement("
                UPDATE `{$table}`
                SET `outstanding` = ROUND(`outstanding`, 2)
                WHERE `outstanding` != ROUND(`outstanding`, 2)
            ");

            // Round nominal_fix ke 2 desimal juga (preventif)
            if ($this->columnExists($table, 'nominal_fix')) {
                DB::statement("
                    UPDATE `{$table}`
                    SET `nominal_fix` = ROUND(`nominal_fix`, 2)
                    WHERE `nominal_fix` != ROUND(`nominal_fix`, 2)
                ");
            }

            // Round saldo_masuk ke 2 desimal juga (preventif)
            if ($this->columnExists($table, 'saldo_masuk')) {
                DB::statement("
                    UPDATE `{$table}`
                    SET `saldo_masuk` = ROUND(`saldo_masuk`, 2)
                    WHERE `saldo_masuk` != ROUND(`saldo_masuk`, 2)
                ");
            }
        }
    }

    public function down(): void
    {
        // Tidak bisa di-rollback (data lama sudah tidak bisa dikembalikan ke floating)
        // Ini safe karena hanya membulatkan, bukan mengubah nilai signifikan
    }

    protected function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    protected function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
};
