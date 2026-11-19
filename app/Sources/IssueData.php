<?php

namespace App\Sources;

use App\Enums\Severity;
use Carbon\CarbonImmutable;

/** One issue as a source mapper produced it, before it is stored. */
final readonly class IssueData
{
    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $packages
     * @param  list<string>  $references
     */
    public function __construct(
        public string $source,
        public string $externalId,
        public array $aliases,
        public string $title,
        public ?string $description,
        public Severity $severity,
        public ?float $cvssScore,
        public ?string $cvssVector,
        public array $packages,
        public array $references,
        public string $sourceUrl,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $modifiedAt,
        public ?CarbonImmutable $withdrawnAt,
        public int $languageId,
    ) {}
}
