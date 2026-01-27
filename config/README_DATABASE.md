# Base de données santé1

## Installation

1. **Importer le script SQL** :
   - Ouvrez phpMyAdmin ou votre client MySQL
   - Importez le fichier `config/sante1_database.sql`
   - Ou exécutez la commande : `mysql -u root -p < config/sante1_database.sql`

2. **Vérifier la connexion** :
   - Le fichier `config/bdd.php` est déjà configuré pour utiliser la base de données `santé1`
   - Assurez-vous que MySQL est démarré et que les identifiants sont corrects

## Structure de la base de données

### Tables principales

1. **PATIENTS** - Informations des patients
2. **MEDECINS** - Informations des médecins
3. **CARNETS** - Carnets de santé des patients
4. **SERVICES** - Services médicaux proposés
5. **RENDEZ_VOUS** - Rendez-vous médicaux
6. **CONSULTATION** - Consultations médicales
7. **PAIEMENT** - Paiements des consultations
8. **ORDONNANCES** - Ordonnances médicales
9. **CONSULTATION_SERVICES** - Table de liaison (relation ternaire)
10. **users** - Utilisateurs du système (authentification)

## Relations

- Un patient peut avoir plusieurs rendez-vous, consultations et paiements
- Un médecin peut effectuer plusieurs consultations
- Une consultation nécessite un paiement (relation 1-1)
- Une consultation peut générer plusieurs ordonnances
- Une consultation est liée à un carnet de santé
- Une consultation peut être associée à plusieurs services (via la table de liaison)

## Configuration

Les paramètres de connexion sont dans `config/bdd.php` :
- Serveur : localhost
- Port : 3306
- Base de données : santé1
- Utilisateur : root
- Mot de passe : (vide par défaut)

Modifiez ces paramètres selon votre configuration MySQL.
