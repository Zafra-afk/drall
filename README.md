# Drall — Marketplace e-commerce de Madagascar 🇲🇬

<p align="center">
  **La technologie au service du commerce malgache**
</p>

<p align="center">
  <strong>🇲🇬 Madagascar · Français · Malagasy · Ariary (MGA)</strong>
</p>

**Drall** est une plateforme de commerce électronique destinée au marché malgache. Elle vise à faciliter la vente et l'achat de produits informatiques, d'appareils électroniques, de smartphones, d'accessoires et d'autres produits disponibles à Madagascar.

Construite sur Laravel et personnalisée pour les besoins du marché local, Drall a pour objectif de proposer une expérience de vente en ligne simple, accessible et adaptée aux commerçants comme aux clients malgaches.

- **Marché cible :** Madagascar
- **Langues :** Français et Malagasy
- **Devise :** Ariary malgache (MGA / Ar)
- **Fuseau horaire :** Indian/Antananarivo (UTC+3)
- **Démonstration :** https://drall.up.railway.app
- **Dépôt du projet :** https://github.com/Zafra-afk/drall

---

## Sommaire

- [Présentation](#présentation)
- [Objectifs](#objectifs)
- [Fonctionnalités](#fonctionnalités)
- [Marché malgache](#marché-malgache)
- [Technologies](#technologies)
- [Installation](#installation)
- [Configuration](#configuration)
- [Développement](#développement)
- [Déploiement](#déploiement)
- [Sécurité](#sécurité)
- [Licence](#licence)

## Présentation

Drall est un projet de plateforme e-commerce conçu pour répondre aux besoins du commerce en ligne à Madagascar.

Le projet met l'accent sur :

- Une interface accessible sur ordinateur, tablette et smartphone.
- Un catalogue de produits organisé par catégories.
- Une gestion centralisée des produits, des commandes et des clients.
- Une expérience d'achat adaptée aux habitudes locales.
- Une architecture personnalisable et évolutive.

L'objectif est de contribuer au développement du commerce numérique malgache en offrant aux entreprises et aux commerçants une solution adaptable.

## Objectifs

Drall ambitionne de :

1. Faciliter la commercialisation des produits informatiques à Madagascar.
2. Permettre aux clients de consulter les produits et leurs caractéristiques.
3. Simplifier la gestion des catalogues et des commandes.
4. Préparer l'intégration de moyens de paiement adaptés au marché local.
5. Offrir une base technique évolutive pour de futures fonctionnalités.

## Fonctionnalités

Les fonctionnalités suivantes constituent le périmètre fonctionnel du projet. Leur disponibilité dépend de la configuration et de la validation de chaque module.

### Boutique en ligne

- Présentation des produits.
- Organisation par catégories.
- Affichage des prix en Ariary.
- Pages détaillées des produits.
- Recherche et navigation dans le catalogue.
- Interface adaptée aux appareils mobiles.

### Administration

- Gestion des produits.
- Gestion des catégories.
- Gestion des commandes.
- Gestion des comptes clients.
- Configuration du catalogue et de la boutique.

### Comptes clients

- Création de compte et connexion.
- Consultation des informations du compte.
- Gestion des informations personnelles, lorsque le module est activé.
- Accès aux commandes selon les fonctionnalités disponibles.

### Évolutions envisagées

- Intégration de MVola.
- Intégration d'Orange Money.
- Intégration d'Airtel Money.
- Intégration de paiements bancaires par un prestataire compatible avec Madagascar.
- Notifications par e-mail.
- Amélioration du suivi des commandes.
- Développement d'une expérience mobile enrichie.

**Important :** les moyens de paiement et les fonctionnalités en cours de développement ne doivent pas être considérés comme opérationnels tant que leur intégration n'a pas été testée en conditions réelles.

## Marché malgache

Drall est destiné au marché de Madagascar.

### Paramètres régionaux

| Paramètre | Configuration souhaitée |
|---|---|
| Pays de commercialisation | Madagascar |
| Code pays | MG |
| Langue française | fr |
| Langue malagasy | mg |
| Devise | Ariary malgache |
| Code devise | MGA |
| Fuseau horaire | Indian/Antananarivo |
| Décalage horaire | UTC+3 |

La configuration régionale doit être appliquée à la boutique, au processus de commande, aux adresses clients, aux paramètres d'administration et aux éventuelles intégrations externes.

Les sélecteurs de pays destinés aux clients doivent proposer uniquement Madagascar lorsque le champ concerne le pays de livraison ou de facturation de la boutique.

Les langues disponibles doivent être limitées au français et au malagasy. La devise commerciale doit être l'Ariary.

Ces restrictions doivent être mises en œuvre dans la configuration et les composants de l'application, et pas seulement dans cette documentation.

## Technologies

Drall s'appuie sur les technologies suivantes :

- **PHP** — langage serveur.
- **Laravel** — framework applicatif.
- **MySQL** — système de gestion de base de données, selon la configuration.
- **Vue.js** — technologies d'interface utilisées par les composants concernés.
- **JavaScript** — fonctionnalités interactives de l'interface.
- **Composer** — gestion des dépendances PHP.
- **Git et GitHub** — gestion du code source.
- **Railway** — hébergement de la démonstration actuelle.

Les versions requises dépendent des fichiers de dépendances et de la configuration réellement présents dans le dépôt.

## Installation

### Prérequis

Avant l'installation, vérifiez que votre environnement dispose des éléments nécessaires :

- PHP dans une version compatible avec le projet.
- Composer.
- Node.js et le gestionnaire de paquets requis par le projet.
- Une base de données compatible.
- Les extensions PHP exigées par les dépendances.
- Git.

### 1. Récupérer le projet

```bash
git clone https://github.com/Zafra-afk/drall.git
cd drall
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Vérifiez que le fichier `.env.example` existe avant de copier ce fichier.

### 4. Configurer la base de données

Modifiez les paramètres de connexion dans `.env` en fonction de votre environnement :

```dotenv
APP_NAME=Drall
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_TIMEZONE=Indian/Antananarivo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=drall
DB_USERNAME=root
DB_PASSWORD=
```

Adaptez les identifiants de base de données à votre installation. N'utilisez pas ces paramètres de développement tels quels en production.

### 5. Installer les dépendances de l'interface

Consultez `package.json` pour connaître le gestionnaire de paquets et les scripts réellement utilisés.

Avec npm, si le projet est configuré pour cela :

```bash
npm install
npm run build
```

### 6. Préparer l'application

Vérifiez les commandes de migration et d'installation prévues par le projet avant de les exécuter.

Pour une application Laravel déjà configurée avec ses migrations :

```bash
php artisan migrate
php artisan storage:link
php artisan optimize:clear
```

N'exécutez pas une commande d'installation initiale sur une base de données contenant déjà des données sans avoir vérifié ses effets.

### 7. Lancer le serveur de développement

```bash
php artisan serve
```

L'application sera généralement accessible à l'adresse :

http://127.0.0.1:8000

## Configuration

### Nom de l'application

Dans `.env` :

```dotenv
APP_NAME=Drall
```

Vérifiez également les fichiers de configuration, les titres des pages, les e-mails, les modèles de notification et les paramètres d'installation afin d'utiliser le nom Drall dans l'interface.

### Langues

La boutique doit proposer uniquement :

- Français.
- Malagasy.

Il faut vérifier les fichiers de traduction, les paramètres de canal, les menus de langue et les éventuelles traductions fournies par les extensions.

### Devise

La devise commerciale à utiliser est l'Ariary malgache (MGA).

Vérifiez les prix, les taxes, les totaux de commande, les remboursements, les règles d'arrondi et les affichages dans l'administration. Ne convertissez pas arbitrairement les prix existants : une modification de devise doit préserver la cohérence des données.

### Pays

Pour les adresses de livraison et de facturation, le pays commercialisé doit être Madagascar.

Cette restriction ne doit pas supprimer les informations techniques ou les mentions légales nécessaires dans les dépendances du projet.

### Paiements

Les intégrations de paiement doivent être ajoutées et testées séparément. Avant d'activer un fournisseur, vérifiez ses conditions d'accès, ses identifiants, ses URL de retour, la validation des transactions et la gestion des paiements échoués.

Ne stockez jamais de clés secrètes ou de données sensibles dans le dépôt Git.

## Développement

Pour contribuer au développement de Drall :

1. Créez une branche pour votre modification.
2. Effectuez les changements nécessaires.
3. Lancez les tests disponibles.
4. Vérifiez le fonctionnement de la boutique et de l'administration.
5. Soumettez les changements après vérification.

Exemple :

```bash
git checkout -b feature/amelioration-catalogue
git add .
git commit -m "Improve product catalog"
```

Avant de pousser les modifications, vérifiez la branche et le dépôt distant :

```bash
git branch --show-current
git remote -v
git status
```

## Déploiement

Drall dispose d'une démonstration hébergée à l'adresse suivante :

https://drall.up.railway.app

Pour un déploiement en production, configurez les variables d'environnement, la base de données, le stockage des fichiers, les tâches planifiées, les files d'attente éventuelles et les sauvegardes.

Paramètres de production recommandés :

```dotenv
APP_NAME=Drall
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Indian/Antananarivo
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
```

Générez et conservez la clé d'application de manière sécurisée. Ne publiez pas le fichier `.env`.

Après chaque déploiement, vérifiez :

- L'accès à la boutique.
- L'affichage des produits et des images.
- L'inscription et la connexion des clients.
- Le panier et le passage de commande.
- L'enregistrement des informations.
- L'accès au tableau de bord d'administration.
- Les journaux d'erreurs et la configuration de production.

## Sécurité

La sécurité est essentielle pour une plateforme de commerce électronique.

- Ne publiez jamais les mots de passe ni les clés API.
- Désactivez le mode debug en production.
- Maintenez les dépendances à jour après vérification de leur compatibilité.
- Vérifiez les permissions d'accès à l'administration.
- Protégez les données personnelles des clients.
- Effectuez des sauvegardes régulières.
- Testez les paiements et les commandes avant toute mise en service.

Pour signaler un problème de sécurité, contactez les responsables du projet par un canal privé approprié plutôt que de publier des informations permettant d'exploiter une vulnérabilité.

## Licence

Consultez le fichier `LICENSE` présent dans le dépôt pour connaître les conditions de redistribution, de modification et d'utilisation du code.

Drall utilise des composants et des dépendances tierces. Leurs licences et mentions de copyright doivent être conservées conformément à leurs conditions respectives.

## Contact et projet

- **Nom du projet :** Drall
- **Marché cible :** Madagascar
- **Dépôt du code source :** https://github.com/Zafra-afk/drall
- **Démonstration :** https://drallmg.up.railway.app

---

**Drall — Construisons ensemble le commerce numérique de Madagascar. 🇲🇬**
