<?php

declare(strict_types=1);

namespace Test\Feature\GuessedResources\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Test\app\Models\Comment;
use Test\app\Models\Post;

/**
 * A post whose `writer` is a model rendered with the resource of its `#[UseResource]`.
 */
final class Article extends Post
{
    protected $table = 'posts';

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * @return BelongsTo<Writer, $this>
     */
    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class, 'user_id');
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Unrendered, $this>
     */
    public function unrendered(): BelongsTo
    {
        return $this->belongsTo(Unrendered::class, 'user_id');
    }

    /**
     * @return BelongsTo<PlainUser, $this>
     */
    public function plain(): BelongsTo
    {
        return $this->belongsTo(PlainUser::class, 'user_id');
    }

    public function notARelation(): string
    {
        return 'title';
    }
}
