<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feedback about the site. The submitter follows its status through a private link (token). */
class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['user_id', 'name', 'email', 'type', 'message', 'status', 'public_note', 'token'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['type' => FeedbackType::class, 'status' => FeedbackStatus::class];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusUrl(): string
    {
        return route('feedback.status', $this->token);
    }
}
