<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Http\Requests\PublicPostIndexRequest;
use App\Models\CookingBatch;
use App\Models\Post;
use App\Support\PostViewCounter;
use App\Support\PublicPostCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Trang bài viết cho khách (không cần đăng nhập). Chỉ hiện bài đã đăng.
 * Thông tin đợt nấu đi kèm chỉ gồm số liệu tổng hợp (tên, thời gian, trạng thái, số suất);
 * không hiện người phụ trách, công việc, nguyên liệu hay chi phí (xem docs/SECURITY_PRIVACY.md).
 */
class PublicPostController extends Controller
{
    /** Số bài mỗi trang danh sách. */
    private const PER_PAGE = 6;

    /**
     * Danh sách bài đã đăng, tìm theo từ khóa / thẻ / đợt nấu.
     * Trang kết quả tìm kiếm/lọc được đánh noindex (tránh trang trùng lặp trên công cụ tìm kiếm).
     *
     * @param  PublicPostIndexRequest  $request  bộ lọc đã chuẩn hóa
     */
    public function index(PublicPostIndexRequest $request): View
    {

        $q = $request->keyword();
        $tag = $request->tag();
        $batch = $request->batch();
        $words = $request->words();
        $page = max(1, $request->integer('page', 1));

        // Kết quả theo đúng bộ lọc + trang được lưu đệm (xem PublicPostCache: tự làm mới khi dữ liệu đổi).
        $cacheKey = 'index:'.md5(json_encode([$words->all(), $tag?->id, $batch?->id, $page]));
        $posts = PublicPostCache::remember($cacheKey, fn () => Post::published()
            ->with(['tags', 'batch:id,public_id,name,starts_on', 'cover', 'author:id,name'])
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($t) => $t->whereKey($tag->id)))
            ->when($batch, fn ($query) => $query->where('cooking_batch_id', $batch->id))
            // Mỗi từ khóa phải xuất hiện ở tiêu đề, tóm tắt, nội dung (chữ thuần, không khớp nhầm thẻ HTML) hoặc tên thẻ.
            ->when($words->isNotEmpty(), function ($query) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $query->where(fn ($w) => $w->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('body_text', 'like', $like)
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like)));
                }
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page))
            ->withQueryString();

        $filtered = $q !== '' || $tag || $batch;

        // Slider đầu trang chủ: các đợt ĐANG DIỄN RA (chỉ trang 1, không lọc).
        $ongoing = ! $filtered && $posts->onFirstPage()
            ? PublicPostCache::remember('ongoing-batches', fn () => CookingBatch::where('status', BatchStatus::InProgress->value)
                ->orderBy('starts_on')->orderBy('id')
                ->get(['id', 'public_id', 'name', 'starts_on', 'status', 'expected_servings', 'cover_path', 'cover_widths', 'cover_width', 'cover_height']))
            : collect();

        return view('public.posts.index', [
            'posts' => $posts,
            'ongoing' => $ongoing,
            'q' => $q,
            'tag' => $tag,
            'batch' => $batch,
            'filtered' => $filtered,
            'noindex' => $filtered,
            'canonical' => $posts->currentPage() > 1 ? $posts->url($posts->currentPage()) : route('blog.index'),
        ]);
    }

    /**
     * Trang đợt nấu công khai: thông tin tổng hợp ở trên, bài viết của đợt ở dưới (phân trang).
     * Chỉ đợt đang diễn ra hoặc đã có bài đăng (CookingBatch::scopePubliclyVisible); còn lại 404.
     * Không hiện người điều phối, địa điểm, công việc, nguyên liệu, chi phí (docs/SECURITY_PRIVACY.md).
     *
     * URL dùng mã công khai ngẫu nhiên (public_id), không dùng ID tăng dần.
     *
     * @param  Request  $request  yêu cầu của người xem
     * @param  CookingBatch  $batch  đợt nấu (tìm theo public_id)
     */
    public function batch(Request $request, CookingBatch $batch): View
    {
        $model = CookingBatch::publiclyVisible()->whereKey($batch->getKey())->firstOrFail();
        $page = max(1, $request->integer('page', 1));

        $posts = PublicPostCache::remember('batch:'.$model->id.':'.$page, fn () => $model->posts()->published()
            ->with(['tags', 'batch:id,public_id,name,starts_on', 'cover', 'author:id,name'])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page))
            ->withPath(route('blog.batch', $model));

        return view('public.batches.show', [
            'batch' => $model,
            'posts' => $posts,
            'canonical' => $posts->currentPage() > 1 ? $posts->url($posts->currentPage()) : route('blog.batch', $model),
        ]);
    }

    /**
     * Chi tiết bài viết. Bài chưa đăng: khách nhận 404, người quản lý được xem trước (noindex).
     * Lượt xem được đếm qua PostViewCounter (chống tăng ảo).
     *
     * @param  Request  $request  yêu cầu của người xem
     * @param  Post  $post  bài viết (theo slug)
     * @param  PostViewCounter  $views  bộ đếm lượt xem
     */
    public function show(Request $request, Post $post, PostViewCounter $views): View
    {
        $preview = ! $post->isPublished();
        abort_if($preview && ! $request->user()?->can('update', $post), 404);

        // Dòng bài viết luôn lấy mới (lượt xem, trạng thái); phần quan hệ và bài liên quan lấy từ bộ nhớ đệm.
        // Bản xem trước (chưa đăng) không lưu đệm.
        $load = fn () => $this->showData($post);
        $data = $preview ? $load() : PublicPostCache::remember('post:v2:'.$post->id, $load);
        $post->setRelations($data['relations']);
        $views->record($post, $request);

        return view('public.posts.show', [
            'post' => $post,
            'preview' => $preview,
            'related' => $data['related'],
            'batchPostCount' => $data['batchPostCount'],
        ]);
    }

    /**
     * Dữ liệu (lưu đệm được) của trang chi tiết: quan hệ của bài, bài liên quan (ưu tiên cùng đợt), số bài cùng đợt.
     *
     * @param  Post  $post  bài viết
     * @return array{relations: array<string, mixed>, related: Collection<int, Post>, batchPostCount: int}
     */
    private function showData(Post $post): array
    {
        // Bản riêng để lưu đệm (chỉ chạy khi bộ nhớ đệm trống).
        $loaded = Post::with(['tags', 'batch', 'cover', 'gallery', 'author:id,name'])->findOrFail($post->id);

        $related = Post::published()
            ->with('cover')
            ->whereKeyNot($post->id)
            ->when($post->cooking_batch_id, fn ($q) => $q->orderByRaw('cooking_batch_id = ? desc', [$post->cooking_batch_id]))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'published_at', 'cooking_batch_id']);

        return [
            'relations' => $loaded->getRelations(),
            'related' => $related,
            'batchPostCount' => $loaded->batch ? $loaded->batch->posts()->published()->count() : 0,
        ];
    }

    /**
     * Sơ đồ trang (sitemap.xml) cho công cụ tìm kiếm: danh sách bài đã đăng.
     */
    public function sitemap(): Response
    {
        $posts = Post::published()->orderByDesc('published_at')->get(['slug', 'updated_at', 'published_at']);
        $batches = CookingBatch::publiclyVisible()->orderByDesc('starts_on')->get(['id', 'public_id', 'updated_at']);
        $contact = route('contact.create');

        return response()
            ->view('public.sitemap', compact('posts', 'batches', 'contact'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
