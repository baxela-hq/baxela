<?php

return [
    'role' => [
        'creation_failed' => 'Le rôle n’a pas pu être créé. Veuillez réessayer.',
        'update_failed' => 'Le rôle n’a pas pu être mis à jour. Veuillez réessayer.',
        'deletion_failed' => 'Le rôle n’a pas pu être supprimé. Veuillez réessayer.',
        'in_use' => 'Ce rôle est attribué à un ou plusieurs utilisateurs. Réattribuez-leur d’abord un autre rôle, puis supprimez le rôle.',
    ],
    'account' => [
        'already_active' => 'Votre compte est déjà activé.',
        'not_active' => 'Veuillez activer votre compte avant que votre demande soit traitée.',
    ],
    'credentials' => [
        'invalid' => 'Le nom d’utilisateur ou le mot de passe saisi est incorrect.',
    ],
    'otp' => [
        'too_many_requests' => 'Veuillez patienter un instant avant de demander un nouveau code OTP.',
        'invalid' => 'Le code OTP saisi est invalide ou a expiré.',
    ],
    'two_factor.invalid_code' => 'Le code de vérification est invalide ou expiré',
    'two_factor.challenge_invalid' => 'Le challenge est invalide ou expiré',
    'two_factor.too_many_attempts' => 'Trop de tentatives de vérification. Réessayez plus tard.',

    'google' => [
        'invalid_token' => 'Échec de la connexion avec Google. Veuillez réessayer.',
        'nonce_invalid' => 'La connexion avec Google a expiré. Veuillez réessayer.',
        'disabled' => 'La connexion avec Google est actuellement indisponible.',
        'email_unverified' => 'Échec de la connexion avec Google : l\'adresse e-mail n\'a pas pu être vérifiée.',
        'email_exists' => 'Un compte avec cet e-mail existe déjà. Veuillez vous connecter avec votre mot de passe.',
        'admin_forbidden' => 'La connexion avec Google n\'est pas disponible pour ce compte.',
        'unavailable' => 'La connexion avec Google est temporairement indisponible. Veuillez réessayer.',
    ],
];
