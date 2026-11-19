<?php

namespace App\Livewire\Issues;

use App\Enums\Severity;
use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\Language;
use App\Support\Issues\IssueQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The public issue list. Every filter lives in the query string, so views can be shared and linked. */
#[Layout('layouts.app')]
#[Title('Security issues')]
class Index extends Component
{
    use TogglesIssueFlags, WithPagination;

    /** @var list<string> */
    #[Url(except: [])]
    public array $languages = [];

    #[Url(except: '')]
    public string $severity = '';

    #[Url(except: '')]
    public string $period = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'published')]
    public string $date_field = 'published';

    #[Url(except: '')]
    public string $q = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('languages', 'severity', 'period', 'from', 'to', 'date_field', 'q');
        $this->resetPage();
    }

    /** Star from the list. Guests are sent to sign in and come back to this filtered view. */
    public function toggleStar(int $issueId): void
    {
        $issue = Issue::query()->findOrFail($issueId);
        $this->flip(IssueStar::class, $issue, url()->previous() ?: route('home'));
    }

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return IssueQuery::fromRequest([
            'languages' => $this->languages, 'severity' => $this->severity, 'period' => $this->period,
            'from' => $this->from, 'to' => $this->to, 'date_field' => $this->date_field, 'q' => $this->q,
        ]);
    }

    protected function baseQuery()
    {
        return Issue::query()->notWithdrawn();
    }

    public function render()
    {
        $filters = $this->filters();
        $issues = IssueQuery::apply($this->baseQuery()->with('languages:id,slug,name'), $filters, auth()->user())->paginate(25);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'filters' => $filters,
            'allLanguages' => Language::query()->enabled()->orderBy('kind')->orderBy('name')->get(['id', 'slug', 'name', 'kind']),
            'severities' => array_filter(Severity::cases(), fn (Severity $s) => $s !== Severity::Unknown),
            'periods' => IssueQuery::PERIODS,
            'heading' => 'Security issues',
        ]);
    }
}
