<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityComment>
 */
class ActivityCommentFactory extends Factory
{
    protected $model = ActivityComment::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->sentence(),
        ];
    }
}
