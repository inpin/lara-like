<?php

namespace Inpin\LaraLike;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User;

trait Likeable
{
    /**
     * Boot the soft taggable trait for a model.
     *
     * @return void
     */
    public static function bootLikeable()
    {
        if (static::removeLikesOnDelete()) {
            static::deleting(function ($model) {
                /** @var Likeable $model */
                $model->removeLikes();
            });
        }
    }

    /**
     * Fetch records that are liked by a given user.
     * Ex: Book::whereLikedBy(123)->get();
     * @param Builder $query
     * @param User|string|null $guard
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function scopeWhereLikedBy($query, $guard = null, $type = 'like')
    {
        if (!($guard instanceof User)) {
            $guard = $this->loggedInUser($guard);
        }

        return $query->whereHas('likes', function ($query) use ($type, $guard) {
            /** @var Builder $query */
            $query->where('user_id', '=', $guard->id)->where('type', $type);
        });
    }


    /**
     * Populate the $model->likes attribute
     */
    public function getLikeCountAttribute()
    {
        return $this->likeCounter()->where('type', 'like')->exists()
            ? $this->likeCounter()->where('type', 'like')->first()->count
            : 0;
    }

    /**
     * Collection of the likes on this record
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Counter is a record that stores the total likes for the
     * morphed record
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function likeCounter()
    {
        return $this->morphOne(LikeCounter::class, 'likeable');
    }

    /**
     * Add a like for this record by the given user by given type.
     *
     * @param string|User|integer $guard - The guard of current user, If instance of Illuminate\Foundation\Auth\User use as user
     * @param $type - If given store as type
     */
    public function like($guard = null, $type = 'like')
    {
        if (!($guard instanceof User) && (is_string($guard) || is_null($guard))) {
            $guard = $this->loggedInUser($guard);
        }

        if ($guard instanceof User) {
            if ($this->likes()
                ->where('user_id', '=', $guard->id)
                ->where('type', $type)
                ->exists()) {
                return;
            }

            $like = new Like();
            $like->user_id = $guard->id;
            $like->type = $type;
            $this->likes()->save($like);
        }

        $this->incrementLikeCount($type);
    }

    /**
     * Remove a like from this record for the given user and given type.
     *
     * @param string|User|integer $guard - The guard of current user, If instance of Illuminate\Foundation\Auth\User use as user
     * @param $type - If given store as type
     */
    public function unlike($guard = null, $type = 'like')
    {
        if (!($guard instanceof User) && (is_string($guard) || is_null($guard))) {
            $guard = $this->loggedInUser($guard);
        }

        if ($guard instanceof User) {
            if (!$this->likes()
                ->where('user_id', '=', $guard->id)
                ->where('type', $type)
                ->exists()) {
                return;
            }

            $this->likes()
                ->where('user_id', '=', $guard->id)
                ->where('type', $type)
                ->first()
                ->delete();
        }

        $this->decrementLikeCount($type);
    }

    /**
     * Has the currently logged in user already "liked" the current object
     *
     * @param string $guard - The guard of current user, If instance of Illuminate\Foundation\Auth\User use as user
     * @param $type - If given store as type
     * @return boolean
     */
    public function liked($guard = null, $type = 'like')
    {
        if (!($guard instanceof User)) {
            $guard = $this->loggedInUser($guard);
        }

        return (bool)$this->likes()
            ->where('user_id', '=', $guard->id)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Private. Increment the total like count stored in the counter
     *
     * @param string $type
     */
    private function incrementLikeCount($type = 'like')
    {
        $counter = $this->likeCounter()->where('type', $type)->first();

        if ($counter) {
            $counter->count++;
            $counter->save();
        } else {
            $counter = new LikeCounter;
            $counter->count = 1;
            $counter->type = $type;
            $this->likeCounter()->save($counter);
        }
    }

    /**
     * Private. Decrement the total like count stored in the counter
     *
     * @param string $type
     */
    private function decrementLikeCount($type = 'like')
    {
        $counter = $this->likeCounter()->where('type', $type)->first();

        if ($counter) {
            $counter->count--;
            if ($counter->count) {
                $counter->save();
            } else {
                $counter->delete();
            }
        }
    }

    /**
     * Fetch the primary ID of the currently logged in user
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function loggedInUser($guard)
    {
        return auth($guard)->user();
    }

    /**
     * Did the currently logged in user like this model
     * Example : if($book->liked) { }
     * @return boolean
     */
    public function getLikedAttribute()
    {
        return $this->liked();
    }

    /**
     * Should remove likes on model row delete (defaults to true)
     * public static removeLikesOnDelete = false;
     */
    public static function removeLikesOnDelete()
    {
        return isset(static::$removeLikesOnDelete)
            ? static::$removeLikesOnDelete
            : true;
    }

    /**
     * Delete likes related to the current record
     * @param string $type
     */
    public function removeLikes($type = 'like')
    {
        Like::query()
            ->where('likeable_type', $this->getMorphClass())
            ->where('likeable_id', $this->id)
            ->where('type', $type)
            ->delete();

        LikeCounter::query()
            ->where('likeable_type', $this->getMorphClass())
            ->where('likeable_id', $this->id)
            ->where('type', $type)
            ->delete();
    }
}