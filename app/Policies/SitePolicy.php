<?php

namespace Alpha\Journal\Policies;

use Alpha\Journal\Models\User;

/**
 * Site-level configuration (title, homepage content, which journal '/' redirects to) is the one
 * genuinely global action in the app — CLAUDE.md §4 draws the line at is_site_admin for exactly
 * this reason (it's the one global role; everything else is per-journal).
 */
class SitePolicy
{
    public function manage(User $user): bool
    {
        // Cast rather than return the attribute raw: a model built in memory can carry null here
        // (see User::$attributes), and a null return from a `: bool` method is a 500, not a denial.
        return (bool) $user->is_site_admin;
    }
}
