<?php

namespace Modules\User\Schemas\Profile;

enum GenderEnum: string
{
    case MALE = 'male';

    case FEMALE = 'female';

    case OTHER = 'other';
}
