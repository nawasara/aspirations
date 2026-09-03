<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desa atau kelurahan — 307 di Kabupaten Ponorogo.
 *
 * Kodenya Kemendagri 10 digit dan selalu BERAWALAN kode kecamatannya
 * (350202 → 3502022001), sehingga pasangan yang tidak cocok dapat ditolak
 * tanpa menyentuh tabel ini sama sekali.
 *
 * ⚠️ Lima belas nama muncul di lebih dari satu kecamatan — Tugurejo, Wates,
 * Sendang, Temon, Kunti, Karangpatihan, dan seterusnya. Jangan pernah mencari
 * desa lewat namanya saja.
 */
class Village extends Model
{
    protected $table = 'nawasara_aspirations_villages';

    protected $fillable = ['code', 'district_code', 'name', 'is_kelurahan'];

    protected $casts = ['is_kelurahan' => 'boolean'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    /**
     * Kelurahan atau desa — perbedaannya nyata, bukan sekadar label.
     *
     * Kelurahan dipimpin lurah dan tidak punya pemerintahan desa sendiri;
     * penyaluran laporan bisa berbeda karenanya.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->is_kelurahan ? 'Kelurahan' : 'Desa';
    }
}
