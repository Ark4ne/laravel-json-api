<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function tables()
    {
        return [
            'users' => function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('password');
                $table->timestamps();
            },
            'posts' => function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('content');
                $table->timestamps();
                $table->foreignId('user_id')->constrained('users');
            },
            'comments' => function (Blueprint $table) {
                $table->id();
                $table->string('content');
                $table->foreignId('post_id')->constrained('posts');
                $table->foreignId('user_id')->constrained('users');
                $table->timestamps();
            },
        ];
    }

    public function up()
    {
        foreach ($this->tables() as $table => $blueprint) {
            Schema::create($table, $blueprint);
        }
    }

    public function down()
    {
        foreach (array_reverse(array_keys($this->tables())) as $table) {
            Schema::drop($table);
        }
    }
};
