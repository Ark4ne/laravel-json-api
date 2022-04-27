<?php

namespace Test\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Test\app\Factories\CommentFactory;

/**
 * @property int $id
 * @property string $content
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at`
 *
 * @property-read User $user
 * @property-read Post $post
 */
class Comment extends Model
{
    use HasFactory;

    protected static function newFactory(): CommentFactory
    {
        return new CommentFactory();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
