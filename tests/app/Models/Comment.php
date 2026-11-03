<?php

declare(strict_types=1);

namespace Test\app\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Test\app\Factories\CommentFactory;

/**
 * @property int $id
 * @property string $content
 * @property DateTimeInterface $created_at
 * @property DateTimeInterface $updated_at`
 *
 * @property-read User $user
 * @property-read Post $post
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected static function newFactory(): CommentFactory
    {
        return new CommentFactory();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
