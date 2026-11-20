<?php

namespace App\Livewire\Issues;

use App\Enums\Severity;
use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\Language;
use App\Models\SyncRun;
use App\Support\Issues\HiddenPackages;
use App\Support\Issues\IssueQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    /** @var list<string>|null a visitor's hidden packages after a change in this page (null: read the cookie) */
    #[Locked]
    public ?array $visitorHidden = null;

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

    /** Hides issues of this package: on the account (site and emails), or in a cookie for visitors. */
    public function hidePackage(string $package): void
    {
        $package = HiddenPackages::normalize($package);
        if (! HiddenPackages::isValid($package)) {
            return;
        }

        if ($user = auth()->user()) {
            $user->hidePackage($package);
            session()->flash('status', "Issues of $package are hidden now. Undo in Email settings.");

            return;
        }

        $this->visitorHidden = HiddenPackages::clean([...HiddenPackages::fromCookie(), ...$this->visitorHidden ?? [], $package]);
        HiddenPackages::queueCookie($this->visitorHidden);
        session()->flash('status', "Issues of $package are hidden in this browser. Sign in to hide them from emails too.");
    }

    /** Shows a package again: for visitors from the header, for users also in Email settings. */
    public function unhidePackage(string $package): void
    {
        if ($user = auth()->user()) {
            $user->unhidePackage($package);

            return;
        }

        $package = HiddenPackages::normalize($package);
        $this->visitorHidden = array_values(array_filter(
            $this->visitorHidden ?? HiddenPackages::fromCookie(), fn ($p) => $p !== $package));
        HiddenPackages::queueCookie($this->visitorHidden);
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
        // A cookie set during this request is only readable from the next one, so visitors' changes are kept here too.
        $hidden = $user?->hiddenPackages() ?? $this->visitorHidden ?? HiddenPackages::fromCookie();
        $query = $this->baseQuery();
        $hiddenCount = 0;
        if ($hidden !== []) {
            $hiddenCount = IssueQuery::apply(HiddenPackages::apply($this->baseQuery(), $hidden, $user?->id, onlyHidden: true), $filters)
                ->toBase()->getCountForPagination();
            if (! $this->showHidden) {
                HiddenPackages::apply($query, $hidden, $user?->id);
            }
        }
        $issues = IssueQuery::apply($query->with('languages:id,slug,name'), $filters, $user)->paginate(25);

        return view('livewire.issues.index', [
            'issues' => $issues,
            'hiddenCount' => $hiddenCount,
            'hiddenPackages' => $hidden,
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
