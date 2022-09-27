<?php

namespace Test\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router)
    {
        include __DIR__ . '/../app/routes.php';
    }

    protected function afterRefreshingDatabase()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../app/migrations.php');

        self::loadSeed();
    }

    private static function loadSeed(): void
    {
        static $seed;

        DB::beginTransaction();

        if (isset($seed)) {
            foreach ($seed as $query) {
                DB::statement($query['query'], $query['bindings']);
            }
        } else {
            DB::enableQueryLog();
            self::dataSeed();
            $seed = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        DB::commit();
    }

    private static function dataSeed(): void
    {
        $users = User::factory()->count(10)->create();

        foreach ($users as $udx => $user) {
            $posts = Post::factory()->for($user)->count(3)->create();
            foreach ($posts as $post) {
                foreach ($users->except($udx)->random(5) as $u) {
                    Comment::factory()->for($post)->for($u)->create();
                }
            }
        }
    }
}
