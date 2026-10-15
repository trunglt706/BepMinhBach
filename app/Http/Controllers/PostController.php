<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Http\Requests\PostIndexRequest;
use App\Http\Requests\PostRequest;
use App\Models\ActivityLog;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\PostReviewNotification;
use App\Support\InvalidImage;
use App\Support\PostImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
            ->with(['batch:id,name,starts_on', 'tags', 'author:id,name', 'cover'])
            // Quản trị viên thấy mọi bài (để duyệt); người khác chỉ thấy bài của mình.
            ->unless($request->user()->isAdmin(), fn ($query) => $query->where('author_id', $request->user()->id))
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByRaw('case when status = ? then 0 else 1 end', [PostStatus::Pending->value]) // bài chờ duyệt lên đầu
            ->orderByRaw('published_at is null desc')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('posts.index', compact('posts', 'q', 'status'));
    }

    /**
     * Form viết bài. Mở từ trang chương trình (?batch=ID) thì chọn sẵn chương trình đó.
     *
     * @param  Request  $request  có thể chứa ?batch=ID (chỉ dùng để chọn sẵn, vẫn được kiểm tra khi lưu)
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Post::class);

        $batchId = CookingBatch::visibleTo($request->user())->whereKey($request->integer('batch'))->value('id');

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
        // save_as đã giới hạn theo vai trò ở PostRequest: chỉ quản trị viên đăng ngay được.
        match (PostStatus::from($data['save_as'])) {
            PostStatus::Published => $this->markPublished($post, $request->user()),
            PostStatus::Pending => $post->markPending(),
            PostStatus::Draft => null,
        };

        $uploads = $this->processUploads($request);
        $this->transactionWithUploads($uploads, function () use ($post, $data, $request, $uploads) {
            $post->save();
            $post->tags()->sync(Tag::findOrCreateMany($data['tag_list'])->pluck('id'));
            $this->applyImages($post, $uploads, $request);
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostCreated,
                'Tạo bài viết #'.$post->id.' ('.$post->status->label().').',
                $post->status === PostStatus::Published ? 'Đăng bài viết “'.$post->title.'”: '.route('blog.show', $post) : null);
            if ($post->status === PostStatus::Pending) {
                $this->notifyAdmins($post, $request->user());
            }
        });

        return $this->done($request, redirect()->route('posts.index'), match ($post->status) {
            PostStatus::Published => 'Đã đăng bài “'.$post->title.'”.',
            PostStatus::Pending => 'Đã gửi duyệt “'.$post->title.'”. Bài sẽ hiện ở trang công khai sau khi quản trị viên duyệt.',
            PostStatus::Draft => 'Đã lưu nháp “'.$post->title.'”.',
        });
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

        // Người không phải quản trị viên sửa bài đang đăng: bài về "Chờ duyệt" (ẩn khỏi trang công khai) để nội dung
        // chưa duyệt không bao giờ hiện ra ngoài.
        $needsReview = $post->status === PostStatus::Published && ! $request->user()->isAdmin();

        $uploads = $this->processUploads($request);
        $this->transactionWithUploads($uploads, function () use ($post, $data, $request, $uploads, $needsReview) {
            $post->fill($data);
            if ($needsReview) {
                $post->markPending();
            }
            $post->save();
            $post->tags()->sync(Tag::findOrCreateMany($data['tag_list'])->pluck('id'));
            $this->applyImages($post, $uploads, $request);
            Tag::pruneUnused();
            // Chỉ ghi số hiệu bài, không ghi nội dung/tên file (log nội bộ không cần dữ liệu bài viết).
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUpdated,
                'Sửa bài viết #'.$post->id.($needsReview ? ' (về chờ duyệt).' : '.'));
            if ($needsReview) {
                $this->notifyAdmins($post, $request->user());
            }
        });

        return $this->done($request, redirect()->route('posts.index'), $needsReview
            ? 'Đã lưu “'.$post->title.'”. Bài tạm ẩn khỏi trang công khai và chờ quản trị viên duyệt lại.'
            : 'Đã lưu bài “'.$post->title.'”.');
    }

    /**
     * Tác giả gửi yêu cầu phát hành (nháp → chờ duyệt); quản trị viên nhận thông báo.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài viết
     */
    public function submit(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('submit', $post);

        DB::transaction(function () use ($post, $request) {
            $post->markPending();
            $post->save();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUpdated, 'Gửi duyệt bài viết #'.$post->id.'.');
            $this->notifyAdmins($post, $request->user());
        });

        return $this->done($request, redirect()->route('posts.index'),
            'Đã gửi duyệt “'.$post->title.'”. Bài sẽ hiện ở trang công khai sau khi quản trị viên duyệt.');
    }

    /**
     * Tác giả rút yêu cầu phát hành (chờ duyệt → nháp).
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài viết
     */
    public function withdraw(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('withdraw', $post);

        DB::transaction(function () use ($post, $request) {
            $post->status = PostStatus::Draft;
            $post->submitted_at = null;
            $post->save();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUpdated, 'Rút yêu cầu duyệt bài viết #'.$post->id.'.');
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã rút yêu cầu duyệt “'.$post->title.'”. Bài trở về bản nháp.');
    }

    /**
     * Quản trị viên duyệt và đăng bài. Gỡ rồi đăng lại giữ nguyên đường dẫn và ngày đăng đầu tiên.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài viết
     */
    public function publish(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('publish', $post);

        DB::transaction(function () use ($post, $request) {
            $wasPending = $post->status === PostStatus::Pending;
            $this->markPublished($post, $request->user());
            $post->save();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostPublished,
                ($wasPending ? 'Duyệt và đăng' : 'Đăng').' bài viết #'.$post->id.'.',
                'Đăng bài viết “'.$post->title.'”: '.route('blog.show', $post));
            if (! $post->isAuthoredBy($request->user())) {
                $post->author?->notify(new PostReviewNotification($post, PostReviewNotification::APPROVED, $request->user()->name));
            }
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã duyệt và đăng bài “'.$post->title.'”.');
    }

    /**
     * Form từ chối (nhập lý do).
     *
     * @param  Post  $post  bài viết
     */
    public function rejectForm(Post $post): View
    {
        Gate::authorize('reject', $post);

        return view('posts.reject', compact('post'));
    }

    /**
     * Quản trị viên từ chối (chờ duyệt → nháp) kèm lý do để tác giả sửa; tác giả nhận thông báo.
     *
     * @param  Request  $request  lý do (bắt buộc)
     * @param  Post  $post  bài viết
     */
    public function reject(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('reject', $post);
        $note = $request->validate(['review_note' => ['required', 'string', 'max:500']], [], ['review_note' => 'lý do'])['review_note'];

        DB::transaction(function () use ($post, $request, $note) {
            $post->status = PostStatus::Draft;
            $post->review_note = $note;
            $post->reviewed_by = $request->user()->id;
            $post->reviewed_at = now();
            $post->save();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUpdated, 'Từ chối duyệt bài viết #'.$post->id.'.');
            $post->author?->notify(new PostReviewNotification($post, PostReviewNotification::REJECTED, $request->user()->name));
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã từ chối “'.$post->title.'” và báo cho tác giả.');
    }

    /**
     * Gỡ bài khỏi trang công khai (về nháp): tác giả hoặc quản trị viên.
     *
     * @param  Request  $request  yêu cầu (không có dữ liệu nhập)
     * @param  Post  $post  bài viết
     */
    public function unpublish(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        Gate::authorize('unpublish', $post);

        DB::transaction(function () use ($post, $request) {
            $post->status = PostStatus::Draft;
            $post->save();
            ActivityLog::record($post->batch, $request->user(), ActivityAction::PostUnpublished, 'Gỡ bài viết #'.$post->id.'.',
                'Gỡ bài viết “'.$post->title.'” khỏi trang công khai.');
        });

        return $this->done($request, redirect()->route('posts.index'), 'Đã gỡ bài “'.$post->title.'” khỏi trang công khai.');
    }

    /**
     * Đánh dấu đã đăng + người duyệt (không lưu). Lần đăng đầu ghi ngày đăng; đăng lại giữ ngày cũ.
     *
     * @param  Post  $post  bài viết
     * @param  User  $admin  quản trị viên duyệt
     */
    private function markPublished(Post $post, User $admin): void
    {
        $post->status = PostStatus::Published;
        $post->published_at ??= now();
        $post->reviewed_by = $admin->id;
        $post->reviewed_at = now();
        $post->review_note = null;
    }

    /**
     * Báo cho mọi quản trị viên đang hoạt động: có bài chờ duyệt.
     *
     * @param  Post  $post  bài viết
     * @param  User  $author  người gửi duyệt
     */
    private function notifyAdmins(Post $post, User $author): void
    {
        $admins = User::active()->where('role', Role::Admin->value)->whereKeyNot($author->id)->get();
        Notification::send($admins, new PostReviewNotification($post, PostReviewNotification::REQUESTED, $author->name));
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
     * Danh sách chương trình cho ô chọn: 200 chương trình gần nhất trong phạm vi được xem, luôn giữ chương trình đang gắn với bài
     * (kể cả chương trình cũ hơn hoặc của người khác — để sửa bài không làm mất liên kết).
     *
     * @param  int|null  $currentId  chương trình đang gắn với bài
     * @return array<int, string> id => "Tên · dd/mm/yyyy"
     */
    private function batchOptions(?int $currentId = null): array
    {
        $batches = CookingBatch::visibleTo(auth()->user())->orderByDesc('starts_on')->limit(200)->get(['id', 'name', 'starts_on']);
        if ($currentId && ! $batches->contains('id', $currentId)) {
            $batches->push(...CookingBatch::whereKey($currentId)->get(['id', 'name', 'starts_on']));
        }

        return $batches->mapWithKeys(fn ($b) => [$b->id => $b->name.' · '.$b->starts_on->format('d/m/Y')])->all();
    }
}
