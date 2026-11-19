<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    /** Emails are stored lowercase, so lookups by email are exact. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function isSuperadmin(): bool
    {
        return $this->role === Role::Superadmin;
    }

    public function starredIssues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_stars')->withTimestamps();
    }

    public function watchedIssues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_watches')->withTimestamps();
    }

    public function hasStarred(Issue $issue): bool
    {
        return IssueStar::query()->where(['user_id' => $this->id, 'issue_id' => $issue->id])->exists();
    }

    public function isWatching(Issue $issue): bool
    {
        return IssueWatch::query()->where(['user_id' => $this->id, 'issue_id' => $issue->id])->exists();
    }

    public static function superadminExists(): bool
    {
        return static::query()->where('role', Role::Superadmin)->exists();
    }
}
