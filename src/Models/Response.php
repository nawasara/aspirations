<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tanggapan OPD + riwayat status.
 *
 * Tidak pernah disunting — inilah linimasa yang dilihat warga sekaligus jejak
 * audit. Perubahan status apa pun meninggalkan barisnya sendiri.
 */
class Response extends Model
{
    protected $table = 'nawasara_aspirations_responses';

    protected $guarded = [];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'response_id');
    }

    /** Yang tampil ke warga — tanggapan internal disaring. */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }
}
