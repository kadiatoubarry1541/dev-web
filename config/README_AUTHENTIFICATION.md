# Système d'Authentification - MediCo.

## Fonctionnalités

Le système d'authentification permet à chaque utilisateur de :
- ✅ Créer un compte personnel
- ✅ Se connecter à son compte
- ✅ Gérer son profil
- ✅ Voir ses rendez-vous
- ✅ Se déconnecter

## Pages créées

### 1. **register.php** - Inscription
- Formulaire d'inscription complet
- Création automatique d'un compte utilisateur ET d'un patient dans la base de données
- Validation des données (email unique, mot de passe minimum 6 caractères)
- Hashage sécurisé des mots de passe
- Redirection automatique vers la page de connexion après inscription

### 2. **login.php** - Connexion
- Formulaire de connexion sécurisé
- Vérification email/mot de passe
- Création de session utilisateur
- Redirection vers la page d'accueil après connexion
- Protection : redirige si déjà connecté

### 3. **profil.php** - Mon Profil
- Affichage des informations personnelles
- Modification du profil (nom, téléphone, adresse, date de naissance)
- Liste des rendez-vous du patient
- Accès rapide pour prendre un nouveau rendez-vous
- Protection : nécessite d'être connecté

### 4. **deconnexion.php** - Déconnexion
- Destruction de la session
- Redirection vers la page d'accueil

## Système de Session

### Fichier : `config/session.php`

Fonctions disponibles :
- `estConnecte()` - Vérifie si l'utilisateur est connecté
- `getUserInfo()` - Récupère les informations de l'utilisateur connecté
- `requireLogin()` - Redirige vers login si non connecté
- `requireLogout()` - Redirige si déjà connecté

## Intégration dans le Menu

Le menu de navigation (`partials/entete.php`) affiche maintenant :
- **Si connecté** : "Bienvenue, [Nom]" avec liens vers Profil et Déconnexion
- **Si non connecté** : Liens vers Connexion et Inscription

## Base de Données

### Table `users`
- Stocke les comptes utilisateurs
- Liée à la table `PATIENTS` via `id_patient`
- Mots de passe hashés avec `password_hash()`

### Table `PATIENTS`
- Créée automatiquement lors de l'inscription
- Contient les informations médicales du patient
- Matricule généré automatiquement (format: PATYYYYMMDD####)

## Sécurité

- ✅ Mots de passe hashés avec `password_hash()` (algorithme bcrypt)
- ✅ Protection contre les injections SQL (requêtes préparées)
- ✅ Validation des données côté serveur
- ✅ Gestion sécurisée des sessions
- ✅ Protection CSRF (à améliorer si nécessaire)

## Utilisation

### Pour créer un compte :
1. Aller sur `register.php`
2. Remplir le formulaire (nom, email, téléphone, mot de passe)
3. Cliquer sur "Créer mon compte"
4. Redirection automatique vers `login.php`

### Pour se connecter :
1. Aller sur `login.php`
2. Entrer email et mot de passe
3. Cliquer sur "Se connecter"
4. Redirection vers `index.php`

### Pour accéder au profil :
1. Se connecter
2. Cliquer sur "Mon Profil" dans le menu
3. Ou aller directement sur `profil.php`

## Améliorations futures possibles

- [ ] Réinitialisation de mot de passe par email
- [ ] Confirmation d'email
- [ ] Double authentification
- [ ] Historique des connexions
- [ ] Gestion des rôles (admin, médecin, patient)
