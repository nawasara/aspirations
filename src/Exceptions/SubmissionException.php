<?php

namespace Nawasara\Aspirations\Exceptions;

use RuntimeException;

/**
 * Laporan warga ditolak.
 *
 * Pesannya DIBACA WARGA, bukan petugas — aplikasi menampilkannya apa adanya.
 * Karena itu tulis apa yang harus dilakukan ("Silakan lanjutkan besok"), bukan
 * apa yang gagal di dalam sistem.
 */
class SubmissionException extends RuntimeException
{
    /** 422: permintaannya sah, aturannya yang tidak mengizinkan. */
    public function getStatusCode(): int
    {
        return 422;
    }
}
