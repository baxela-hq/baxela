<?php

return [
    'process.invalid_order' => 'El pedido no es válido, ha caducado o no es pagadero',
    'process.method_not_supported' => 'Este método de pago aún no es compatible',
    'process.method_inactive' => 'Este método de pago no está disponible actualmente',
    'process.gateway_unconfigured' => 'Este método de pago aún no está configurado en la tienda',
    'update.invalid_status_transition' => 'El estado del pago ya no se puede cambiar',
    'webhook.not_supported' => 'Este controlador de pago no acepta webhooks',
    'webhook.invalid' => 'Los datos del webhook no coinciden con ningún pago',
    'refund.not_supported' => 'Este método de pago aún no admite reembolsos',
    'refund.exceeds_refundable' => 'El monto del reembolso supera el saldo reembolsable restante',
    'refund.invalid_status' => 'El pago o el reembolso no está en un estado que permita esta acción',
    'refund.failed' => 'La pasarela rechazó el reembolso',
    'refund.outcome_unknown' => 'El resultado del reembolso es desconocido; queda en espera para conciliación',
];
