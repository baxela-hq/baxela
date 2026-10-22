<?php

/*
 * Bot conversation strings, keyed by reply name. Rendered per chat with
 * the recipient's own Telegram language.
 */
return [
    'start' => "¡Bienvenido a :app! 👋\n\nTu código de vinculación: :code\nTu ID de Telegram: :chat_id\n\nAbre Notificaciones en la configuración de tu cuenta e ingresa el código para recibir aquí las novedades de tus pedidos. El código vence en :minutes minutos.",
    'help' => "Este bot envía las notificaciones de pedidos de :app.\n\nComandos:\n/start — obtener un código de vinculación\n/unlink — dejar de recibir notificaciones en este chat",
    'unlinked' => 'Este chat fue desvinculado — ya no recibirás notificaciones aquí. Envía /start para vincularlo de nuevo.',
    'linked' => '✅ ¡Vinculado! Ahora recibirás tus notificaciones de :app en este chat.',
    'test' => '✅ Este es un mensaje de prueba — tus notificaciones de Telegram funcionan.',
];
