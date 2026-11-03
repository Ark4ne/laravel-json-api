<?php

declare(strict_types=1);

/**
 * The schema and the data of the benchmarks (bin/benchmark/scenario.php, bin/benchmark/cold.php), on the default
 * connection of the booted application.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The same data for every implementation: 100 users, the posts, 3 comments per post.
 */
function benchmark_database(int $posts): void
{
    Schema::create('users', static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
    Schema::create('posts', static function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('content');
        $table->foreignId('user_id');
        $table->timestamps();
    });
    Schema::create('comments', static function (Blueprint $table): void {
        $table->id();
        $table->string('content');
        $table->foreignId('post_id');
        $table->foreignId('user_id');
        $table->timestamps();
    });

    $now = '2026-01-01 00:00:00';
    $users = 100;

    foreach (array_chunk(range(1, $users), 500) as $chunk) {
        DB::table('users')->insert(array_map(static fn(int $id) => [
            'id' => $id, 'name' => "User {$id}", 'email' => "user{$id}@example.com", 'created_at' => $now, 'updated_at' => $now,
        ], $chunk));
    }
    foreach (array_chunk(range(1, $posts), 500) as $chunk) {
        DB::table('posts')->insert(array_map(static fn(int $id) => [
            'id' => $id, 'title' => "Post {$id}", 'content' => str_repeat('lorem ipsum ', 10),
            'user_id' => $id % $users + 1, 'created_at' => $now, 'updated_at' => $now,
        ], $chunk));
        DB::table('comments')->insert(array_merge(...array_map(static fn(int $post) => array_map(static fn(int $n) => [
            'content' => "Comment {$n} on post {$post}", 'post_id' => $post,
            'user_id' => ($post * 7 + $n) % $users + 1, 'created_at' => $now, 'updated_at' => $now,
        ], [1, 2, 3]), $chunk)));
    }
}
