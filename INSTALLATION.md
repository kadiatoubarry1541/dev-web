# Guide d'Installation - MediCo.

## Installation de la Base de Données

### Méthode 1 : Installation Automatique (Recommandée)

1. **Ouvrez votre navigateur** et accédez à :
   ```
   http://localhost/ProjetClinique/install.php
   ```

2. **Cliquez sur le bouton** "Installer / Réinitialiser la Base de Données"

3. **Attendez** que le message de succès s'affiche

4. **C'est terminé !** Vous pouvez maintenant utiliser le site.

### Méthode 2 : Installation Manuelle via phpMyAdmin

1. **Ouvrez phpMyAdmin** : `http://localhost/phpmyadmin`

2. **Cliquez sur "Importer"** dans le menu supérieur

3. **Sélectionnez le fichier** : `config/sante1_database.sql`

4. **Cliquez sur "Exécuter"**

5. **Vérifiez** que toutes les tables ont été créées

### Méthode 3 : Installation via Ligne de Commande

```bash
cd C:\xampp_new\htdocs\ProjetClinique
mysql -u root -p < config/sante1_database.sql
```

## Vérification

Après l'installation, vérifiez que les tables suivantes existent :

- ✅ PATIENTS
- ✅ MEDECINS
- ✅ CARNETS
- ✅ SERVICES
- ✅ RENDEZ_VOUS
- ✅ CONSULTATION
- ✅ PAIEMENT
- ✅ ORDONNANCES
- ✅ CONSULTATION_SERVICES
- ✅ users

## Résolution de Problèmes

### Erreur : "Table doesn't exist"

**Solution :** Exécutez `install.php` ou importez manuellement le fichier SQL.

### Erreur : "Access denied"

**Solution :** Vérifiez les identifiants dans `config/bdd.php` :
- Utilisateur : root
- Mot de passe : (vide par défaut)
- Modifiez si nécessaire

### Erreur : "Can't connect to MySQL server"

**Solution :** 
1. Vérifiez que MySQL est démarré dans XAMPP
2. Vérifiez que le port 3306 est libre
3. Vérifiez les paramètres dans `config/bdd.php`

## Données d'Exemple

Le script SQL inclut automatiquement :
- 4 médecins (Dr. Sophie Laurent, Dr. Marc Dubois, Dr. Julie Moreau, Dr. Thomas Renaud)
- 4 services (Consultation générale, Maternité, Chirurgie, Ophtalmologie)

## Sécurité

⚠️ **Important :** Après l'installation, supprimez le fichier `install.php` pour des raisons de sécurité en production.
