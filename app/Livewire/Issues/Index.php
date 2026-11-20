<?php

namespace App\Livewire\Issues;

use App\Enums\Severity;
use App\Livewire\Concerns\ManagesPackageLists;
use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Announcement;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\Language;
use App\Models\SyncRun;
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
    use ManagesPackageLists, TogglesIssueFlags, WithPagination;

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

    /** Show everything, also packages that are hidden by default or by choice (this page view only). */
    #[Url(as: 'hidden', except: false)]
    public bool $showHidden = false;

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
        $user = auth()->user();
        $visibility = $this->packageVisibility();
        $query = $this->baseQuery();
        $hiddenCount = IssueQuery::apply($visibility->apply($this->baseQuery(), onlyHidden: true), $filters)
            ->toBase()->getCountForPagination();
        if (! $this->showHidden) {
            $visibility->apply($query);
        }
        $issues = IssueQuery::apply($query->with('languages:id,slug,name'), $filters, $user)->paginate(25);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'hiddenCount' => $hiddenCount,
            'announcements' => Announcement::forBanner($filters['languages']),
            'hiddenPackages' => $visibility->hidden,
            'usedPackages' => $visibility->used,
            'filters' => $filters,
            'allLanguages' => Language::query()->enabled()->orderBy('kind')->orderBy('name')->get(['id', 'slug', 'name', 'kind']),
            'severities' => array_filter(Severity::cases(), fn (Severity $s) => $s !== Severity::Unknown),
            'periods' => IssueQuery::PERIODS,
            'heading' => 'Security issues',
            'lastChecked' => SyncRun::lastSuccessful(),
            ...$this->extraViewData(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function extraViewData(): array
    {
        return [];
    }
}
