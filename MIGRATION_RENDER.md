# Mettre toute ta base de données (et ton compte admin) sur Render

Ce guide te permet d’**exporter ta base locale** (XAMPP) puis de **l’importer sur Render** en 3 étapes.

---

## Étape 1 : Exporter ta base en local

1. Ouvre ton projet en local avec XAMPP (MySQL et Apache démarrés).
2. Dans le navigateur, va sur :
   ```
   http://localhost/ProjetClinique/export_local_db.php
   ```
   (Adapte l’URL si ton projet est dans un sous-dossier, par ex. `http://localhost/ProjetClinique/export_local_db.php`.)
3. La page affiche **« Export terminé »** et crée le fichier **`export_for_render.sql`** à la racine du projet (tables + données, y compris ton admin).

Tu as maintenant un fichier **`export_for_render.sql`** qui contient toute ta base.

---

## Étape 2 : Déployer le site sur Render (si ce n’est pas déjà fait)

1. Pousse ton code sur GitHub (y compris les nouveaux fichiers `export_local_db.php` et `import_on_render.php`).
2. Sur [dashboard.render.com](https://dashboard.render.com) :
   - Ton **Web Service** (le site) doit avoir les variables d’environnement :
     - `MYSQL_HOST` = nom de ton service MySQL (ex. `projetclinique-mysql`)
     - `MYSQL_DATABASE` = `sante1`
     - `MYSQL_USER` = ton utilisateur MySQL
     - `MYSQL_PASSWORD` = le mot de passe
     - `MYSQL_PORT` = `3306`
   - Ajoute aussi (pour sécuriser l’import) :
     - `RENDER_IMPORT_KEY` = une phrase secrète de ton choix (ex. `MaPhraseSecrete2024`)
3. Lance un **Manual Deploy** pour que le site soit à jour.

---

## Étape 3 : Importer la base sur Render

1. Ouvre cette URL (remplace par ton URL Render et ta clé) :
   ```
   https://dev-web-njqk.onrender.com/import_on_render.php?key=MaPhraseSecrete2024
   ```
   Utilise la **même** valeur que `RENDER_IMPORT_KEY` pour `key=...`.
2. Sur la page d’import :
   - Clique sur **« Choisir un fichier »** et sélectionne **`export_for_render.sql`** (celui généré à l’étape 1).
   - Clique sur **« Importer »**.
3. Quand tu vois **« Import terminé »**, ta base (et ton compte admin) est sur Render.

Ensuite, va sur **`https://dev-web-njqk.onrender.com/login.php`** et connecte-toi avec **ton compte admin** comme en local.

---

## En résumé

| Étape | Où | Action |
|-------|-----|--------|
| 1 | En local (XAMPP) | Ouvrir `export_local_db.php` → obtenir `export_for_render.sql` |
| 2 | Render | Vérifier les variables `MYSQL_*` et `RENDER_IMPORT_KEY`, redéployer si besoin |
| 3 | Render | Ouvrir `import_on_render.php?key=TA_CLE` et envoyer `export_for_render.sql` |

Après ça, ton site sur Render utilise la même base que en local, avec ton admin et toutes tes données.
