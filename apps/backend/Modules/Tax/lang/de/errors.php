<?php

return [
    'class' => [
        'creation_failed' => 'Erstellen der Steuerklasse fehlgeschlagen',
        'update_failed' => 'Aktualisieren der Steuerklasse fehlgeschlagen',
        'delete_default' => 'Die Standard-Steuerklasse kann nicht gelöscht werden; legen Sie zuerst eine andere Klasse als Standard fest',
        'in_use' => 'Die Steuerklasse ist noch Produkten zugewiesen und kann nicht gelöscht werden',
    ],
    'rate' => [
        'creation_failed' => 'Erstellen des Steuersatzes fehlgeschlagen',
        'update_failed' => 'Aktualisieren des Steuersatzes fehlgeschlagen',
    ],
    'configuration' => [
        'invalid_class' => 'Ein Produkt verweist auf eine Steuerklasse, die nicht mehr existiert; der Checkout kann nicht fortgesetzt werden',
        'no_default' => 'Es ist keine Standard-Steuerklasse konfiguriert',
    ],
];
