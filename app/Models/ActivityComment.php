<?php

namespace App\Models;

use App\Observers\ActivityCommentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ActivityCommentObserver::class)]
class ActivityComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'user_id',
        'comment',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
