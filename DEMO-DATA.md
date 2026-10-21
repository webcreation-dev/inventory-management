# Démonstration informatique et téléphonie

Boutique fictive **Tech Horizon — Cotonou**, un seul point de vente, montants FCFA. Les prix, clients, coordonnées, entreprises et opérations sont simulés. Les photos illustrent des familles de produits et ne garantissent pas le modèle exact.

## Installation

Sur une installation de démonstration vide, avec les modules choisis et un seul point de vente déjà configurés :

```bash
php artisan migrate --force
php artisan demo:seed-electronics
php tools/verify-electronics-demo.php
# Avec le serveur local démarré :
python3 tools/check-electronics-demo.py
```

En environnement production, la commande exige `--force` : à réserver à une instance de démonstration. Elle refuse une base contenant déjà des produits ou des transactions et ne supprime aucune donnée. Une nouvelle exécution avec le même identifiant ne crée pas de doublons. Sauvegarder la base avant le premier lancement ; la restauration de cette sauvegarde permet de retrouver l’état initial. Les données de démonstration ne doivent pas devenir les soldes d’ouverture d’une vraie exploitation.

## Contenu

Le seed lit les choix de Gestion des modules sans les modifier. Le scénario nécessite les produits, stocks, ventes, achats, clients et fournisseurs actifs ; il ajoute les retours, dépenses, employés et rôles uniquement si leurs modules sont cochés. Il respecte aussi les fonctionnalités devises et dénominations. Les modules désactivés ne reçoivent pas de données.

83 références réparties en neuf catégories : portables, PC fixes/écrans, téléphones, tablettes, périphériques, audio, stockage/composants, recharge/accessoires, impression/réseau. 64 clients fictifs plus le client comptoir existant, huit fournisseurs, trois employés sans connexion autorisée, deux rôles, catégories, marques, unités et configuration de caisse.

Six mois glissants jusqu’au jour d’exécution, dimanche fermé, fréquentation accrue le samedi et à la rentrée. Les approvisionnements précèdent les ventes. Les ventes comprennent règlements immédiats et crédits classiques, encaissements différés, remises et retours avec remise en stock. Les achats ont des règlements immédiats et différés. Loyer, électricité, Internet, transport et entretien alimentent les dépenses.

Les journées historiques sont clôturées ; la caisse du dernier jour reste ouverte pour essayer l’application. Les fonds de roulement de démonstration regroupent espèces et banque : ils ne représentent pas le montant physique du tiroir-caisse. Aucun compte supplémentaire n’est créé lorsque Comptes de paiement est désactivé ; les moyens existants servent uniquement aux règlements nécessaires aux ventes et achats.

Le résumé et les identifiants créés sont conservés dans `demo_datasets` et `storage/app/demo-electronics-report.json`. Les contrôles vérifient l’unicité du point de vente, les modules, les stocks historiques/finals, les montants des factures, les règlements et la continuité des journées.

## Images et crédits

La sélection est dans `database/demo/image-attributions.json` : auteur, source, licence et lien de licence pour chaque illustration. Les crédits figurent aussi dans la description des produits. Les images Commons restent soumises à leurs licences respectives, indépendamment de la licence du logiciel. La photographie du routeur provient de [TP-Link](https://www.tp-link.com/us/home-networking/wifi-router/archer-c6/), tous droits réservés, pour présentation du produit dans cette démonstration.

Les fichiers sont fournis dans `public/demo-assets/electronics` ; aucun téléchargement n’est nécessaire au lancement du seed. `tools/fetch-demo-images.py` permet de récupérer à nouveau la sélection publiée et ses métadonnées, sous réserve des limites de Wikimedia.

## Limite de l’installation récupérée

La base locale a été reconstruite sans le dump SQL de l’éditeur. La migration complémentaire restaure notamment la vue de mouvements utilisée pour le stock des produits simples de cette démonstration. Elle préserve une vue officielle déjà présente. Les contrôles de ce scénario ne constituent pas une validation de toutes les fonctions métier du logiciel.
