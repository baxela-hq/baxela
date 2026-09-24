<?php

namespace Modules\Auth\Schemas;

class RouteSchema
{
    // Auth
    public const string PREFIX = 'auth';

    public const string ACCOUNT_PREFIX = 'account';

    public const string SIGN_UP = 'signup';

    public const string SIGN_IN = 'signin';

    public const string VERIFY_ACCOUNT_ACTIVATION = 'account-activation/verify';

    public const string REQUEST_ACCOUNT_ACTIVATION = 'account-activation/request';

    public const string REQUEST_PASSWORD_RESET = 'reset-password/request';

    public const string VERIFY_PASSWORD_RESET = 'reset-password/verify';

    // Profile
    public const string SIGN_OUT = 'sign-out';

    public const string ME = 'me';

    public const string SESSIONS = 'sessions';

    public const string SESSION = 'sessions/{id}';
}
