<?php

namespace App\Exports;

use App\Models\ArusKasTiktok2Import;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Tiktok2CashFlowExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = ArusKasTiktok2Import::with('platform');

        if (!empty($this->filters['from_date'])) {
            $query->whereDate('tanggal_pembayaran', '>=', $this->filters['from_date']);
        }
        
        if (!empty($this->filters['to_date'])) {
            $query->whereDate('tanggal_pembayaran', '<=', $this->filters['to_date']);
        }
        
        if (!empty($this->filters['order_number'])) {
            $query->where('no_pesanan', 'like', '%' . $this->filters['order_number'] . '%');
        }

        return $query->orderBy('tanggal_pembayaran', 'desc')
            ->orderBy('no_pesanan', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Pembayaran',
            'Deskripsi',
            'No Pesanan',
            'Tanggal Pesanan',
            'Pembayaran',
            'Saldo Akhir',
            'Platform',
        ];
    }

    public function map($cashFlow): array
    {
        return [
            $cashFlow->tanggal_pembayaran ? $cashFlow->tanggal_pembayaran->format('Y-m-d') : '',
            $cashFlow->deskripsi,
            $cashFlow->no_pesanan,
            $cashFlow->tanggal_pesanan ? $cashFlow->tanggal_pesanan->format('Y-m-d') : '',
            $cashFlow->pembayaran,
            $cashFlow->saldo_akhir,
            $cashFlow->platform->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Tanggal Pembayaran
            'B' => 30, // Deskripsi
            'C' => 20, // No Pesanan
            'D' => 18, // Tanggal Pesanan
            'E' => 15, // Pembayaran
            'F' => 15, // Saldo Akhir
            'G' => 12, // Platform
        ];
    }
}
