<?php

/*
 * Bot conversation strings, keyed by reply name. Rendered per chat with
 * the recipient's own Telegram language.
 */
return [
    'start' => "Willkommen bei :app! 👋\n\nIhr Verknüpfungscode: :code\nIhre Telegram-ID: :chat_id\n\nÖffnen Sie Benachrichtigungen in Ihren Kontoeinstellungen und geben Sie den Code ein, um Ihre Bestellupdates hier zu erhalten. Der Code läuft in :minutes Minuten ab.",
    'help' => "Dieser Bot liefert :app Bestellbenachrichtigungen.\n\nBefehle:\n/start — Verknüpfungscode abrufen\n/unlink — Benachrichtigungen in diesem Chat stoppen",
    'unlinked' => 'Dieser Chat wurde getrennt — Sie erhalten hier keine Benachrichtigungen mehr. Senden Sie /start, um ihn erneut zu verknüpfen.',
    'linked' => '✅ Verknüpft! Sie erhalten nun Ihre :app Benachrichtigungen in diesem Chat.',
    'test' => '✅ Dies ist eine Testnachricht — Ihre Telegram-Benachrichtigungen funktionieren.',
];
