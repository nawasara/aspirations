<?php

namespace Nawasara\Aspirations\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Nawasara\Aspirations\Http\Resources\CategoryResource;
use Nawasara\Aspirations\Models\Category;

/**
 * Daftar kategori untuk aplikasi warga — kontrak 6.2 di rencana teknis.
 *
 * Aplikasi menyimpan salinannya (Lapor Bunda dirancang luring-dulu), dan cukup
 * membandingkan `version` sebelum mengunduh ulang.
 */
class CategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->with('opd')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),

            // Penanda perubahan untuk cache di aplikasi. Memakai waktu ubah
            // TERBARU, bukan waktu sekarang — kalau memakai now(), setiap
            // permintaan tampak seperti perubahan dan aplikasi mengunduh ulang
            // terus-menerus.
            'version' => optional($categories->max('updated_at'))->toIso8601String(),
        ]);
    }
}
