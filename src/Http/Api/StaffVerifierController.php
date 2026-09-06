<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nawasara\Aspirations\Services\VerifierDirectory;

/**
 * Daftar calon pemeriksa, di bawah token petugas yang sedang masuk.
 *
 * ## Kenapa endpoint ini ada
 *
 * Panel OPD sebelumnya memanggil `GET /api/v1/keycloak/users` memakai
 * `KABID_API_TOKEN` — token STATIS di berkas `.env` panel. Tiga akibatnya:
 *
 *   1. Siapa pun yang dapat membuka panel dapat menelusuri SELURUH direktori
 *      pegawai. Panel tidak dapat membatasinya per OPD karena ia tidak tahu
 *      pegawai mana milik OPD mana.
 *   2. Token itu tidak terikat siapa pun dan tidak kedaluwarsa. Bila bocor,
 *      tidak ada jejak siapa yang memakainya, dan mencabutnya mematikan fitur
 *      bagi semua orang sekaligus.
 *   3. Panel menarik seluruh direktori lalu menyaring di memori — pegawai baru
 *      tidak muncul sampai cache lima menitnya habis.
 *
 * Dengan endpoint ini, `KABID_API_TOKEN` dapat dihapus sepenuhnya: yang
 * dipakai adalah token petugas, dan server yang menyaring.
 *
 * ⚠️ Namanya `verifiers`, bukan `kabid`. Yang dicari adalah PERANNYA dalam
 * alur, dan di sebagian OPD bukan Kepala Bidang yang memegangnya.
 */
class StaffVerifierController
{
    public function __construct(
        protected VerifierDirectory $directory,
    ) {}

    /**
     * GET /api/v1/staff/aspirations/verifiers?search=budi
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],

            // Diterima demi kejelasan pemanggil, tetapi TIDAK mengubah hasil:
            // aturan pemeriksa bergantung pada petugas dan OPD-nya, bukan pada
            // laporan tertentu. Menolaknya hanya akan memaksa panel menghapus
            // parameter yang wajar ia kirim.
            'report' => ['nullable', 'string', 'max:32'],
        ]);

        $actor = $request->user();

        $candidates = $this->directory->candidatesFor($actor, $data['search'] ?? null);

        // Kosong karena pencarian, atau kosong karena OPD ini memang belum
        // punya pemeriksa? Keduanya menuntut kalimat berbeda di layar, dan
        // panel tidak dapat membedakannya tanpa penanda ini.
        $meta = [];

        if ($candidates->isEmpty()) {
            $adaCalon = ($data['search'] ?? null)
                ? $this->directory->hasAnyCandidate($actor)
                : false;

            $meta['reason'] = $adaCalon ? 'no_match' : 'no_verifier_in_opd';
        }

        return response()->json([
            'data' => $candidates->map(fn (object $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,

                // Menjawab dua hal sekaligus: membedakan nama yang mirip, dan
                // menjelaskan kenapa seseorang muncul padahal beda dinas.
                'opd_name' => $u->opd_name,
                'membership' => $u->membership,
            ])->all(),
        ] + ($meta === [] ? [] : ['meta' => $meta]));
    }
}
