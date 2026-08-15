<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori laporan — 13 urusan, satu tingkat (datar).
 *
 * `parent_id` ada tetapi TIDAK dipakai: semua NULL. Disiapkan agar
 * pengelompokan sisi-panel dapat menyusul tanpa migrasi atas tabel yang sudah
 * berisi ratusan ribu laporan.
 */
class Category extends Model
{
    use HasUuids;

    protected $table = 'nawasara_aspirations_categories';

    protected $guarded = [];

    protected $casts = [
        'sla_hours' => 'integer',
        'sort_order' => 'integer',
        'uses_working_days' => 'boolean',
        'is_active' => 'boolean',
        'is_sensitive' => 'boolean',
        'requires_evidence' => 'boolean',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'category_id');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(\Nawasara\Registry\Models\Opd::class, 'opd_id');
    }

    /** Kategori yang ditawarkan ke warga, terurut. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Siap dipakai disposisi otomatis?
     *
     * Kategori tanpa `opd_id` akan membuat laporan berhenti di pintu masuk —
     * lebih baik ketahuan saat memeriksa daripada saat warga mengirim.
     */
    public function isDispatchable(): bool
    {
        return $this->opd_id !== null;
    }
}
