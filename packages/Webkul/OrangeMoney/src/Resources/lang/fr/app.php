<?php

return [
    'description' => 'Payez avec Orange Money. Saisissez votre numéro pour recevoir une demande de paiement.',
    'title' => 'Orange Money',

    'redirect' => [
        'click-if-not-redirected' => 'Retourner à la boutique',
        'pay-button' => 'Payer avec Orange Money',
        'phone-placeholder' => 'Votre numéro Orange Money (ex. : 0320000000)',
        'processing' => 'Traitement en cours…',
        'secure-payment' => 'Paiement sécurisé',
        'step-1' => 'Saisissez votre numéro Orange Money.',
        'step-2' => 'Un code de paiement vous sera envoyé par USSD.',
        'step-3' => 'Saisissez le code pour confirmer le paiement.',
    ],

    'response' => [
        'cart-not-found' => 'Panier introuvable ou invalide.',
        'invalid-transaction' => 'Transaction invalide. Veuillez réessayer.',
        'order-creation-failed' => 'Le paiement a réussi, mais la commande n’a pas pu être créée. Contactez le service client.',
        'payment-cancelled' => 'Le paiement a été annulé.',
        'payment-failed' => 'Le paiement a échoué. Veuillez réessayer.',
        'payment-pending' => 'Votre paiement Orange Money est en attente. Confirmez-le sur votre téléphone.',
        'payment-success' => 'Le paiement a été effectué.',
        'provide-credentials' => 'Configurez les identifiants Orange Money requis dans l’environnement sécurisé du serveur.',
        'supported-currency-error' => 'La devise :currency n’est pas prise en charge. Devises acceptées : :supportedCurrencies.',
        'verification-failed' => 'Le paiement n’a pas pu être vérifié.',
    ],
];
