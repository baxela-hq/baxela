<?php

/*
 * Bot conversation strings, keyed by reply name. Rendered per chat with
 * the recipient's own Telegram language (fa when their client reports a
 * fa* locale, English otherwise).
 */
return [
    'start' => "Welcome to :app! 👋\n\nYour linking code: :code\nYour Telegram ID: :chat_id\n\nOpen Notifications in your account settings and enter the code to receive your order updates here. The code expires in :minutes minutes.",
    'help' => "This bot delivers :app order notifications.\n\nCommands:\n/start — get a linking code\n/unlink — stop notifications on this chat",
    'unlinked' => 'This chat has been unlinked — you will no longer receive notifications here. Send /start to link again.',
    'linked' => "✅ Linked! You'll now receive your :app notifications in this chat.",
    'test' => '✅ This is a test message — your Telegram notifications are working.',
];
