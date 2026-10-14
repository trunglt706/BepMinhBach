<?php

namespace App\Models;

use App\Enums\ContactTopic;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Liên hệ từ khách. Chứa thông tin cá nhân (tên, email/SĐT) nên chỉ điều phối viên trở lên xem được;
 * xóa khi đã xử lý xong nếu không cần giữ lại.
 *
 * @property ContactTopic $topic
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['topic', 'name', 'contact', 'message', 'client_hash', 'sent_on'];

    /** @var list<string> không bao giờ đưa mã băm client ra JSON/mảng */
    protected $hidden = ['client_hash'];

    /**
     * Ép kiểu thuộc tính.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'sent_on' => 'date',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Người đã đánh dấu xử lý.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Chỉ liên hệ chưa xử lý.
     *
     * @param  Builder<ContactMessage>  $query
     */
    public function scopeUnhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }

    /**
     * Mã nhận diện client (không lưu IP thô): HMAC(IP) với APP_KEY.
     * Theo IP (không theo trình duyệt) để đổi trình duyệt/xóa cookie không vượt được giới hạn 1 liên hệ / ngày.
     *
     * @param  Request  $request  yêu cầu gửi liên hệ
     */
    public static function clientHash(Request $request): string
    {
        return hash_hmac('sha256', 'contact|'.$request->ip(), (string) config('app.key'));
    }

    /**
     * Client này (hoặc email/SĐT này) đã gửi liên hệ trong hôm nay (giờ Việt Nam) chưa.
     *
     * @param  string  $clientHash  mã client
     * @param  string|null  $contact  email/SĐT đã chuẩn hóa (nếu có)
     */
    public static function sentToday(string $clientHash, ?string $contact = null): bool
    {
        return static::query()
            ->whereDate('sent_on', today())
            ->where(fn (Builder $q) => $q->where('client_hash', $clientHash)
                ->when($contact, fn (Builder $q) => $q->orWhere('contact', $contact)))
            ->exists();
    }
}
