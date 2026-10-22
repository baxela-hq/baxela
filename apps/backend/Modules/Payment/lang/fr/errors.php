<?php

return [
    'process.invalid_order' => 'La commande est invalide, expirée ou non payable',
    'process.method_not_supported' => 'Ce mode de paiement n’est pas encore pris en charge',
    'process.method_inactive' => 'Ce mode de paiement est actuellement indisponible',
    'process.gateway_unconfigured' => 'Ce mode de paiement n’est pas encore configuré sur la boutique',
    'update.invalid_status_transition' => 'Le statut de paiement ne peut plus être modifié',
    'webhook.not_supported' => 'Ce pilote de paiement n’accepte pas les webhooks',
    'webhook.invalid' => 'La charge utile du webhook ne correspond à aucun paiement',
    'refund.not_supported' => 'Ce mode de paiement ne prend pas encore en charge les remboursements',
    'refund.exceeds_refundable' => 'Le montant du remboursement dépasse le solde remboursable restant',
    'refund.invalid_status' => 'Le paiement ou le remboursement n’est pas dans un état permettant cette action',
    'refund.failed' => 'La passerelle a rejeté le remboursement',
    'refund.outcome_unknown' => 'Le résultat du remboursement est inconnu ; il est mis en attente pour rapprochement',
];
