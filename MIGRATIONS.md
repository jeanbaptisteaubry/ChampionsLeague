Mises à jour SQL (migrations)
================================

Principe
--------
- La base contient une table `version` qui stocke la version courante (entier).
- Les scripts de mise à jour sont des fichiers `sql/migrations/updateN.sql` (N entier croissant).
- Le script CLI `update.php` lit la version courante et applique, dans l'ordre, tous les `updateN.sql` dont `N` est strictement supérieur à la version de la base.

Utilisation
-----------
1) Placer vos scripts de migration dans `sql/migrations/` en les nommant `update1.sql`, `update2.sql`, etc.
2) Exécuter le script:

   - `php update.php`        → applique tout jusqu'à la dernière version disponible
   - `php update.php --dry-run` → affiche le plan sans exécuter
   - `php update.php --target=5` → applique seulement jusqu'à la version 5

Règles d'écriture des scripts
-----------------------------
- Séparer les instructions par `;` en fin de ligne.
- Éviter l'usage de `DELIMITER` (directive du client MySQL) — le moteur exécute les instructions une par une.
- Les triggers/procédures peuvent être utilisées si leur définition ne contient pas de `;` non protégés. Sinon, placez-les dans un fichier dédié et adaptez si besoin.
- Les commentaires `-- ...`, `# ...` et `/* ... */` sont acceptés.

Version initiale
----------------
- Si la table `version` n'existe pas, `update.php` la crée et initialise la version à `0`.
- Une base fraîchement installée via `install.php` peut être amenée à jour en lançant `php update.php`.

Bonnes pratiques
----------------
- Préfixer chaque migration par des DDL idempotents lorsque possible (ex: `ADD COLUMN` avec test d'existence si nécessaire via `INFORMATION_SCHEMA`).
- Ajouter des `CREATE INDEX`/`DROP INDEX` cohérents avec vos modifications de schéma.
- Tester sur un dump de préproduction avant d'exécuter en production.

