<?php

/*
 * Bot conversation strings, keyed by reply name. Rendered per chat with
 * the recipient's own Telegram language.
 */
return [
    'start' => "Bienvenue sur :app ! 👋\n\nVotre code de liaison : :code\nVotre identifiant Telegram : :chat_id\n\nOuvrez Notifications dans les paramètres de votre compte et saisissez le code pour recevoir ici les mises à jour de vos commandes. Le code expire dans :minutes minutes.",
    'help' => "Ce bot envoie les notifications de commande de :app.\n\nCommandes :\n/start — obtenir un code de liaison\n/unlink — désactiver les notifications sur ce chat",
    'unlinked' => 'Ce chat a été délié — vous ne recevrez plus de notifications ici. Envoyez /start pour vous lier à nouveau.',
    'linked' => '✅ Lié ! Vous recevrez désormais vos notifications :app dans ce chat.',
    'test' => '✅ Ceci est un message test — vos notifications Telegram fonctionnent.',
];
