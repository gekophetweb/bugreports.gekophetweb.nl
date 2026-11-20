# Packages opt-in — Design (short)

Date: 2026-11-20 · Status: approved ("yes")

## Intent

By default the site is about the languages and servers themselves (PHP, Python, Node.js, nginx, Apache…), not
the thousands of open source packages built on them. People opt in to the packages they actually use.

## Goals

- Every issue has a **scope**: `core` (the language or server itself) or `package`.
  - NVD (runtime and server CPEs) and php-src advisories are `core`.
  - OSV records are `package`, except a language's own standard library (`stdlib`, `toolchain` in Go).
  - Once an issue is `core` it stays `core` (an NVD record merged into an advisory row).
- **Shown by default:** core issues. **Shown on top of that:**
  - issues of **packages you use**: a list like the hidden one (`laravel/*`, `silverstripe/framework`); an issue
    shows when *any* of its packages is on it;
  - all package issues of a language you follow with **All packages** on (a per-subscription switch);
  - issues you starred or watched.
- The hidden list keeps working on top (hide `silverstripe/*` inside a language whose packages you all see).
- **Site:** the issue list and My issues apply this; the header says how many are left out and has
  "Show all" (not saved). Rows and issue pages get "I use this" and "Hide" per package.
- **Visitors:** packages they use are kept in a cookie, like hidden ones, and move into the account on sign-in.
- **Emails** (instant, Critical alerts, Critical updates, digests) follow the same rule per subscription.
  Watched issues are always emailed.

## Non-goals

- Guessing which packages someone uses (from a composer.lock, say). Possible later.

## Anti-goals

- Existing subscribers silently losing the package emails they get today: existing subscriptions start with
  **All packages** on. New subscriptions start with it off.

## Approaches

- **Scope column + preferences (chosen):** one stored fact per issue, filters in SQL and in PHP for emails.
- A per-source filter at query time (source = osv): rejected, it would hide Go's stdlib and break on merged rows.

## Builder's calls

- "All packages" is per subscription (signed-in users); visitors have only the use list and "Show all".
- The list header's "Show all" shows everything (also hidden packages) for that page view only.
