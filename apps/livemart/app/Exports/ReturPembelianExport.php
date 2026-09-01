<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class ReturPembelianExport implements FromArray, WithHeadings, WithColumnFormatting, WithCustomValueBinder
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Kode Retur',
            'Nomor PO',
            'Tanggal Penerimaan',
            'Tanggal Retur',
            'Tipe Retur',
            'Nama Produk',
            'Harga',
            'Qty Retur',
            'Satuan',
            'Total Nominal',
            'Alasan',
            'User',
            'Dibuat Pada'
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    public function bindValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell, $value)
    {
        if (is_numeric($value)) {
            $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            return true;
        }
        $cell->setValue($value);
        return true;
    }
}
