# VPS Ubuntu — accès par IPv4 et port 5000

Exécuter `install-vps.sh` en root avec `INVENTORY_IP` défini sur l’IPv4 publique du serveur. PHP 8.2, Composer, Nginx et MySQL doivent déjà être disponibles. Le script installe FPM et les extensions PHP depuis les dépôts APT configurés.

Le script refuse de remplacer une installation existante ou d’occuper un port 5000 déjà utilisé. Il crée la base `inventory_management`, le compte MySQL local `inventory_app`, un site Nginx séparé et un mot de passe administrateur aléatoire enregistré dans `/root/inventory-admin.txt`. Il conserve les autres sites Nginx. Les identifiants MySQL sont stockés dans `.env` avec des permissions restreintes.

Cette procédure initialise une nouvelle base. Elle active explicitement le schéma reconstruit avec `ALLOW_RECOVERED_SCHEMA=true`. Les ventes, achats, taxes et clôtures complètes restent à valider. HTTP sur une IP n’est pas chiffré ; utiliser un domaine et HTTPS avant un usage réel.

Si une commande échoue, le script s’arrête ; examiner l’erreur avant toute nouvelle tentative. Il ne supprime pas les ressources déjà créées.

Configuration Nginx basée sur les instructions de déploiement Laravel 11 : https://laravel.com/docs/11.x/deployment. La racine HTTP est `public/` ; seul le point d’entrée `index.php` est transmis à PHP-FPM. Les URL historiques `/public/` sont réécrites pour servir les styles correctement.
