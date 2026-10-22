<?php

return [
    'description' => 'Payez avec Airtel Money. Saisissez votre numéro pour recevoir une demande de paiement.',
    'title' => 'Airtel Money',

    'redirect' => [
        'click-if-not-redirected' => 'Retourner à la boutique',
        'pay-button' => 'Payer avec Airtel Money',
        'phone-placeholder' => 'Votre numéro Airtel Money (ex. : 0330000000)',
        'processing' => 'Traitement en cours…',
        'secure-payment' => 'Paiement sécurisé',
        'step-1' => 'Saisissez votre numéro Airtel Money.',
        'step-2' => 'Une demande de paiement sera envoyée sur votre téléphone.',
        'step-3' => 'Confirmez le paiement avec votre code PIN Airtel Money.',
    ],

    'response' => [
        'cart-not-found' => 'Panier introuvable ou invalide.',
        'invalid-transaction' => 'Transaction invalide. Veuillez réessayer.',
        'order-creation-failed' => 'Le paiement a réussi, mais la commande n’a pas pu être créée. Contactez le service client.',
        'payment-cancelled' => 'Le paiement a été annulé.',
        'payment-failed' => 'Le paiement a échoué. Veuillez réessayer.',
        'payment-pending' => 'Votre paiement Airtel Money est en attente. Confirmez-le sur votre téléphone.',
        'payment-success' => 'Le paiement a été effectué.',
        'provide-credentials' => 'Configurez les identifiants Airtel Money requis dans l’environnement sécurisé du serveur.',
        'supported-currency-error' => 'La devise :currency n’est pas prise en charge. Devises acceptées : :supportedCurrencies.',
        'verification-failed' => 'Le paiement n’a pas pu être vérifié.',
    ],
];
