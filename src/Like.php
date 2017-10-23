<?php

namespace Inpin\LaraLike;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    protected $table = 'laralike_likes';
    public $timestamps = true;
    protected $fillable = ['likeable_id', 'likeable_type', 'user_id'];
    public function likeable()
    {
        return $this->morphTo();
    }
}
