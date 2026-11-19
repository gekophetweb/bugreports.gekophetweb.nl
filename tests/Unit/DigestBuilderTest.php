<?php

use App\Enums\Severity;
use App\Models\Issue;
use App\Support\Digest\DigestBuilder;

function digestIssue(Severity $severity, int $daysAgo, int $id): Issue
{
    $issue = new Issue(['severity' => $severity, 'published_at' => now()->subDays($daysAgo), 'external_id' => "X-$id", 'title' => "Issue $id"]);
    $issue->id = $id;

    return $issue;
}

it('shows 20 in full, 100 as one line and counts the rest', function () {
    $issues = collect(range(1, 150))->map(fn ($i) => digestIssue(Severity::High, $i % 7, $i));

    $digest = DigestBuilder::build(collect(), $issues, ['languages' => ['php'], 'period' => '7d']);

    expect($digest->full)->toHaveCount(20)
        ->and($digest->brief)->toHaveCount(100)
        ->and($digest->overflowCount)->toBe(30)
        ->and(urldecode($digest->moreUrl))->toContain('languages[0]=php')->toContain('period=7d');
});

it('ranks by severity, then newest', function () {
    $digest = DigestBuilder::build(collect(), collect([
        digestIssue(Severity::High, 0, 1),
        digestIssue(Severity::Critical, 6, 2),
        digestIssue(Severity::High, 2, 3),
    ]), []);

    expect(collect($digest->full)->pluck('issue.id')->all())->toBe([2, 1, 3]);
});

it('never shortens watched updates', function () {
    $watched = collect(range(1, 130))->map(fn ($i) => ['issue' => digestIssue(Severity::Low, 1, $i), 'fields' => ['description'], 'previous' => Severity::Low, 'tag' => 'watched']);

    $digest = DigestBuilder::build($watched, collect(), []);

    expect($digest->watched)->toHaveCount(130)->and($digest->overflowCount)->toBe(0);
});

it('marks Critical issues as already alerted when asked', function () {
    $digest = DigestBuilder::build(collect(), collect([digestIssue(Severity::Critical, 1, 1), digestIssue(Severity::High, 1, 2)]), [], markCriticalAlerted: true);

    expect(collect($digest->full)->pluck('alerted')->all())->toBe([true, false]);
});

it('knows when it is empty', function () {
    expect(DigestBuilder::build(collect(), collect(), [])->isEmpty())->toBeTrue();
});
