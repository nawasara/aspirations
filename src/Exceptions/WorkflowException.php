<?php

namespace Nawasara\Aspirations\Exceptions;

use RuntimeException;

/**
 * Perpindahan status yang ditolak.
 *
 * Pesannya ditujukan untuk DIBACA PETUGAS, bukan sekadar dicatat di log —
 * panel menampilkannya apa adanya. Karena itu tulis alasannya, bukan kode
 * galat: "Anda tidak dapat memverifikasi pekerjaan Anda sendiri" memberi tahu
 * apa yang harus dilakukan; "transition denied" tidak.
 */
class WorkflowException extends RuntimeException
{
    /** Dijawab 422: permintaannya sah, keadaannya yang tidak mengizinkan. */
    public function getStatusCode(): int
    {
        return 422;
    }
}
