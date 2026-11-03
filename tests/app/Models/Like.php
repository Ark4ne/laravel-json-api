<?php

declare(strict_types=1);

namespace Test\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A like of a post or a comment: a polymorphic relation.
 *
 * @property int $id
 * @property int $user_id
 *
 * @property-read User $user
 * @property-read Post|Comment $likeable
 */
class Like extends Model
{
    /** @var array<string> */
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}
