<?php

namespace Alpha\Journal\Privacy;

use Alpha\Journal\Models\User;
use Alpha\Journal\Support\Activity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Account deletion request flow with editorial-record retention policy (CLAUDE.md §11.5).
 * Scrubs personally-identifying fields but keeps the row — every submission, review, and
 * editorial decision the account is party to stays exactly as it was, just no longer traceable
 * to a real name or email. The DB's restrictOnDelete constraints on those tables make skipping
 * this and hard-deleting the row impossible, not just discouraged.
 */
class AccountAnonymizationService
{
    public function anonymize(User $user): void
    {
        if ($user->anonymized_at !== null) {
            throw new InvalidArgumentException('This account has already been anonymized.');
        }

        $user->forceFill([
            'name' => 'Deleted User',
            'email' => "deleted-user-{$user->id}@anonymized.invalid",
            'password' => Hash::make(Str::random(40)),
            'orcid' => null,
            'orcid_access_token' => null,
            'orcid_token_expires_at' => null,
            'orcid_authenticated_at' => null,
            'is_suspended' => true,
            'suspended_at' => now(),
            'anonymized_at' => now(),
        ])->save();

        Activity::log('user.anonymized', $user, 'Account anonymized at user request; editorial records retained.');
    }
}
