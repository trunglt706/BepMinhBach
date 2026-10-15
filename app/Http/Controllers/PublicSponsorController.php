<?php

namespace App\Http\Controllers;

use App\Enums\SponsorType;
use App\Models\Sponsor;
use App\Support\PublicPostCache;
use Illuminate\View\View;

/**
 * Trang công khai về các đơn vị tài trợ / hỗ trợ: danh sách theo phân loại và trang riêng từng đơn vị
 * (giới thiệu + các chương trình đã tham gia). Chỉ đơn vị đang hiển thị; chỉ chương trình công khai được.
 */
class PublicSponsorController extends Controller
{
    /**
     * Danh sách đơn vị, nhóm theo phân loại.
     */
    public function index(): View
    {
        $groups = PublicPostCache::remember('sponsors:index', fn () => Sponsor::active()
            ->withCount(['batches' => fn ($q) => $q->publiclyVisible()])
            ->orderBy('name')->get())
            ->groupBy(fn (Sponsor $s) => $s->type->value);

        return view('public.sponsors.index', ['groups' => $groups, 'types' => SponsorType::cases()]);
    }

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
