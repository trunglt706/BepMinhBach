<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use App\Support\PublicPostCache;
use Illuminate\View\View;

/**
 * Trang công khai riêng của từng đơn vị tài trợ / hỗ trợ (giới thiệu + các chương trình đã tham gia).
 * Không có trang danh sách công khai: khách đến từ khối "Đơn vị tham gia" ở trang chương trình.
 * Chỉ đơn vị đang hiển thị; chỉ chương trình công khai được.
 */
class PublicSponsorController extends Controller
{
    /**
     * Trang riêng của một đơn vị.
     *
     * @param  Sponsor  $sponsor  đơn vị (theo slug)
     */
    public function show(Sponsor $sponsor): View
    {
        abort_unless($sponsor->is_active, 404);

        $batches = PublicPostCache::remember('sponsor:'.$sponsor->id.':batches', fn () => $sponsor->batches()
            ->publiclyVisible()->orderByDesc('starts_on')->get(['cooking_batches.id', 'public_id', 'name', 'starts_on', 'status']));

        return view('public.sponsors.show', compact('sponsor', 'batches'));
    }
}
