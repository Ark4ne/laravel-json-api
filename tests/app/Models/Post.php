<?php

namespace Test\app\Models;

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
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 *
 * @property-read User $user
 * @property-read Comment[]|\Illuminate\Support\Collection<Comment> $comments
 */
class Post extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected $casts = [
        'state' => State::class,
    ];

    protected static function newFactory(): PostFactory
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
