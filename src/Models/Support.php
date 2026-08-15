<?php

namespace Nawasara\Aspirations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Saya Juga Mengalami" — satu warga satu dukungan per laporan. */
class Support extends Model
{
    use HasUuids;

    protected $table = 'nawasara_aspirations_supports';

    protected $guarded = [];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}
