<?php

namespace App\Exports;

use App\Models\Penerimaan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PenerimaanExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;
    protected $counter = 1;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return Penerimaan::with(['mainCategory', 'taxCategory', 'details.product', 'details.satuan'])
            ->when($this->filters['kode'] ?? null, function ($q, $val) {
                return $q->where('kode_penerimaan', 'like', '%' . $val . '%');
            })
            ->when($this->filters['kategori'] ?? null, function ($q, $val) {
                return $q->where('main_category_id', $val);
            })
            ->when($this->filters['nomor_po'] ?? null, function ($q, $val) {
                return $q->where('nomor_po', 'like', '%' . $val . '%');
            })
            ->when($this->filters['status'] ?? null, function ($q, $val) {
                return $q->where('status', $val);
            })
            ->when($this->filters['tax_category'] ?? null, function ($q, $val) {
                return $q->whereHas('taxCategory', function ($subQ) use ($val) {
                    $subQ->where('name', $val);
                });
            })
            ->when($this->filters['start_date'] ?? null, function ($q, $val) {
                return $q->whereDate('tanggal_penerimaan', '>=', $val);
            })
            ->when($this->filters['end_date'] ?? null, function ($q, $val) {
                return $q->whereDate('tanggal_penerimaan', '<=', $val);
            })
            ->orderBy('tanggal_penerimaan', 'asc');
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Penerimaan',
            'Nomor PO',
            'Tanggal Penerimaan',
            'Kategori Utama',
            'Status Tax',
            'Metode Pembayaran',
            'Tanggal Jatuh Tempo',
            'Total (DPP)',
            'Status',
            'Jumlah Item',
            'Catatan',
            'Dibuat Pada'
        ];
    }

    public function map($penerimaan): array
    {
        return [
            $this->counter++,
            $penerimaan->kode_penerimaan,
            $penerimaan->nomor_po,
            $penerimaan->tanggal_penerimaan->format('d/m/Y'),
            $penerimaan->mainCategory->name ?? '-',
            $penerimaan->taxCategory->name ?? '-',
            $penerimaan->metode_pembayaran,
            $penerimaan->tanggal_jatuh_tempo ? $penerimaan->tanggal_jatuh_tempo->format('d/m/Y') : '-',
            'Rp ' . number_format(round($penerimaan->calculated_total), 0, ',', '.'),
            $penerimaan->status,
            $penerimaan->details->count(),
            $penerimaan->catatan ?? '-',
            $penerimaan->created_at->format('d/m/Y H:i')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ]
            ],
            // Apply borders to all cells
            'A1:M1000' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]
        ];
    }
} 