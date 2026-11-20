# PHP security announcements — Design (short)

Date: 2026-11-20 · Status: approved ("build it")

## Intent

PHP announces critical security releases a day or so ahead on php.net ("Upcoming Critical PHP Security
Release": date, time window, highest severity, no details). Users should hear about it in time to prepare, and
hear again when the release is out, with what it fixes. PHP's own advisories (php-src on GitHub) appear minutes
after a release, days before NVD has the CVEs.

## Goals

- **php-src advisories as issues.** A new source, `ghsa`, reads GitHub repository security advisories for the
  repos listed on a language (`github_repos`, PHP: `php/php-src`) every 10 minutes. They merge with the NVD record
  of the same CVE; like OSV, the advisory takes over a row that only came from NVD.
- **php.net announcements.** The php.net news feed is read every 5 minutes into `announcements`
  (language, kind, title, text, link, published, scheduled window, severity, version):
  - "Upcoming … Security Release" → `upcoming`, with the scheduled window parsed from the text;
  - "Security advisory: …" → `advisory`; "Informational advisory: …" → `informational`;
  - "PHP x.y.z Released!" that says "security release" → `release` with its version. Other posts are skipped.
- **Emails**, to users subscribed to the language with **advance warnings** on (a new user setting, on by default):
  - heads-up, straight away, for `upcoming` (window in UTC and site time, php.net's text, a prepare list, a
    calendar file) and for `advisory`;
  - "it's out", once per upcoming release: fixed versions and the php-src advisories of that release. It replaces
    the usual new-issue alert for those advisories for these users, so nobody gets them twice.
- **Banner** on the issue list (and My issues) while an upcoming release is pending or just released, and for
  recent advisories, shown when no language filter excludes PHP.
- **Advance warnings** switch in Email settings, and a signed one-click "stop advance warnings" link in the emails.

## Non-goals

- Emailing every regular security release: only releases that php.net warned about get an "it's out" email
  (the advisories of other releases go through the normal alerts).
- Informational advisories by email, announcements in digests, other languages' announcement feeds (the table
  has a language, so they can be added later).

## Anti-goals

- Old posts emailed on the first import: only posts from the last 2 days are emailed, and an `upcoming` only while
  its window has not passed.
- The same release emailed twice, or an advisory alerted twice to the same user.

## Approaches

- **A (chosen):** advisories as a normal issue source; php.net posts as a separate announcements model.
- B: php.net posts stored as issues. Rejected: invented severities, no CVE to merge on, no way to group a release.
- C: advisories only. Rejected: no advance warning.

## Builder's calls

- The "it's out" email waits 20 minutes after the first release post (advisories are published a few minutes
  after the release posts); a fallback runs 3 hours after the announced window, and retries every 3 hours for up
  to 2 days if nothing is out yet.
- Advisories are held back from the normal alert only for users with advance warnings on, only while that
  release's "it's out" email is still to be sent, and only when they were published within 12 hours of the
  announced window.
- Without a GitHub token, GitHub allows 60 requests an hour; one repo every 10 minutes uses 6.
- php.net is read with a conditional request (ETag), so an unchanged feed costs almost nothing.
