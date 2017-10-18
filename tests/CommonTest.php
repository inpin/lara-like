<?php

namespace Tests;

use Faker\Generator;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inpin\LaraLike\Likeable;
use Inpin\LaraLike\LikeCounter;

class CommonTest extends TestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    public function setUp()
    {
        parent::setUp();

        Eloquent::unguard();

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--realpath' => realpath(__DIR__ . '/../migrations'),
        ]);

        $this->loadLaravelMigrations(['--database' => 'testbench']);

        $this->faker = resolve(Generator::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::create('books', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function tearDown()
    {
        Schema::drop('books');
    }

    /**
     * Create a random user with fake information
     *
     * @return User|#$this|Eloquent
     */
    public function createRandomUser()
    {
        return User::query()->create([
            'email' => $this->faker->unique()->email,
            'name' => $this->faker->name,
            'password' => Hash::make($this->faker->password)
        ]);
    }

    /**
     * Create a random stub with fake information
     *
     * @return Stub|$this|Eloquent
     */
    public function createRandomStub()
    {
        return Stub::query()->create([
            'name' => $this->faker->word,
        ]);
    }

    public function testBasicLike()
    {
        $user = $this->createRandomUser();
        $stub = $this->createRandomStub();
        $this->actingAs($user);
        /** @var Stub $stub */

        $stub->like();

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertEquals(1, $stub->likeCount);
    }

    public function testBasicLikeWithType()
    {
        $user = $this->createRandomUser();
        $stub = $this->createRandomStub();
        $this->actingAs($user);
        /** @var Stub $stub */

        $stub->like(null, 'bookmark');

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'bookmark',
        ]);

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'count' => 1,
            'type' => 'bookmark',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testLikeWithDifferentTypes()
    {
        $user = $this->createRandomUser();
        $stub = $this->createRandomStub();
        $this->actingAs($user);
        /** @var Stub $stub */

        $stub->like();
        $stub->like(null, 'bookmark');

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'bookmark',
        ]);

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'type' => 'bookmark',
        ]);

    }

    public function testLikeWithoutUser()
    {
        /** @var Stub $stub */
        $stub = $this->createRandomStub();

        $stub->like(0);

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'type' => 'like',
        ]);

        $this->assertEquals(1, $stub->likeCount);
    }

    public function testLikeWithoutUserWithType()
    {
        /** @var Stub $stub */
        $stub = $this->createRandomStub();

        $stub->like(0, 'bookmark');

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'type' => 'bookmark',
        ]);

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'count' => 1,
            'type' => 'bookmark',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testMultipleLikes()
    {
        $stub = $this->createRandomStub();

        $users = [
            $this->createRandomUser(),
            $this->createRandomUser(),
            $this->createRandomUser(),
            $this->createRandomUser()
        ];

        foreach ($users as $user) {
            $stub->like($user);
        }

        foreach ($users as $user) {
            $this->assertDatabaseHas('laralike_likes', [
                'likeable_type' => $stub->getMorphClass(),
                'likeable_id' => $stub->id,
                'user_id' => $user->id,
                'type' => 'like',
            ]);
        }
        $this->assertEquals(4, $stub->likeCount);
    }

    public function testMultipleLikesWithType()
    {
        $stub = $this->createRandomStub();

        $users = [
            $this->createRandomUser(),
            $this->createRandomUser(),
            $this->createRandomUser(),
            $this->createRandomUser()
        ];

        foreach ($users as $user) {
            $stub->like($user, 'bookmark');
        }

        foreach ($users as $user) {
            $this->assertDatabaseHas('laralike_likes', [
                'likeable_type' => $stub->getMorphClass(),
                'likeable_id' => $stub->id,
                'user_id' => $user->id,
                'type' => 'bookmark',
            ]);
        }

        $this->assertDatabaseHas('laralike_like_counters', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'count' => 4,
            'type' => 'bookmark',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testUnlike()
    {
        $stub = $this->createRandomStub();
        $this->actingAs($this->createRandomUser());

        $stub->unlike();

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testLikeUnlike()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();

        $this->actingAs($user);
        $stub->like();
        $stub->unlike();

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testLikeUnlikeSpecificUser()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();

        $stub->like($user);
        $stub->unlike($user);

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testLikeUnlikeSpecificUserWithType()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();

        $stub->like($user, 'bookmark');
        $stub->unlike($user, 'bookmark');

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'bookmark',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testLikeUnlikeWithType()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();
        $this->actingAs($user);

        $stub->like(null, 'bookmark');
        $stub->unlike(null, 'bookmark');

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'bookmark',
        ]);

        $this->assertEquals(0, $stub->likeCount);
    }

    public function testDoubleLikeOneUnlike()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();
        $randomUser = $this->createRandomUser();
        $this->actingAs($user);

        $stub->like();
        $stub->like($randomUser);
        $stub->unlike();


        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $randomUser->id,
            'type' => 'like',
        ]);

        $this->assertEquals(1, $stub->likeCount);
    }

    public function testDoubleLikeOneUnlikeSpecificUser()
    {
        $stub = $this->createRandomStub();
        $user = $this->createRandomUser();
        $randomUser = $this->createRandomUser();
        $this->actingAs($user);

        $stub->like();
        $stub->like($randomUser);
        $stub->unlike($randomUser);


        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $randomUser->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseHas('laralike_likes', [
            'likeable_type' => $stub->getMorphClass(),
            'likeable_id' => $stub->id,
            'user_id' => $user->id,
            'type' => 'like',
        ]);

        $this->assertEquals(1, $stub->likeCount);
    }

    public function testWhereLikedBy()
    {
        $stubs = [
            $this->createRandomStub(),
            $this->createRandomStub(),
            $this->createRandomStub(),
        ];
        $user = $this->createRandomUser();
        $randomUser = $this->createRandomUser();

        /** @var Stub $stub */
        foreach ($stubs as $stub) {
            $stub->like($user);
        }

        $likedStubes = Stub::whereLikedBy($user)->get();
        $shouldBeEmpty = Stub::whereLikedBy($randomUser)->get();

        $this->assertEquals(3, $likedStubes->count());
        $this->assertEmpty($shouldBeEmpty);
    }

    public function testWhereLikedBySpecificType()
    {
        $stubs = [
            $this->createRandomStub(),
            $this->createRandomStub(),
            $this->createRandomStub(),
        ];
        $user = $this->createRandomUser();
        $randomUser = $this->createRandomUser();

        /** @var Stub $stub */
        foreach ($stubs as $stub) {
            $stub->like($user, 'bookmark');
        }

        $likedStubes = Stub::whereLikedBy($user, 'bookmark')->get();
        $shouldBeEmpty = Stub::whereLikedBy($randomUser, 'bookmark')->get();

        $this->assertEquals(3, $likedStubes->count());
        $this->assertEmpty($shouldBeEmpty);
    }

    public function testLikesGetDeletesWithRecord()
    {
        $stub1 = $this->createRandomStub();
        $stub2 = $this->createRandomStub();

        $stub1->like($this->createRandomUser());
        $stub1->like($this->createRandomUser());
        $stub1->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());

        $stub1->delete();

        $this->assertDatabaseMissing('laralike_likes', [
            'likeable_type' => $stub1->getMorphClass(),
            'likeable_id' => $stub1->id,
            'type' => 'like',
        ]);

        $this->assertDatabaseMissing('laralike_like_counters', [
            'likeable_type' => $stub1->getMorphClass(),
            'likeable_id' => $stub1->id,
            'type' => 'like',
        ]);

        $results = LikeCounter::all();
        $this->assertEquals(1, $results->count());
    }

    public function testRebuildTest()
    {
        $stub1 = $this->createRandomStub();
        $stub2 = $this->createRandomStub();

        $stub1->like($this->createRandomUser());
        $stub1->like($this->createRandomUser());
        $stub1->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());
        $stub2->like($this->createRandomUser());

        LikeCounter::truncate();

        LikeCounter::rebuild($stub1->getMorphClass());

        $results = LikeCounter::all();
        $this->assertEquals(2, $results->count());
    }
}

class Stub extends Eloquent
{
    use Likeable;

    protected $morphClass = 'Stub';

    protected $connection = 'testbench';

    public $table = 'books';
}
