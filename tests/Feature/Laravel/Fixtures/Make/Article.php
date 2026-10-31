<?php

declare(strict_types=1);

namespace Test\Feature\Laravel\Fixtures\Make;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Test\app\Enums\State;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;

/**
 * Every kind of cast and relation `make:jsonapi-resource` reads; the relations are never called.
 */
final class Article extends Model
{
    /** @var list<string> */
    protected $fillable = ['title', 'state', 'body'];

    /** @var list<string> */
    protected $hidden = ['secret'];

    /** @var array<string, string> */
    protected $casts = [
        'state' => State::class,
        'views' => 'integer',
        'rating' => 'float',
        'price' => 'decimal:2',
        'published' => 'boolean',
        'published_on' => 'date',
        'reviewed_at' => 'datetime:Y-m-d H:i',
        'tags' => 'array',
        'options' => 'encrypted:array',
        'secret' => 'string',
        'password' => 'hashed',
        'extra' => 'object',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Post, $this> */
    public function cover(): HasOne
    {
        return $this->hasOne(Post::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function summary(): string
    {
        return '';
    }
}
