<?php

namespace Test\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Test\app\Factories\UserFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \DateTimeInterface $created_at
 * @property \DateTimeInterface $updated_at
 *
 * @property-read Post[]|\Illuminate\Support\Collection<Post> $posts
 * @property-read Comment[]|\Illuminate\Support\Collection<Comment> $comments
 */
class User extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return new UserFactory();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
