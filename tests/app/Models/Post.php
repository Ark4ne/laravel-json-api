<?php

declare(strict_types=1);

namespace Test\app\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Test\app\Enums\State;
use Test\app\Factories\PostFactory;

/**
 * @property int $id
 * @property State $state
 * @property string $title
 * @property string $content
 * @property bool $is_public
 * @property DateTimeInterface $created_at
 * @property DateTimeInterface $updated_at
 *
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $casts = [
        'state' => State::class,
        'is_public' => 'boolean',
    ];

    protected static function newFactory(): PostFactory
    {
        return new PostFactory();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
