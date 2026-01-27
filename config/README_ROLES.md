# Système de Rôles et Permissions - MediCo.

## Types d'Utilisateurs

Le système gère actuellement **3 rôles principaux** avec des permissions spécifiques :

### 1. **Administrateur (admin)**
- **Accès complet** : Voit et gère tout sur le site
- **Permissions** :
  - ✅ Gérer les médecins (créer, modifier, supprimer)
  - ✅ Gérer les patients
  - ✅ Gérer les services
  - ✅ Gérer tous les rendez-vous
  - ✅ Gérer les consultations
  - ✅ Gérer les ordonnances
  - ✅ Voir les rapports et statistiques
  - ✅ Gérer les utilisateurs
  - ✅ Approuver les rendez-vous
  - ✅ Créer des ordonnances

**Page d'accès** : `/admin/index.php`

### 2. **Médecin (medecin)**
- **Gestion de son service** : Gère les patients de sa spécialité
- **Permissions** :
  - ✅ Gérer les patients de son service
  - ✅ Gérer les rendez-vous de son service
  - ✅ Gérer les consultations
  - ✅ Approuver les rendez-vous
  - ✅ Créer des ordonnances pour ses patients
  - ❌ Ne peut pas gérer les médecins
  - ❌ Ne peut pas voir tous les patients
  - ❌ Ne peut pas gérer les services

**Page d'accès** : `/medecin/index.php`

**Note** : Les médecins peuvent avoir différentes spécialisations (Médecine générale, Chirurgie, Maternité, Ophtalmologie, etc.), ce qui crée des "sous-types" de médecins selon leur spécialité.

### 3. **Patient (patient)**
- **Consommateur simple** : Accès limité à ses propres données
- **Permissions** :
  - ✅ Voir son propre profil
  - ✅ Prendre des rendez-vous
  - ✅ Voir ses propres rendez-vous
  - ✅ Voir ses propres consultations
  - ✅ Voir ses propres ordonnances
  - ❌ Ne peut pas gérer les autres utilisateurs
  - ❌ Ne peut pas approuver les rendez-vous
  - ❌ Ne peut pas créer des ordonnances

**Page d'accès** : `/profil.php`

## Sécurité de Connexion

### Vérifications Strictes

1. **Vérification de l'existence du compte** :
   - La fonction `connexion()` vérifie d'abord si l'email existe dans la base de données
   - Si l'email n'existe pas, la connexion est refusée immédiatement
   - Aucune information n'est divulguée sur l'existence ou non du compte

2. **Vérification du mot de passe** :
   - Utilisation de `password_verify()` pour comparer le mot de passe
   - Les mots de passe sont hashés avec `password_hash()` (bcrypt)

3. **Vérification de session** :
   - La fonction `getUserInfo()` vérifie que le compte existe toujours dans la base de données
   - Si le compte a été supprimé, la session est automatiquement détruite

4. **Protection des pages** :
   - `requireLogin()` : Redirige si non connecté
   - `requireRole()` : Redirige si n'a pas le bon rôle
   - `requirePermission()` : Redirige si n'a pas la permission

## Utilisation

### Pour vérifier un rôle :
```php
require_once 'config/permissions.php';

if (isAdmin()) {
    // Code pour admin
}

if (isMedecin()) {
    // Code pour médecin
}

if (isPatient()) {
    // Code pour patient
}
```

### Pour vérifier une permission :
```php
if (hasPermission('manage_medecins')) {
    // L'utilisateur peut gérer les médecins
}

if (hasPermission('approve_rendez_vous')) {
    // L'utilisateur peut approuver les rendez-vous
}
```

### Pour protéger une page :
```php
// Seuls les admins peuvent accéder
requireAdmin();

// Seuls les médecins peuvent accéder
requireMedecin();

// Vérifier une permission spécifique
requirePermission('manage_ordonnances');
```

## Compte Administrateur

- **Email** : `kadiatou1541.kb@gmail.com`
- **Mot de passe** : `12345@`
- **Création** : Automatique lors de l'exécution de `install.php`

## Structure des Fichiers

```
config/
  ├── permissions.php      # Système de permissions
  ├── session.php          # Gestion des sessions
  └── traitement.php       # Fonctions d'authentification

admin/
  ├── index.php           # Tableau de bord admin
  └── stats.php           # API statistiques

medecin/
  ├── index.php           # Tableau de bord médecin
  └── approuver-rdv.php   # Approuver un RDV
```

## Améliorations Futures

- [ ] Ajouter un champ `actif` pour désactiver des comptes
- [ ] Historique des connexions
- [ ] Gestion des permissions granulaires
- [ ] Support pour d'autres rôles (secrétaire, pharmacien, etc.)
