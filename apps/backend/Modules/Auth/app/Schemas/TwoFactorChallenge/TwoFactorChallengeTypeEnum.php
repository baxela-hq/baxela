<?php

namespace Modules\Auth\Schemas\TwoFactorChallenge;

enum TwoFactorChallengeTypeEnum: string
{
    case SIGN_IN = 'sign_in';

    case ENROLLMENT = 'enrollment';
}
