<?php

use App\Enums\Severity;

it('bands scores', function (?float $score, Severity $severity) {
    expect(Severity::fromScore($score))->toBe($severity);
})->with([
    [10.0, Severity::Critical], [9.0, Severity::Critical], [8.9, Severity::High], [7.0, Severity::High],
    [6.9, Severity::Medium], [4.0, Severity::Medium], [3.9, Severity::Low], [0.1, Severity::Low],
    [0.0, Severity::Unknown], [null, Severity::Unknown],
]);

it('maps source labels', function (?string $label, Severity $severity) {
    expect(Severity::fromLabel($label))->toBe($severity);
})->with([
    ['CRITICAL', Severity::Critical], ['high', Severity::High], ['MODERATE', Severity::Medium],
    ['medium', Severity::Medium], ['LOW', Severity::Low], ['', Severity::Unknown], [null, Severity::Unknown],
    ['whatever', Severity::Unknown],
]);

it('ranks severities', function () {
    expect(Severity::Critical->rank())->toBe(4)
        ->and(Severity::High->rank())->toBe(3)
        ->and(Severity::Unknown->rank())->toBe(0);
});

it('lists the severities at or above a minimum', function () {
    expect(Severity::High->andAbove())->toBe([Severity::Critical, Severity::High]);
});
