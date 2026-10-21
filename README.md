# Inventory Management

Application de gestion de stock et de ventes basée sur Laravel 11 et PHP 8.2.

## Dépendances

- PHP 8.2 avec PDO MySQL, mbstring, OpenSSL, cURL, ZIP, GD et les extensions requises par Composer.
- Composer et MySQL.
- Node.js uniquement pour reconstruire les ressources Vite.

## Installation

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Configurer la connexion MySQL dans `.env` avant toute migration. Voir [LOCAL-SETUP.md](LOCAL-SETUP.md) pour les particularités de la reconstruction locale et ses limites. Les identifiants, les bases locales, les fichiers téléversés et les dépendances installées ne sont pas versionnés.

Le script `./start-local.sh` utilise les chemins Homebrew du Mac de développement et une instance MySQL dédiée au port 3307. Il nécessite une base locale déjà initialisée.

## État de la reconstruction

Les tables métier ont été reconstruites depuis le code fourni. La connexion, le tableau de bord, les pages produits et stocks et un test de persistance produit ont été vérifiés. Les opérations complètes de vente, achat et clôture ne sont pas encore validées. Ce schéma ne remplace pas le SQL officiel de l’éditeur pour une mise en production.

## Interface client

L’interface utilise le français pour les sessions existantes et les nouvelles connexions. Le menu **Gestion des modules** est réservé aux utilisateurs autorisés à modifier les rôles ; les choix sont enregistrés par entreprise. Les modules désactivés sont masqués dans le menu et la recherche, leurs chemins sont bloqués et leurs données sont conservées. Accueil, Points de vente, Paramètres et Déconnexion restent accessibles selon les permissions pour éviter un blocage. Voir [le guide des modules](MODULES.md).

Vérifications locales :

```sh
python3 tools/audit-french.py
php tools/check-client-modules.php
python3 tools/check-client-interface.py
```

Le dernier test utilise le serveur local sur le port 8000. Ses accès peuvent être fournis via `INVENTORY_TEST_EMAIL` et `INVENTORY_TEST_PASSWORD`. Les tests de modules restaurent leurs modifications ; le test PHP nécessite l’environnement local.
