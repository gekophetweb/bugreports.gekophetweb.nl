<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Issues\HiddenPackages;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'email_verified_at', 'hidden_packages', 'advance_warnings', 'used_packages'])]
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
            'hidden_packages' => 'array',
            'used_packages' => 'array',
            'advance_warnings' => 'boolean',
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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

    /** @return list<string> package names and vendor patterns this user does not want to see */
    public function hiddenPackages(): array
    {
        return array_values($this->hidden_packages ?? []);
    }

    /** Adds a package or vendor pattern; returns false when it is invalid. */
    public function hidePackage(string $pattern): bool
    {
        $pattern = HiddenPackages::normalize($pattern);
        if (! HiddenPackages::isValid($pattern)) {
            return false;
        }
        $hidden = $this->hiddenPackages();
        if (! in_array($pattern, $hidden, true) && count($hidden) < HiddenPackages::MAX) {
            $this->update(['hidden_packages' => [...$hidden, $pattern]]);
        }

        return true;
    }

    public function unhidePackage(string $pattern): void
    {
        $pattern = HiddenPackages::normalize($pattern);
        $this->update(['hidden_packages' => array_values(array_filter($this->hiddenPackages(), fn ($p) => $p !== $pattern))]);
    }

    /** @return list<string> packages this user uses: their issues show although packages are hidden by default */
    public function usedPackages(): array
    {
        return array_values($this->used_packages ?? []);
    }

    public function usePackage(string $pattern): bool
    {
        $pattern = HiddenPackages::normalize($pattern);
        if (! HiddenPackages::isValid($pattern)) {
            return false;
        }
        $this->update(['used_packages' => HiddenPackages::clean([...$this->usedPackages(), $pattern])]);

        return true;
    }

    public function unusePackage(string $pattern): void
    {
        $pattern = HiddenPackages::normalize($pattern);
        $this->update(['used_packages' => array_values(array_filter($this->usedPackages(), fn ($p) => $p !== $pattern))]);
    }

    public static function superadminExists(): bool
    {
        return static::query()->where('role', Role::Superadmin)->exists();
    }
}
