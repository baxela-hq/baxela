<?php

return [
    'process.invalid_order' => 'Die Bestellung ist ungültig, abgelaufen oder nicht zahlbar',
    'process.method_not_supported' => 'Diese Zahlungsmethode wird noch nicht unterstützt',
    'process.method_inactive' => 'Diese Zahlungsmethode ist derzeit nicht verfügbar',
    'process.gateway_unconfigured' => 'Diese Zahlungsmethode ist im Shop noch nicht konfiguriert',
    'update.invalid_status_transition' => 'Der Zahlungsstatus kann nicht mehr geändert werden',
    'webhook.not_supported' => 'Dieser Zahlungs-Treiber akzeptiert keine Webhooks',
    'webhook.invalid' => 'Die Webhook-Nutzdaten stimmen mit keiner Zahlung überein',
    'refund.not_supported' => 'Diese Zahlungsmethode unterstützt noch keine Erstattungen',
    'refund.exceeds_refundable' => 'Der Erstattungsbetrag übersteigt den verbleibenden erstattbaren Betrag',
    'refund.invalid_status' => 'Die Zahlung oder Erstattung befindet sich nicht in einem Zustand, der diese Aktion erlaubt',
    'refund.failed' => 'Der Zahlungsdienstleister hat die Erstattung abgelehnt',
    'refund.outcome_unknown' => 'Das Ergebnis der Erstattung ist unbekannt; sie wird zur Abgleichung zurückgestellt',
];
