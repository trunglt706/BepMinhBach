<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Thẻ (tag) của bài viết; trùng nhau theo slug ("Cơm Chay" = "cơm chay"). */
class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * Các bài viết gắn thẻ này.
     *
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Tìm hoặc tạo thẻ theo tên. "Bữa trưa, cơm chay ,  Bữa Trưa" → 2 thẻ
     * (trùng nhau khi cùng slug, không phân biệt hoa thường/dấu cách). Tên rỗng sau khi tạo slug bị bỏ.
     *
     * @param  list<string>  $names  tên thẻ người dùng nhập
     * @return Collection<int, Tag>
     */
    public static function findOrCreateMany(array $names): Collection
    {
        return collect($names)
            ->map(fn ($name) => ['name' => $name, 'slug' => Str::slug($name)])
            ->filter(fn ($t) => $t['slug'] !== '')
            ->unique('slug')
            ->map(fn ($t) => static::firstOrCreate(['slug' => $t['slug']], ['name' => $t['name']]))
            ->values();
    }

    /**
     * Xóa thẻ không còn bài viết nào dùng (để bộ lọc công khai không có thẻ rỗng).
     */
    public static function pruneUnused(): void
    {
        static::doesntHave('posts')->delete();
    }
}
