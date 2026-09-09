<?php

return [
    'role' => [
        'creation_failed' => 'The role could not be created. Please try again.',
        'update_failed' => 'The role could not be updated. Please try again.',
        'deletion_failed' => 'The role could not be deleted. Please try again.',
        'in_use' => 'This role is assigned to one or more users. Reassign them first, then delete the role.',
    ],
    'account' => [
        'already_active' => 'Your account is already activated.',
        'not_active' => 'Please activate your account before processing your request.',
    ],
    'credentials' => [
        'invalid' => 'The username or password you entered is incorrect.',
    ],
    'otp' => [
        'too_many_requests' => 'Please wait a moment before requesting a new OTP.',
        'invalid' => 'The OTP you entered is invalid or has expired.',
    ],
];
