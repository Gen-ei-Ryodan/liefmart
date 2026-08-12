<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class LokasiSeeder extends Seeder
{
    public function run()
    {
        Lokasi::updateOrCreate(['kode' => 'UNLOCATED'], [
            'nama' => 'Unlocated',
            'deskripsi' => 'Tempat penyimpanan sementara barang yang baru diterima',
        ]);

        Lokasi::updateOrCreate(['kode' => 'GUDANG_A'], [
            'nama' => 'Gudang A',
            'deskripsi' => 'Gudang utama penyimpanan barang',
        ]);
    }
}
