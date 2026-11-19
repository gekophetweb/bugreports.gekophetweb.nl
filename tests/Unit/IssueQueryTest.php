<?php

use App\Support\Issues\IssueQuery;

it('normalises filters and drops invalid values', function () {
    expect(IssueQuery::fromRequest([
        'languages' => ['php', '', 'go'], 'severity' => 'high', 'period' => 'custom',
        'from' => '2026-11-01', 'to' => 'not-a-date', 'date_field' => 'sideways', 'q' => '  CVE-2026 ',
    ]))->toBe([
        'languages' => ['php', 'go'], 'severity' => 'high', 'period' => 'custom',
        'from' => '2026-11-01', 'to' => null, 'date_field' => 'published', 'q' => 'CVE-2026',
    ]);
});

it('accepts a comma separated language list and rejects unknown severities and periods', function () {
    $f = IssueQuery::fromRequest(['languages' => 'php,python', 'severity' => 'apocalyptic', 'period' => '3y']);

    expect($f['languages'])->toBe(['php', 'python'])->and($f['severity'])->toBeNull()->and($f['period'])->toBeNull();
});
