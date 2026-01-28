# Une seule table patient : PATIENTS (avec S)

## Problème

L’erreur **« Patient introuvable en base »** peut venir du fait qu’il existe **deux tables** pour les patients :

- `patient` (sans **s**)
- `PATIENTS` (avec **s**)

L’application utilise **uniquement** la table **PATIENTS**.  
Si les données sont dans `patient` ou si les deux tables coexistent, les recherches côté app échouent.

## Solution : une seule table, PATIENTS

On ne garde que **PATIENTS** (avec **s**) et on supprime l’autre.

### Option 1 : script dédié (recommandé)

1. Ouvrir dans le navigateur :  
   `http://votre-site/config/unifier_table_patients.php`
2. Lire le résumé (quelle table existe, combien de lignes).
3. Cliquer sur le lien **« Confirmer : migrer vers PATIENTS et supprimer patient »**  
   ou aller à :  
   `http://votre-site/config/unifier_table_patients.php?confirmer=oui`

Le script :

- copie les lignes de `patient` vers `PATIENTS` si besoin ;
- supprime la table `patient` ;
- laisse uniquement **PATIENTS** pour l’application.

### Option 2 : script général des doublons

Le script `nettoyer_tables_doublons.php` fait la même chose pour **patient/PATIENTS**, **medecin/MEDECINS** et **ordonnance/ORDONNANCES**.

- En ligne de commande :  
  `php config/nettoyer_tables_doublons.php`  
  puis répondre `oui` quand demandé.
- Ou dans le navigateur :  
  `http://votre-site/config/nettoyer_tables_doublons.php?confirmer=oui`

## Après l’unification

- Toute la base ne doit plus utiliser que **PATIENTS** (avec **s**).
- Les requêtes du projet pointent déjà sur **PATIENTS** ; aucune modification de code n’est nécessaire une fois la table doublon supprimée et les données éventuellement migrées.

## Sauvegarde

Faire une sauvegarde de la base de données avant de lancer un des deux scripts.
