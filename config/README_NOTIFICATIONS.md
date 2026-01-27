# Système de Notifications

## Description

Le système de notifications permet d'envoyer automatiquement des messages aux patients pour les informer de :
- La disponibilité d'un reçu de paiement
- La confirmation d'un rendez-vous

## Installation

### 1. Créer la table NOTIFICATIONS

La table est créée automatiquement lors de la première utilisation, mais vous pouvez aussi l'exécuter manuellement :

```bash
# Via le script PHP
php config/create_notifications_table.php
```

**Note** : La table est créée automatiquement lors de la première utilisation via la fonction `createNotificationsTable()` dans `database_functions.php`.

## Fonctionnalités

### 1. Notification de Reçu de Paiement

**Quand** : Quand l'admin crée un paiement avec le statut "payé", un reçu est automatiquement généré et une notification est envoyée au patient.

**Où** : La notification apparaît dans la section "Mes Notifications" du profil patient.

**Contenu** : 
- Titre : "Reçu de paiement disponible"
- Message : Informations sur le montant et le numéro de facture
- Lien : Vers la page de visualisation du reçu

### 2. Notification de Confirmation de Rendez-vous

**Quand** : Quand un médecin confirme un rendez-vous (statut passe de "planifié" à "confirmé"), une notification est automatiquement envoyée au patient.

**Où** : La notification apparaît dans la section "Mes Notifications" du profil patient.

**Contenu** :
- Titre : "Rendez-vous confirmé"
- Message : Date, heure, médecin et service concernés
- Lien : Vers la page des rendez-vous

## Utilisation

### Pour les Patients

1. **Voir les notifications** : Connectez-vous et allez dans votre profil. La section "Mes Notifications" affiche toutes vos notifications.

2. **Marquer comme lu** : Cliquez sur "Marquer lu" pour marquer une notification comme lue.

3. **Accéder au contenu** : Cliquez sur "Voir" pour accéder directement au reçu ou au rendez-vous concerné.

### Pour les Administrateurs

Les notifications sont créées automatiquement :
- Lors de la création d'un paiement (via `paiements/creer-paiement.php`)
- Aucune action supplémentaire n'est requise

### Pour les Médecins

Les notifications sont créées automatiquement :
- Lors de la confirmation d'un rendez-vous (via `medecin/approuver-rdv.php`)
- Aucune action supplémentaire n'est requise

## Structure de la Base de Données

### Table NOTIFICATIONS

```sql
CREATE TABLE NOTIFICATIONS (
    id_notification INT AUTO_INCREMENT PRIMARY KEY,
    id_patient INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('paiement', 'rendez_vous', 'consultation', 'autre') DEFAULT 'autre',
    lien VARCHAR(500) NULL,
    lu TINYINT(1) DEFAULT 0,
    Date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_patient) REFERENCES PATIENTS(id_patient)
);
```

## Fonctions PHP Disponibles

### Créer une notification

```php
creerNotification($id_patient, $titre, $message, $type = 'autre', $lien = null);
```

### Récupérer les notifications d'un patient

```php
getNotificationsByPatient($id_patient, $non_lues_seulement = false);
```

### Marquer une notification comme lue

```php
marquerNotificationLue($id_notification);
```

### Compter les notifications non lues

```php
countNotificationsNonLues($id_patient);
```

## Fichiers Modifiés

1. `config/database_functions.php` :
   - Ajout des fonctions de gestion des notifications
   - Modification de `genererReçu()` pour envoyer une notification
   - Modification de `updateStatutRendezVous()` pour envoyer une notification

2. `profil.php` :
   - Ajout de la section "Mes Notifications"
   - Affichage des notifications avec badge de compteur

3. `config/create_notifications_table.php` :
   - Script pour créer la table NOTIFICATIONS (utilise la fonction `createNotificationsTable()`)

## Notes Techniques

- La table est créée automatiquement lors de la première utilisation si elle n'existe pas
- Les notifications sont triées par date (plus récentes en premier)
- Les notifications non lues sont mises en évidence visuellement
- Le système est compatible avec la structure existante de la base de données
