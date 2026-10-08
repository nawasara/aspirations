<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kecamatan Ponorogo — 21 baris tetap.
 *
 * Dipakai mengelompokkan laporan di peta, karena kolom `district` hasil
 * geocoding kosong pada seluruh laporan produksi. Lihat catatan migrasinya.
 */
class District extends Model
{
    protected $table = 'nawasara_aspirations_districts';

    protected $fillable = ['code', 'name', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Titik tengah (`latitude`/`longitude`) kini HANYA untuk menaruh
    // penanda kecamatan di peta. Kecamatan sebuah laporan ditentukan dari
    // batas wilayah oleh Services\BoundaryLocator.
    //
    // `nearest()` yang dulu ada di sini memilih titik tengah terdekat, dan
    // meleset di setiap perbatasan: 7 dari 30 laporan produksi tersimpan di
    // kecamatan tetangga. Sengaja dihapus, bukan ditinggalkan, supaya tidak
    // ada yang memakainya lagi karena terlihat lebih sederhana.
}
