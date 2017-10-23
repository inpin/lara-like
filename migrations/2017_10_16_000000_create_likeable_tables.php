<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLikeableTables extends Migration
{
    public function up()
    {
        Schema::create('laralike_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('type')->default('like');
            $table->morphs('likeable');
            $table->unsignedInteger('user_id');
            $table->unique(['likeable_id', 'likeable_type', 'user_id', 'type'], 'likeable_likes_unique');
        });

        Schema::create('laralike_like_counters', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->default('like');
            $table->morphs('likeable');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['likeable_id', 'likeable_type', 'type'], 'likeable_counts');
        });

        Schema::table('laralike_likes', function (Blueprint $table) {
            $table->foreign('user_id', 'laralike_likes_ibfk_1')
                ->references('id')
                ->on('users')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');
        });
    }

    public function down()
    {
        Schema::table('laralike_likes', function (Blueprint $table) {
            $table->dropForeign('laralike_likes_ibfk_1');
        });

        Schema::drop('laralike_likes');
        Schema::drop('laralike_like_counters');
    }
}
