<?php

namespace App\Support\Issues;

use App\Enums\Severity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The issue filters shared by the public list, My issues and email links:
 * languages (slugs), minimum severity, period / custom from–to (display-timezone days), date field, search.
 */
class IssueQuery
{
    public const PERIODS = ['24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'custom' => 'Custom range'];

    public const DATE_FIELDS = ['published' => 'published_at', 'modified' => 'modified_at'];

    /**
     * @return array{languages: list<string>, severity: ?string, period: ?string, from: ?string, to: ?string, date_field: string, q: string}
     */
    public static function fromRequest(array $input): array
    {
        $languages = $input['languages'] ?? [];
        if (is_string($languages)) {
            $languages = explode(',', $languages);
        }

        $severity = Severity::tryFrom((string) ($input['severity'] ?? ''));

        return [
            'languages' => array_values(array_filter(array_map(fn ($l) => trim((string) $l), (array) $languages), fn ($l) => $l !== '')),
            'severity' => $severity && $severity !== Severity::Unknown ? $severity->value : null,
            'period' => array_key_exists((string) ($input['period'] ?? ''), self::PERIODS) ? (string) $input['period'] : null,
            'from' => self::validDate($input['from'] ?? null),
            'to' => self::validDate($input['to'] ?? null),
            'date_field' => array_key_exists((string) ($input['date_field'] ?? ''), self::DATE_FIELDS) ? (string) $input['date_field'] : 'published',
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 200),
        ];
    }

    public static function apply(Builder $query, array $filters, ?User $user = null): Builder
    {
        $f = self::fromRequest($filters);
        $column = self::DATE_FIELDS[$f['date_field']];

        if ($f['languages'] !== []) {
            $query->forLanguages($f['languages']);
        }
        if ($f['severity']) {
            $query->minSeverity(Severity::from($f['severity']));
        }

        [$from, $to] = self::range($f);
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<', $to);
        }

        if ($f['q'] !== '') {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('external_id', 'ilike', $like)
                ->orWhere('title', 'ilike', $like)
                ->orWhereRaw('aliases::text ilike ?', [$like])
                ->orWhereRaw('packages::text ilike ?', [$like]));
        }

        return $query->orderByDesc($column)->orderByDesc('issues.id');
    }

    /** @return array{?CarbonImmutable, ?CarbonImmutable} UTC bounds, the upper one exclusive */
    private static function range(array $f): array
    {
        $now = CarbonImmutable::now();
        $tz = config('app.display_timezone');

        return match ($f['period']) {
            '24h' => [$now->subDay(), null],
            '7d' => [$now->subDays(7), null],
            '30d' => [$now->subDays(30), null],
            '90d' => [$now->subDays(90), null],
            'custom' => [
                $f['from'] ? CarbonImmutable::parse($f['from'], $tz)->startOfDay()->utc() : null,
                $f['to'] ? CarbonImmutable::parse($f['to'], $tz)->addDay()->startOfDay()->utc() : null,
            ],
            default => [null, null],
        };
    }

    private static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y) ? $value : null;
    }
}
