<?php

namespace Test\Feature\Post;

use Ark4ne\JsonApi\Support\Arr;
use DateTimeInterface;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Feature\FeatureTestCase;

/**
 * Tests the behaviour of deep includes with shared resources across multiple include paths.
 *
 * Scenario: GET /post/{id}?include=user.posts,comments.user
 *
 * - post->user        : User A (post author)      → user.posts requested → User A's posts included
 * - post->comments    : [commentByB, commentByA]
 *   - commentByB->user: User B (comment only)     → NO user.posts        → User B's posts NOT included
 *   - commentByA->user: User A (same as author)   → NO user.posts        → deduplicated with the first occurrence
 *
 * Key assertions:
 * 1. User A appears exactly ONCE in `included` (deduplication).
 * 2. User A's posts ARE in `included` (fetched via the user.posts path).
 * 3. User B appears in `included` (fetched via comments.user path).
 * 4. User B's posts are NOT in `included` (comments.user.posts was not requested).
 */
class DeepIncludeDeduplicationTest extends FeatureTestCase
{
    public function testDeepIncludeDeduplication(): void
    {
        $postAuthor    = User::factory()->create(); // User A: author of the post AND one comment
        $commentAuthor = User::factory()->create(); // User B: only appears via comments

        $post                = Post::factory()->for($postAuthor)->create();    // one of postAuthor's posts
        $extraPost           = Post::factory()->for($postAuthor)->create();    // another post of postAuthor
        $commentAuthorPost   = Post::factory()->for($commentAuthor)->create(); // post of commentAuthor — must NOT appear in included

        // Refresh to get DB defaults (e.g. state column default)
        $post->refresh();
        $extraPost->refresh();
        $commentAuthorPost->refresh();

        $commentByB = Comment::factory()->for($post)->for($commentAuthor)->create(); // User B comments
        $commentByA = Comment::factory()->for($post)->for($postAuthor)->create();    // User A comments → shared user, tests dedup

        $response = $this->get("post/{$post->id}?include=user.posts,comments.user.comments");

        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'attributes' => ['state', 'title'],
                'relationships' => [
                    'user' => ['data' => ['type', 'id'], 'links' => ['self']],
                    'comments' => ['data' => [['type', 'id']], 'links' => ['self', 'related'], 'meta' => ['total']],
                ],
                'meta' => ['created_at', 'updated_at'],
            ],
            'included' => [
                '*' => [
                    'id',
                    'type',
                    'attributes',
                    'relationships',
                    'meta',
                ],
            ],
        ]);

        $response->assertJsonPath("included.0.type", 'user');
        $response->assertJsonPath("included.0.id", $postAuthor->id);
        $response->assertJsonPath("included.0.relationships.comments.data.0.id", $commentByA->id);

        // Explicit sub-assertions to document intent
        $included = $response->json('included');

        $postAuthorInIncluded = collect($included)
            ->filter(fn($item) => $item['type'] === 'user' && $item['id'] == $postAuthor->id)
            ->count();
        $this->assertSame(1, $postAuthorInIncluded, 'Post author must appear exactly once (deduplication)');

        $usersInIncluded = collect($included)->filter(fn($item) => $item['type'] === 'user')->pluck('id')->all();
        $this->assertCount(2, $usersInIncluded, 'There should be exactly 2 users in included');
        $this->assertContains($postAuthor->id, $usersInIncluded, 'Post author must be included');
        $this->assertContains($commentAuthor->id, $usersInIncluded, 'Comment author must be included');
    }
}
