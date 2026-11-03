<?php

declare(strict_types=1);

namespace Test\Unit\Loading;

use Ark4ne\JsonApi\Loading\LoadPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\TestCase;

class LoadPlanTest extends TestCase
{
    public function test_an_empty_plan_has_neither_relations_nor_nested_plans(): void
    {
        self::assertTrue((new LoadPlan())->isEmpty());
        self::assertFalse((new LoadPlan(['user' => null]))->isEmpty());
        self::assertFalse((new LoadPlan(nested: ['user' => [new LoadPlan()]]))->isEmpty());
    }

    public function test_what_is_not_an_eloquent_model_is_left_as_it_is(): void
    {
        $model = (object) ['user' => null];

        (new LoadPlan(['user' => null], nested: ['user' => [new LoadPlan(['posts' => null])]]))->apply([$model, ['user' => 1]]);

        self::assertEquals((object) ['user' => null], $model);
    }

    public function test_a_source_that_is_not_a_relation_is_not_loaded(): void
    {
        $post = new Post();

        // Nothing to query: `title` is an attribute, `user` is already loaded.
        (new LoadPlan(['title' => null, 'user' => null], ['title', 'user']))->apply([$post->setRelation('user', null)]);

        self::assertFalse($post->relationLoaded('title'));
    }

    public function test_the_related_models_are_those_of_the_loaded_relations_each_once(): void
    {
        $user = new User();
        $comment = new Comment();
        $posts = [
            (new Post())->setRelation('user', $user)->setRelation('comments', new Collection([$comment])),
            (new Post())->setRelation('user', $user)->setRelation('comments', new Collection([$comment])),
            (new Post())->setRelation('user', null),
            new Post(),
        ];

        self::assertSame([$user], LoadPlan::related($posts, 'user'));
        self::assertSame([$comment], LoadPlan::related($posts, 'comments'));
    }

    public function test_a_plan_for_a_resource_of_a_map_leaves_the_models_the_map_gives_another_resource_for(): void
    {
        $comment = new Comment();
        $post = (new Post())->setRelation('user', null);

        // Only the comment would be loaded: the post is skipped, and its `user` is already loaded anyway.
        (new LoadPlan(['user' => null], ['user'], morphs: [Post::class => 'post', Comment::class => 'comment'], resource: 'post'))
            ->apply([$comment, $post]);

        self::assertFalse($comment->relationLoaded('user'));
    }

    public function test_a_model_of_several_classes_of_a_map_is_planned_with_the_first_one_only(): void
    {
        $post = new Post();

        // A post is also a model: the map gives it `post`, the plan for `model` leaves it as it is.
        (new LoadPlan(['user' => null], ['user'], morphs: [Post::class => 'post', Model::class => 'model'], resource: 'model'))
            ->apply([$post]);

        self::assertFalse($post->relationLoaded('user'));
    }
}
