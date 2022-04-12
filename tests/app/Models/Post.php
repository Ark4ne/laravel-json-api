<?php

namespace Test\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Test\app\Factories\PostFactory;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 *
 * @property-read User $user
 * @property-read Comment[]|\Illuminate\Support\Collection<Comment> $comments
 */
class Post extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return new PostFactory();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
