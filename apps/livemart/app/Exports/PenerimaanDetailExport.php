<?php

namespace App\Exports;

use App\Models\Penerimaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class PenerimaanDetailExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithColumnFormatting
{
    protected $filters;
    protected $data;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->prepareData();
    }

    private function prepareData()
    {
        $penerimaans = Penerimaan::with(['mainCategory', 'taxCategory', 'details.product', 'details.satuan'])
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
            ->orderBy('tanggal_penerimaan', 'asc')
            ->get();

        $this->data = collect();

        foreach ($penerimaans as $penerimaan) {
            // Add header row for each penerimaan
            $this->data->push([
                'type' => 'header',
                'penerimaan' => $penerimaan,
                'detail' => null
            ]);

            // Add detail rows for each penerimaan
            foreach ($penerimaan->details as $detail) {
                $this->data->push([
                    'type' => 'detail',
                    'penerimaan' => $penerimaan,
                    'detail' => $detail
                ]);
            }

            // Add empty rows for spacing
            $this->data->push([
                'type' => 'spacer',
                'penerimaan' => null,
                'detail' => null
            ]);
            $this->data->push([
                'type' => 'spacer',
                'penerimaan' => null,
                'detail' => null
            ]);
        }
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Penerimaan',
            'Tanggal Penerimaan',
            'Nomor PO',
            'Status Tax',
            'Nama Barang',
            'Qty',
            'Satuan',
            'Harga per Barang',
            'Diskon %',
            'Diskon Nominal',
            'Subtotal',
            'Total Diskon',
            'Harga Total Setelah Diskon',
            'Catatan Barang'
        ];
    }

    public function map($row): array
    {
        if ($row['type'] === 'header') {
            $penerimaan = $row['penerimaan'];
            
            // Calculate totals for this specific penerimaan
            $penerimaanTotalQty = 0;
            $penerimaanTotalSubtotal = 0;
            $penerimaanTotalDiscount = 0;
            $penerimaanTotalAfterDiscount = 0;
            
            foreach ($penerimaan->details as $detail) {
                $qty = (float)($detail->qty ?? 0);
                $hargaHpp = (float)($detail->harga_hpp ?? 0);
                $subtotalSebelumDiskon = $qty * $hargaHpp;
                
                // Calculate cascading discounts
                $subtotal = $subtotalSebelumDiskon;
                $totalDiskonNominal = 0;
                
                for ($i = 1; $i <= 5; $i++) {
                    $diskonPersen = $detail->{"diskon_persen_$i"} ?? 0;
                    $diskonNominal = $detail->{"diskon_nominal_$i"} ?? 0;
                    
                    if ($diskonPersen > 0) {
                        $potongan = $subtotal * ($diskonPersen / 100);
                        $subtotal -= $potongan;
                    } elseif ($diskonNominal > 0) {
                        $subtotal -= $diskonNominal;
                        $totalDiskonNominal += $diskonNominal;
                    }
                }
                
                $totalDiskon = $subtotalSebelumDiskon - $subtotal;
                $hargaTotalSetelahDiskon = $subtotal;
                
                $penerimaanTotalQty += $qty;
                $penerimaanTotalSubtotal += $subtotalSebelumDiskon;
                $penerimaanTotalDiscount += $totalDiskon;
                $penerimaanTotalAfterDiscount += $hargaTotalSetelahDiskon;
            }
            
            return [
                'PENERIMAAN BARANG',
                $penerimaan->kode_penerimaan,
                $penerimaan->tanggal_penerimaan->format('d/m/Y'),
                $penerimaan->nomor_po,
                $penerimaan->status,
                'Kategori: ' . ($penerimaan->mainCategory->name ?? '-'),
                'Total QTY: ' . $penerimaanTotalQty,
                'Tax: ' . ($penerimaan->taxCategory->name ?? '-'),
                'Metode: ' . $penerimaan->metode_pembayaran,
                '',
                '',
                'Total Subtotal: Rp ' . number_format($penerimaanTotalSubtotal, 0, ',', '.'),
                'Total Diskon: Rp ' . number_format($penerimaanTotalDiscount, 0, ',', '.'),
                'Total Value: Rp ' . number_format($penerimaanTotalAfterDiscount, 0, ',', '.'),
                ''
            ];
        } elseif ($row['type'] === 'detail') {
            $penerimaan = $row['penerimaan'];
            $detail = $row['detail'];
            
            // Calculate subtotal before discount
            $subtotalSebelumDiskon = $detail->qty * $detail->harga_hpp;
            $subtotal = $subtotalSebelumDiskon;
            
            // Calculate cascading discounts (bertingkat)
            $totalDiskonPersen = 0;
            $totalDiskonNominal = 0;
            $discountPercentages = []; // Array to store individual percentages
            
            for ($i = 1; $i <= 5; $i++) {
                $diskonPersen = $detail->{"diskon_persen_$i"} ?? 0;
                $diskonNominal = $detail->{"diskon_nominal_$i"} ?? 0;
                
                if ($diskonPersen > 0) {
                    $potongan = $subtotal * ($diskonPersen / 100);
                    $subtotal -= $potongan;
                    $totalDiskonPersen += $diskonPersen;
                    $discountPercentages[] = number_format($diskonPersen, 2) . '%'; // Store individual percentage with 2 decimal places
                } elseif ($diskonNominal > 0) {
                    $subtotal -= $diskonNominal;
                    $totalDiskonNominal += $diskonNominal;
                }
            }
            
            $totalDiskon = $subtotalSebelumDiskon - $subtotal;
            $hargaTotalSetelahDiskon = $subtotal;
            
            return [
                $this->getCounter(),
                $penerimaan->kode_penerimaan,
                $penerimaan->tanggal_penerimaan->format('d/m/Y'),
                $penerimaan->nomor_po ?? '-',
                $penerimaan->taxCategory->name ?? '-',
                $detail->product->name ?? '-',
                (int) $detail->qty, // Angka untuk qty (tanpa desimal)
                $detail->satuan->name ?? '-',
                round((float) $detail->harga_hpp, 2), // Harga per barang 2 desimal
                empty($discountPercentages) ? '-' : implode('+', $discountPercentages), // Format individual percentages like "4%+1%"
                round((float) $totalDiskonNominal, 2), // Diskon nominal 2 desimal
                round((float) $subtotalSebelumDiskon, 2), // Subtotal 2 desimal
                round((float) $totalDiskon, 2), // Total diskon 2 desimal
                round((float) $hargaTotalSetelahDiskon, 2), // Total setelah diskon 2 desimal
                $detail->catatan ?? '-'
            ];
        } else {
            // Spacer row
            return array_fill(0, 15, '');
        }
    }

    private $counter = 1;

    private function getCounter()
    {
        return $this->counter++;
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [
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
            'A1:O1000' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]
        ];

        return $styles;
    }

    public function columnFormats(): array
    {
        return [
            'G' => '0', // Qty (tanpa desimal)
            'I' => '"Rp" #,##0.00', // Harga per Barang (2 desimal)
            'J' => '0.00%', // Diskon % dengan 2 desimal
            'K' => '"Rp" #,##0.00', // Diskon Nominal (2 desimal)
            'L' => '"Rp" #,##0.00', // Subtotal (2 desimal)
            'M' => '"Rp" #,##0.00', // Total Diskon (2 desimal)
            'N' => '"Rp" #,##0.00', // Harga Total Setelah Diskon (2 desimal)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                
                // Auto-adjust column widths
                foreach (range('A', 'O') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Find and apply green background to header rows (PENERIMAAN BARANG rows)
                // Search through all rows to find rows containing "PENERIMAAN BARANG" in column A
                $highestRow = $sheet->getHighestRow();
                for ($row = 2; $row <= $highestRow; $row++) {
                    $cellValue = $sheet->getCell('A' . $row)->getValue();
                    if ($cellValue === 'PENERIMAAN BARANG') {
                        // Apply green styling to entire header row (all columns A to O)
                        $sheet->getStyle('A' . $row . ':O' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '00FF00'] // Bright green
                            ],
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => '000000']
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000']
                                ]
                            ]
                        ]);
                        
                        // Pastikan baris header per-PO menampilkan total tanpa desimal di kolom I
                        $sheet->getDelegate()->getStyle('I' . $row)
                            ->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    }
                }
            },
        ];
    }
}
