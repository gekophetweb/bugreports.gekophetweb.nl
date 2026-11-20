<?php

namespace App\Livewire\Concerns;

use App\Support\Issues\HiddenPackages;
use App\Support\Issues\PackageVisibility;
use Livewire\Attributes\Locked;

/**
 * "Hide" and "I use this" for packages, on the issue list and issue pages. Signed-in users keep the
 * lists on their account (they also filter emails); visitors keep them in cookies. A cookie set in this
 * request is only readable from the next one, so a visitor's changes are also kept on the component.
 */
trait ManagesPackageLists
{
    /** @var list<string>|null */
    #[Locked]
    public ?array $visitorHidden = null;

    /** @var list<string>|null */
    #[Locked]
    public ?array $visitorUsed = null;

    public function hidePackage(string $package): void
    {
        $this->changePackageList($package, used: false, add: true,
            message: fn ($p) => auth()->check()
                ? "Issues of $p are hidden from your lists and emails. Undo in Email settings."
                : "Issues of $p are hidden in this browser.");
    }

    public function unhidePackage(string $package): void
    {
        $this->changePackageList($package, used: false, add: false);
    }

    public function usePackage(string $package): void
    {
        $this->changePackageList($package, used: true, add: true,
            message: fn ($p) => auth()->check()
                ? "Issues of $p are shown in your lists and emails now."
                : "Issues of $p are shown in this browser now.");
    }

    public function unusePackage(string $package): void
    {
        $this->changePackageList($package, used: true, add: false);
    }

    protected function packageVisibility(): PackageVisibility
    {
        $user = auth()->user();

        return $user ? PackageVisibility::forUser($user) : PackageVisibility::forVisitor($this->visitorHidden, $this->visitorUsed);
    }

    private function changePackageList(string $package, bool $used, bool $add, ?\Closure $message = null): void
    {
        $package = HiddenPackages::normalize($package);
        if (! HiddenPackages::isValid($package)) {
            return;
        }

        if ($user = auth()->user()) {
            match (true) {
                $used && $add => $user->usePackage($package),
                $used => $user->unusePackage($package),
                $add => $user->hidePackage($package),
                default => $user->unhidePackage($package),
            };
        } else {
            $cookie = $used ? HiddenPackages::USED_COOKIE : HiddenPackages::COOKIE;
            $current = ($used ? $this->visitorUsed : $this->visitorHidden) ?? HiddenPackages::fromCookie($cookie);
            $list = $add ? HiddenPackages::clean([...$current, $package]) : array_values(array_filter($current, fn ($p) => $p !== $package));
            $used ? $this->visitorUsed = $list : $this->visitorHidden = $list;
            HiddenPackages::queueCookie($list, $cookie);
        }

        if ($message) {
            session()->flash('status', $message($package));
        }
    }
}
