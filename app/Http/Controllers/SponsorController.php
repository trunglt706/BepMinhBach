<?php

namespace App\Http\Controllers;

use App\Enums\SponsorType;
use App\Http\Requests\SponsorRequest;
use App\Models\Sponsor;
use App\Support\InvalidImage;
use App\Support\PostImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Danh bạ đơn vị tài trợ / hỗ trợ (khu quản lý). Trang công khai ở PublicSponsorController. */
class SponsorController extends Controller
{
    /** Thư mục lưu logo (đĩa uploads). */
    private const LOGO_DIRECTORY = 'sponsors';

    /**
     * @param  PostImageProcessor  $images  chuyển logo tải lên thành các bản WebP
     */
    public function __construct(private readonly PostImageProcessor $images) {}

    /**
     * Danh sách, lọc theo tên và phân loại.
     *
     * @param  Request  $request  ?q=&type=
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sponsor::class);

        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $type = SponsorType::tryFrom((string) $request->query('type'));

        $sponsors = Sponsor::query()
            ->withCount('batches')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('sponsors.index', compact('sponsors', 'q', 'type'));
    }

    /**
     * Form thêm đơn vị.
     */
    public function create(): View
    {
        Gate::authorize('create', Sponsor::class);

        return view('sponsors.create', ['sponsor' => new Sponsor(['type' => SponsorType::Sponsor, 'is_active' => true])]);
    }

    /**
     * Lưu đơn vị mới.
     *
     * @param  SponsorRequest  $request  dữ liệu đã kiểm tra + quyền
     */
    public function store(SponsorRequest $request): JsonResponse|RedirectResponse
    {
        $logo = $this->processLogo($request);
        $sponsor = new Sponsor($request->safe()->except(['logo', 'remove_logo']));
        $sponsor->created_by = $request->user()->id;
        $this->fillLogo($sponsor, $logo);
        $this->transactionWithLogo($logo, fn () => $sponsor->save());

        return $this->done($request, redirect()->route('sponsors.index'), 'Đã thêm đơn vị “'.$sponsor->name.'”.');
    }

    /**
     * Form sửa đơn vị.
     *
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function edit(Sponsor $sponsor): View
    {
        Gate::authorize('update', $sponsor);

        return view('sponsors.edit', compact('sponsor'));
    }

    /**
     * Lưu thay đổi (logo mới thay logo cũ; file cũ xóa sau khi lưu thành công).
     *
     * @param  SponsorRequest  $request  dữ liệu đã kiểm tra + quyền
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function update(SponsorRequest $request, Sponsor $sponsor): JsonResponse|RedirectResponse
    {
        $logo = $this->processLogo($request);
        $old = $sponsor->logo_path ? [$sponsor->logo_path, $sponsor->logo_widths ?? []] : null;
        $replace = $logo !== null || $request->boolean('remove_logo');

        $sponsor->fill($request->safe()->except(['logo', 'remove_logo']));
        if ($replace) {
            $this->fillLogo($sponsor, $logo);
        }
        $this->transactionWithLogo($logo, function () use ($sponsor, $replace, $old) {
            $sponsor->save();
            if ($replace && $old) {
                DB::afterCommit(fn () => $this->images->delete(...$old));
            }
        });

        return $this->done($request, redirect()->route('sponsors.index'), 'Đã lưu đơn vị “'.$sponsor->name.'”.');
    }

    /**
     * Xóa đơn vị (gỡ khỏi mọi chương trình; logo xóa sau khi lưu thành công).
     *
     * @param  Request  $request  yêu cầu hiện tại
     * @param  Sponsor  $sponsor  đơn vị
     */
    public function destroy(Request $request, Sponsor $sponsor): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $sponsor);

        $old = $sponsor->logo_path ? [$sponsor->logo_path, $sponsor->logo_widths ?? []] : null;
        DB::transaction(function () use ($sponsor, $old) {
            $sponsor->delete();
            if ($old) {
                DB::afterCommit(fn () => $this->images->delete(...$old));
            }
        });

        return $this->done($request, redirect()->route('sponsors.index'), 'Đã xóa đơn vị “'.$sponsor->name.'”.');
    }

    /**
     * Chuyển logo tải lên thành WebP trước khi mở giao dịch.
     *
     * @param  SponsorRequest  $request  yêu cầu đã kiểm tra
     * @return array{path: string, widths: list<int>, width: int, height: int}|null
     *
     * @throws ValidationException ảnh hỏng/giả mạo
     */
    private function processLogo(SponsorRequest $request): ?array
    {
        $file = $request->file('logo');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        try {
            return $this->images->store($file, self::LOGO_DIRECTORY);
        } catch (InvalidImage $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }
    }

    /**
     * Gán (hoặc xóa khi null) thông tin logo.
     *
     * @param  Sponsor  $sponsor  đơn vị
     * @param  array{path: string, widths: list<int>, width: int, height: int}|null  $logo  ảnh đã xử lý
     */
    private function fillLogo(Sponsor $sponsor, ?array $logo): void
    {
        $sponsor->forceFill([
            'logo_path' => $logo['path'] ?? null,
            'logo_widths' => $logo['widths'] ?? null,
            'logo_width' => $logo['width'] ?? null,
            'logo_height' => $logo['height'] ?? null,
        ]);
    }

    /**
     * Chạy giao dịch; lỗi thì xóa logo vừa tạo để không để lại file mồ côi.
     *
     * @param  array{path: string, widths: list<int>}|null  $logo  ảnh vừa xử lý
     * @param  callable  $callback  thao tác ghi DB
     *
     * @throws Throwable lỗi gốc của giao dịch
     */
    private function transactionWithLogo(?array $logo, callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (Throwable $e) {
            if ($logo) {
                $this->images->delete($logo['path'], $logo['widths']);
            }
            throw $e;
        }
    }
}
