# Installation locale de récupération

Démarrer depuis ce dossier avec `./start-local.sh`, puis ouvrir http://127.0.0.1:8000/login.
PHP 8.2 est utilisé explicitement. MySQL utilise le port 3307 et le dossier `storage/mysql-local`, séparé des autres bases du Mac.

Cette installation utilise un schéma inféré du code, pas le SQL officiel de l’éditeur. Elle est réservée à l’environnement local. L’indicateur APP_INSTALLED a été activé uniquement après création des tables et des données initiales ; aucun justificatif d’achat ni résultat de validation n’a été fabriqué.

## Fichiers de récupération

- `tools/rebuild_local_schema.py` génère `database/local-recovery-schema.json` à partir des modèles, contrôleurs et services. Les types et contraintes ne peuvent pas être récupérés intégralement depuis ces sources.
- La migration `2026_10_21_000001_recover_local_business_tables.php` crée les tables absentes, sans effacer les tables existantes.
- `php tools/local-setup.php` complète les champs absents et initialise les paramètres livrés avec le projet. Utiliser PHP 8.2 ; ce script nécessite MySQL. Ce script remet le nom de boutique et certains paramètres locaux, ne pas le réexécuter après personnalisation sans le relire.
- `php tools/check-local-stock.php` vérifie la persistance catégorie/unité/produit dans une transaction annulée.

## Vérifications effectuées

Connexion administrateur, sélection du point de vente, tableau de bord, liste et formulaire produits, page stock : HTTP 200. Les écrans achat/vente redirigent vers l’ouverture de caisse prévue par l’application. Persistance d’une catégorie, d’une unité et d’un produit vérifiée puis annulée.

Les transactions complètes de vente/achat, les calculs de valorisation, les variantes, les taxes et l’ensemble des modules ne sont pas validés. Ne pas utiliser cette reconstruction comme base comptable ou comme installation de production sans récupérer le SQL officiel et comparer les contraintes, index, types et valeurs initiales.
