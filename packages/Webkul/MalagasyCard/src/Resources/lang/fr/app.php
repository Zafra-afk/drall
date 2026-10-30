<?php

return [
    'description' => 'Payez en toute sécurité avec votre carte bancaire via Papi (papi.mg). Accepte Visa, MasterCard et American Express.',
    'title' => 'Malagasy Card',

    'redirect' => [
        'click-if-not-redirected' => 'Cliquez ici pour continuer',
        'pay-button' => 'Payer par carte',
        'processing' => 'Traitement en cours…',
        'secure-payment' => 'Passerelle de paiement sécurisée',
        'info-1' => 'Vous serez redirigé(e) vers la page de paiement sécurisée de Papi.',
        'info-2' => 'Saisissez les informations de votre carte pour finaliser le paiement.',
        'info-3' => 'Après le paiement, vous serez redirigé(e) vers ce magasin.',
    ],

    'response' => [
        'cart-not-found' => 'Aucun panier trouvé ou panier invalide.',
        'invalid-transaction' => 'Transaction invalide. Veuillez réessayer.',
        'order-creation-failed' => 'Le paiement a réussi, mais la commande n’a pas pu être créée. Contactez le support.',
        'payment-cancelled' => 'Le paiement a été annulé.',
        'payment-failed' => 'Le paiement a échoué. Veuillez réessayer.',
        'payment-pending' => 'Votre paiement est toujours en attente. Veuillez patienter un moment et réessayer.',
        'payment-success' => 'Le paiement a été effectué avec succès.',
        'provide-credentials' => 'Veuillez fournir les identifiants Papi valides dans le panneau d’administration.',
        'supported-currency-error' => 'La devise :currency n’est pas prise en charge. Devises acceptées : :supportedCurrencies.',
        'verification-failed' => 'Le paiement n’a pas pu être vérifié.',
    ],
];
