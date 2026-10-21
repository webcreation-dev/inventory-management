# VPS Ubuntu — accès par IPv4 et port 5000

Exécuter `install-vps.sh` en root avec `INVENTORY_IP` défini sur l’IPv4 publique du serveur. PHP 8.2, Composer, Nginx et MySQL doivent déjà être disponibles. Le script installe FPM et les extensions PHP depuis les dépôts APT configurés.

Le script refuse de remplacer une installation existante ou d’occuper un port 5000 déjà utilisé. Il crée la base `inventory_management`, le compte MySQL local `inventory_app`, un site Nginx séparé et un mot de passe administrateur aléatoire enregistré dans `/root/inventory-admin.txt`. Il conserve les autres sites Nginx. Les identifiants MySQL sont stockés dans `.env` avec des permissions restreintes.

Cette procédure initialise une nouvelle base. Elle active explicitement le schéma reconstruit avec `ALLOW_RECOVERED_SCHEMA=true`. Les ventes, achats, taxes et clôtures complètes restent à valider. HTTP sur une IP n’est pas chiffré ; utiliser un domaine et HTTPS avant un usage réel.

Si une commande échoue, le script s’arrête ; examiner l’erreur avant toute nouvelle tentative. Il ne supprime pas les ressources déjà créées.

Configuration Nginx basée sur les instructions de déploiement Laravel 11 : https://laravel.com/docs/11.x/deployment. La racine HTTP est `public/` ; seul le point d’entrée `index.php` est transmis à PHP-FPM. Les URL historiques `/public/` sont réécrites pour servir les styles correctement.

## Copier l’état exact du Mac vers le VPS

`deploy/snapshot.php` transfère la base et les médias sans lancer de seed. Les réglages, modules cochés, utilisateurs et opérations proviennent donc du même instantané local. L’archive est privée : ne pas la placer dans le dépôt Git ou dans `public/`.

Sur le Mac, depuis la racine du projet :

```bash
php deploy/snapshot.php export /tmp/inventory-current.tar.gz
scp /tmp/inventory-current.tar.gz root@213.136.81.100:/root/
```

Sur le VPS, connecté en root :

```bash
cd /var/www/inventory-management
git pull --ff-only origin main &&
php8.2 deploy/snapshot.php restore /root/inventory-current.tar.gz
```

L’import **remplace les tables présentes dans l’instantané** : les modifications réalisées uniquement en ligne sont remplacées. Une sauvegarde de la base, des médias et du `.env` est créée dans `/root/inventory-backups/DATE-ID/` avant l’import. Le script vérifie les empreintes de l’archive et les nombres de lignes. En cas d’échec après la sauvegarde, il tente de restaurer la base et les médias précédents ; si cette restauration échoue, il garde le site en maintenance et affiche le dossier de sauvegarde.

La configuration serveur `.env`, la clé d’application et les identifiants administrateur du VPS restent ceux du serveur. Le mot de passe local de démonstration n’est pas appliqué à l’administrateur en ligne. Les sessions et files d’attente locales ne sont pas reprises ; les sessions serveur sont invalidées pour recharger les nouveaux paramètres à la reconnexion. Une courte maintenance protège le transfert, puis la page de connexion sur le port 5000 est contrôlée.

Conserver l’archive et les sauvegardes dans leurs emplacements privés jusqu’à validation du résultat. Aucun export SQL n’est publié sur GitHub.
