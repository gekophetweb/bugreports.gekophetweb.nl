<?php

namespace App\Http\Controllers;

use App\Models\IssueWatch;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signed links from emails. GET shows a confirmation (mail scanners open links), POST removes;
 * mail clients' one-click unsubscribe (RFC 8058) POSTs to the same signed URL.
 */
class UnsubscribeController
{
    public function subscription(Request $request, int $subscription): View
    {
        $sub = Subscription::query()->with('language')->find($subscription);
        $done = $request->isMethod('post') || ! $sub;
        if ($request->isMethod('post')) {
            $sub?->delete();
        }

        return view('unsubscribe', [
            'done' => $done,
            'what' => $sub ? 'emails about '.$sub->language->name.' ('.$sub->frequency->label().')' : 'these emails',
            'action' => $request->fullUrl(),
        ]);
    }

    public function watch(Request $request, int $watch): View
    {
        $w = IssueWatch::query()->with('issue')->find($watch);
        $done = $request->isMethod('post') || ! $w;
        if ($request->isMethod('post')) {
            $w?->delete();
        }

        return view('unsubscribe', [
            'done' => $done,
            'what' => $w ? 'updates about '.$w->issue->external_id : 'these updates',
            'action' => $request->fullUrl(),
        ]);
    }

    public function advanceWarnings(Request $request, int $user): View
    {
        $u = User::query()->find($user);
        if ($request->isMethod('post')) {
            $u?->update(['advance_warnings' => false]);
        }

        return view('unsubscribe', [
            'done' => $request->isMethod('post') || ! $u,
            'what' => 'advance warnings about upcoming security releases',
            'action' => $request->fullUrl(),
        ]);
    }
}
