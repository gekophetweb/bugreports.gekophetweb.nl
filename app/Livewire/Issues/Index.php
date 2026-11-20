<?php

namespace App\Livewire\Issues;

use App\Enums\Severity;
use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Announcement;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\Language;
use App\Models\SyncRun;
use App\Support\Issues\HiddenPackages;
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

    /** Also show issues whose packages the signed-in user hid. */
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

    /** Hides issues of this package for the signed-in user, on the site and in emails. */
    public function hidePackage(string $package): void
    {
        $user = auth()->user();
        if (! $user) {
            session()->put('url.intended', url()->previous() ?: route('home'));
            $this->redirectRoute('login');

            return;
        }
        if ($user->hidePackage($package)) {
            session()->flash('status', 'Issues of '.HiddenPackages::normalize($package).' are hidden now. Undo in Email settings.');
        }
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
        $hidden = $user?->hiddenPackages() ?? [];
        $query = $this->baseQuery();
        $hiddenCount = 0;
        if ($hidden !== []) {
            $hiddenCount = IssueQuery::apply(HiddenPackages::apply($this->baseQuery(), $hidden, $user->id, onlyHidden: true), $filters)
                ->toBase()->getCountForPagination();
            if (! $this->showHidden) {
                HiddenPackages::apply($query, $hidden, $user->id);
            }
        }
        $issues = IssueQuery::apply($query->with('languages:id,slug,name'), $filters, $user)->paginate(25);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'hiddenCount' => $hiddenCount,
            'announcements' => Announcement::forBanner($filters['languages']),
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
