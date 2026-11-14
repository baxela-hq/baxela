<?php

namespace Modules\Auth\Support\Google;

/**
 * How much Google can be trusted as the authority for this identity's
 * email address (see docs/plans/google-sign-in.md, invariant 3):
 *
 * - GMAIL: Google-hosted address — Google owns the mailbox.
 * - WORKSPACE: hosted-domain account whose `hd` matches the email domain.
 * - THIRD_PARTY: any other address — Google's claim is not authoritative,
 *   so the account must go through the OTP activation flow before it is
 *   trusted (the OTP, not the claim, proves mailbox ownership).
 */
enum GoogleEmailClass: string
{
    case GMAIL = 'gmail';

    case WORKSPACE = 'workspace';

    case THIRD_PARTY = 'third_party';
}
