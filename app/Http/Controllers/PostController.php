<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\PostStatus;
use App\Http\Requests\PostIndexRequest;
use App\Http\Requests\PostRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Tag;
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

/** Quản lý bài viết (nội bộ, điều phối viên trở lên). Trang khách xem nằm ở PublicPostController. */
class PostController extends Controller
{
    /**
     * @param  PostImageProcessor  $images  chuyển ảnh tải lên thành các bản WebP
     */
    public function __construct(private readonly PostImageProcessor $images) {}

    /**
     * Danh sách bài viết (cả nháp), lọc theo tiêu đề và trạng thái.
     *
     * @param  PostIndexRequest  $request  bộ lọc đã kiểm tra (q, status)
     */
    public function index(PostIndexRequest $request): View
    {
        Gate::authorize('viewAny', Post::class);

        $q = $request->keyword();
        $status = $request->status();

        $posts = Post::query()
            ->with(['batch:id,name,starts_at', 'tags', 'author:id,name', 'cover'])
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByRaw('published_at is null desc')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('posts.index', compact('posts', 'q', 'status'));
    }

    /**
     * Form viết bài. Mở từ trang đợt nấu (?batch=ID) thì chọn sẵn đợt đó.
     *
     * @param  Request  $request  có thể chứa ?batch=ID (chỉ dùng để chọn sẵn, vẫn được kiểm tra khi lưu)
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Post::class);

        $batchId = CookingBatch::whereKey($request->integer('batch'))->value('id');

        return view('posts.create', [
            'post' => new Post(['cooking_batch_id' => $batchId]),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name'),
            'batches' => $this->batchOptions(),
        ]);
    }

    /**
     * Lưu bài mới (nháp hoặc đăng ngay), kèm thẻ và ảnh.
     *
     * @param  PostRequest  $request  dữ liệu đã kiểm tra, gồm file ảnh
     *
     * @throws ValidationException khi ảnh không giải mã được
     */
    public function store(PostRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $post = new Post($data);
        $post->author_id = $request->user()->id;
        if (PostStatus::from($data['save_as']) === PostStatus::Published) {
            $post->status = PostStatus::Published;
            $post->published_at = now();
        }

        $uploads = $this->processUploads($request);
        $this->transactionWithUploads($uploads, function () use ($post, $data, $request, $uploads) {
            $post->save();
            $post->tags()->sync(Tag::findOrCreateMany($data['tag_list'])->pluck('id'));
            $this->applyImages($post, $uploads, $request);
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostCreated,
                'Tạo bài viết #'.$post->id.' ('.$post->status->label().').');
        });

        return $this->done($request, redirect()->route('posts.index'), $post->status === PostStatus::Published
            ? 'Đã đăng bài “'.$post->title.'”.'
            : 'Đã lưu nháp “'.$post->title.'”.');
    }

    /**
     * Form sửa bài.
     *
     * @param  Post  $post  bài cần sửa
     */
    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.edit', [
            'post' => $post->load(['tags', 'cover', 'gallery']),
            'tagSuggestions' => Tag::orderBy('name')->pluck('name'),
            'batches' => $this->batchOptions($post->cooking_batch_id),
        ]);
    }

    /**
     * Lưu thay đổi: nội dung, thẻ, ảnh đại diện (thay/xóa), ảnh album (thêm/xóa/chú thích).
     *
     * @param  PostRequest  $request  dữ liệu đã kiểm tra
     * @param  Post  $post  bài cần sửa
     *
     * @throws ValidationException khi ảnh không giải mã được
     */
    public function update(PostRequest $request, Post $post): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $uploads = $this->processUploads($request);
        $this->transactionWithUploads($uploads, function () use ($post, $data, $request, $uploads) {
            $post->fill($data)->save();
            $post->tags()->sync(Tag::findOrCreateMany($data['tag_list'])->pluck('id'));
            $this->applyImages($post, $uploads, $request);
            Tag::pruneUnused();
            // Chỉ ghi số hiệu bài, không ghi nội dung/tên file (log nội bộ không cần dữ liệu bài viết).
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUpdated, 'Sửa bài viết #'.$post->id.'.');
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã lưu bài “'.$post->title.'”.');
    }

    /**
     * Đăng hoặc gỡ bài. Gỡ rồi đăng lại giữ nguyên đường dẫn và ngày đăng đầu tiên.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài cần đổi trạng thái
     */
    public function togglePublish(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('publish', $post);

        DB::transaction(function () use ($post, $request) {
            if ($post->status === PostStatus::Published) {
                $post->status = PostStatus::Draft;
            } else {
                $post->status = PostStatus::Published;
                $post->published_at ??= now();
            }
            $post->save();
            $published = $post->status === PostStatus::Published;
            ActivityLog::record($post->batch, $request->user(),
                $published ? ActivityAction::PostPublished : ActivityAction::PostUnpublished,
                ($published ? 'Đăng' : 'Gỡ').' bài viết #'.$post->id.'.');
        });

        return $this->done($request, redirect()->route('posts.index'), $post->status === PostStatus::Published
            ? 'Đã đăng bài “'.$post->title.'”.'
            : 'Đã gỡ bài “'.$post->title.'” khỏi trang công khai.');
    }

    /**
     * Xóa hẳn bài viết (ảnh WebP được xóa sau khi giao dịch thành công).
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài cần xóa
     */
    public function destroy(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $post);

        DB::transaction(function () use ($post, $request) {
            $post->delete();
            Tag::pruneUnused();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostDeleted, 'Xóa bài viết #'.$post->id.'.');
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã xóa bài “'.$post->title.'”.');
    }

    /**
     * Chuyển các ảnh tải lên thành WebP TRƯỚC khi mở giao dịch (xử lý ảnh chậm, không giữ khóa DB).
     *
     * @param  PostRequest  $request  yêu cầu đã kiểm tra
     * @return array{cover: ?array, gallery: list<array>} kết quả PostImageProcessor::store()
     *
     * @throws ValidationException ảnh hỏng/giả mạo — các file đã tạo được xóa trước khi báo lỗi
     */
    private function processUploads(PostRequest $request): array
    {
        $uploads = ['cover' => null, 'gallery' => []];
        $current = 'cover';

        try {
            if ($request->file('cover') instanceof UploadedFile) {
                $uploads['cover'] = $this->images->store($request->file('cover'));
            }
            $current = 'gallery';
            foreach ($request->file('gallery', []) as $file) {
                $uploads['gallery'][] = $this->images->store($file);
            }
        } catch (InvalidImage $e) {
            $this->discardUploads($uploads);
            throw ValidationException::withMessages([$current => $e->getMessage()]);
        }

        return $uploads;
    }

    /**
     * Chạy giao dịch; nếu lỗi thì xóa các file WebP vừa tạo để không để lại file mồ côi.
     *
     * @param  array{cover: ?array, gallery: list<array>}  $uploads  ảnh đã xử lý
     * @param  callable  $callback  thao tác ghi DB
     *
     * @throws Throwable lỗi gốc của giao dịch
     */
    private function transactionWithUploads(array $uploads, callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (Throwable $e) {
            $this->discardUploads($uploads);
            throw $e;
        }
    }

    /**
     * Ghi thay đổi ảnh vào DB: thay/xóa ảnh đại diện, xóa/chú thích ảnh album cũ, thêm ảnh album mới.
     * Chỉ thao tác trên ảnh của CHÍNH bài này (ID ảnh của bài khác bị bỏ qua — chống sửa ID trong form).
     *
     * @param  Post  $post  bài viết đã lưu
     * @param  array{cover: ?array, gallery: list<array>}  $uploads  ảnh mới đã xử lý
     * @param  PostRequest  $request  chứa remove_cover, remove_images[], captions[id]
     */
    private function applyImages(Post $post, array $uploads, PostRequest $request): void
    {
        if ($uploads['cover'] || $request->boolean('remove_cover')) {
            $post->images()->where('kind', PostImage::COVER)->get()->each->delete();
        }
        if ($uploads['cover']) {
            $post->images()->create(['kind' => PostImage::COVER, ...$uploads['cover']]);
        }

        $removeIds = collect($request->validated('remove_images', []))->map(fn ($id) => (int) $id);
        $post->gallery()->whereIn('id', $removeIds)->get()->each->delete();

        foreach ($request->validated('captions', []) as $id => $caption) {
            $post->gallery()->whereKey((int) $id)->update(['caption' => $caption !== null ? trim($caption) ?: null : null]);
        }

        $order = (int) $post->gallery()->max('sort_order');
        foreach ($uploads['gallery'] as $image) {
            $post->images()->create(['kind' => PostImage::GALLERY, 'sort_order' => ++$order, ...$image]);
        }
    }

    /**
     * Xóa file của các ảnh đã xử lý nhưng không được lưu vào DB.
     *
     * @param  array{cover: ?array, gallery: list<array>}  $uploads
     */
    private function discardUploads(array $uploads): void
    {
        foreach (array_filter([$uploads['cover'], ...$uploads['gallery']]) as $image) {
            $this->images->delete($image['path'], $image['widths']);
        }
    }

    /**
     * Danh sách đợt nấu cho ô chọn: 200 đợt gần nhất, luôn giữ đợt đang gắn với bài (kể cả đợt cũ hơn).
     *
     * @param  int|null  $currentId  đợt đang gắn với bài
     * @return array<int, string> id => "Tên · dd/mm/yyyy"
     */
    private function batchOptions(?int $currentId = null): array
    {
        $batches = CookingBatch::query()->orderByDesc('starts_at')->limit(200)->get(['id', 'name', 'starts_at']);
        if ($currentId && ! $batches->contains('id', $currentId)) {
            $batches->push(...CookingBatch::whereKey($currentId)->get(['id', 'name', 'starts_at']));
        }

        return $batches->mapWithKeys(fn ($b) => [$b->id => $b->name.' · '.$b->starts_at->format('d/m/Y')])->all();
    }
}
