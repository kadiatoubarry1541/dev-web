# Migration - Ajout de la colonne chemin_reçu

## Description
Ce script ajoute la colonne `chemin_reçu` à la table `PAIEMENT` pour stocker le chemin des fichiers de reçus générés.

## Exécution de la migration

### Option 1 : Via navigateur
Accédez à : `http://localhost/ProjetClinique/config/migrate_add_chemin_reçu.php`

### Option 2 : Via ligne de commande
```bash
cd C:\xampp_new\htdocs\ProjetClinique
C:\xampp\php\php.exe config\migrate_add_chemin_reçu.php
```

### Option 3 : Via SQL direct
Exécutez le fichier SQL : `config/add_chemin_reçu_column.sql`

## Fonctionnalités ajoutées

1. **Génération automatique de reçus** : Quand un administrateur crée un paiement avec statut "payé", un reçu est automatiquement généré et envoyé dans l'espace du patient.

2. **Visualisation des reçus** : Les patients peuvent voir leurs reçus dans leur espace profil.

3. **Téléchargement des reçus** : Les reçus sont accessibles via la page `paiements/voir-reçu.php?id=ID_PAIEMENT`

## Structure des fichiers

- Les reçus sont stockés dans : `uploads/reçus/`
- Format : `recu_FACT-YYYYMMDD-XXXXXX_YYYYMMDDHHMMSS.html`

## Notes

- La migration est idempotente : elle peut être exécutée plusieurs fois sans problème
- Le dossier `uploads/reçus/` est créé automatiquement si nécessaire
- Les reçus sont générés en HTML et peuvent être imprimés directement depuis le navigateur
