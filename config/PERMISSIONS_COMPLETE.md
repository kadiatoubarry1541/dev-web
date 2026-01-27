# Système de Permissions Complet - MediCo.

## Vue d'ensemble

Ce document définit **exactement** ce que chaque type d'utilisateur peut faire dans le système. Chaque rôle a des permissions strictement définies et vérifiées.

---

## 1. ADMINISTRATEUR (admin)

### Rôle
**Super-utilisateur** : Contrôle total sur le système

### Permissions Complètes
- ✅ **view_all** : Voir toutes les données du système
- ✅ **manage_medecins** : Créer, modifier, supprimer, approuver les médecins
- ✅ **manage_patients** : Créer, modifier, supprimer les patients
- ✅ **manage_services** : Créer, modifier, supprimer les services
- ✅ **manage_rendez_vous** : Gérer tous les rendez-vous (voir, modifier, supprimer)
- ✅ **create_rendez_vous** : Créer des rendez-vous pour n'importe quel patient
- ✅ **manage_consultations** : Gérer toutes les consultations
- ✅ **view_consultations** : Voir toutes les consultations
- ✅ **manage_ordonnances** : Gérer toutes les ordonnances
- ✅ **view_ordonnances** : Voir toutes les ordonnances
- ✅ **view_reports** : Voir tous les rapports et statistiques
- ✅ **manage_users** : Gérer tous les comptes utilisateurs
- ✅ **approve_rendez_vous** : Approuver/confirmer n'importe quel rendez-vous
- ✅ **create_ordonnances** : Créer des ordonnances pour n'importe quel patient
- ✅ **manage_paiements** : Gérer tous les paiements
- ✅ **view_paiements** : Voir tous les paiements
- ✅ **send_receipts** : Envoyer des reçus aux patients

### Accès
- `/admin/index.php` - Tableau de bord
- Toutes les pages d'administration

### Restrictions
- Aucune restriction

---

## 2. MÉDECIN (medecin)

### Rôle
**Spécialiste médical** : Gère les patients de son service uniquement

### Permissions Complètes
- ❌ **view_all** : Ne peut pas voir toutes les données
- ❌ **manage_medecins** : Ne peut pas gérer les médecins
- ❌ **manage_patients** : Ne peut pas créer/modifier des patients (réservé à l'accueil)
- ✅ **view_patients** : Peut voir les patients de son service uniquement
- ❌ **manage_services** : Ne peut pas gérer les services
- ✅ **manage_rendez_vous** : Peut gérer les rendez-vous de son service uniquement
- ✅ **create_rendez_vous** : Peut créer des rendez-vous pour les patients de son service
- ✅ **manage_consultations** : Peut gérer les consultations de son service
- ✅ **view_consultations** : Peut voir les consultations de son service
- ✅ **manage_ordonnances** : Peut créer des ordonnances pour ses patients
- ✅ **view_ordonnances** : Peut voir les ordonnances de son service
- ❌ **view_reports** : Ne peut pas voir les rapports globaux
- ❌ **manage_users** : Ne peut pas gérer les utilisateurs
- ✅ **approve_rendez_vous** : Peut approuver les rendez-vous de son service
- ✅ **create_ordonnances** : Peut créer des ordonnances
- ❌ **manage_paiements** : Ne peut pas gérer les paiements (réservé à l'accueil)
- ✅ **view_paiements** : Peut voir les paiements de ses consultations
- ✅ **send_receipts** : Peut envoyer des reçus aux patients

### Accès
- `/medecin/index.php` - Tableau de bord médecin
- `/medecin/mes-patients.php` - Ses patients uniquement
- `/medecin/mes-rendez-vous.php` - Ses rendez-vous uniquement
- `/medecin/mes-consultations.php` - Ses consultations uniquement
- `/medecin/mes-ordonnances.php` - Ses ordonnances uniquement
- `/medecin/creer-ordonnance.php` - Créer des ordonnances
- `/medecin/approuver-rdv.php` - Approuver les rendez-vous de son service

### Restrictions Importantes
- **Filtrage par service** : Un médecin ne voit QUE les données de son service (spécialisation)
- **Médecins en attente** : Les médecins non approuvés ont des permissions limitées
- **Pas de création de patients** : Seul l'accueil peut créer des patients

### Types de Médecins (4 services)
1. **Médecine générale** : Service "Consultation générale"
2. **Chirurgie** : Service "Chirurgie"
3. **Maternité** : Service "Maternité"
4. **Ophtalmologie** : Service "Ophtalmologie"

Chaque médecin est filtré par sa `Spécialisation_med` qui correspond au `Nom_service`.

---

## 3. PATIENT (patient)

### Rôle
**Utilisateur final** : Accès limité à ses propres données

### Permissions Complètes
- ❌ **view_all** : Ne peut pas voir toutes les données
- ❌ **manage_medecins** : Ne peut pas gérer les médecins
- ❌ **manage_patients** : Ne peut pas gérer les patients
- ❌ **manage_services** : Ne peut pas gérer les services
- ❌ **manage_rendez_vous** : Ne peut pas gérer tous les rendez-vous
- ✅ **create_rendez_vous** : Peut créer ses propres rendez-vous
- ❌ **manage_consultations** : Ne peut pas gérer les consultations
- ✅ **view_consultations** : Peut voir ses propres consultations uniquement
- ❌ **manage_ordonnances** : Ne peut pas créer des ordonnances
- ✅ **view_ordonnances** : Peut voir ses propres ordonnances uniquement
- ❌ **view_reports** : Ne peut pas voir les rapports
- ❌ **manage_users** : Ne peut pas gérer les utilisateurs
- ❌ **approve_rendez_vous** : Ne peut pas approuver les rendez-vous
- ❌ **create_ordonnances** : Ne peut pas créer des ordonnances
- ❌ **manage_paiements** : Ne peut pas gérer les paiements
- ✅ **view_paiements** : Peut voir ses propres paiements uniquement
- ✅ **view_receipts** : Peut voir et lire ses propres reçus

### Accès
- `/profil.php` - Son profil
- `/rendez-vous.php` - Prendre rendez-vous
- `/mes-ordonnances.php` - Voir ses ordonnances
- `/paiements/liste-paiements.php` - Voir ses paiements (filtrés)

### Restrictions Importantes
- **Données personnelles uniquement** : Un patient ne voit QUE ses propres données
- **Pas de création** : Ne peut pas créer d'ordonnances, consultations, etc.
- **Lecture seule** : Peut seulement consulter et créer des rendez-vous

---

## 4. ACCUEIL (accueil)

### Rôle
**Agent d'accueil** : Gère l'inscription des patients et les paiements

### Permissions Complètes
- ❌ **view_all** : Ne peut pas voir toutes les données
- ❌ **manage_medecins** : Ne peut pas gérer les médecins
- ✅ **manage_patients** : Peut créer et modifier les patients
- ✅ **view_patients** : Peut voir les patients
- ❌ **manage_services** : Ne peut pas gérer les services
- ❌ **manage_rendez_vous** : Ne peut pas gérer tous les rendez-vous
- ✅ **create_rendez_vous** : Peut créer des rendez-vous pour les patients
- ❌ **manage_consultations** : Ne peut pas gérer les consultations
- ❌ **view_consultations** : Ne peut pas voir les consultations
- ❌ **manage_ordonnances** : Ne peut pas gérer les ordonnances
- ❌ **view_ordonnances** : Ne peut pas voir les ordonnances
- ❌ **view_reports** : Ne peut pas voir les rapports
- ❌ **manage_users** : Ne peut pas gérer les utilisateurs
- ❌ **approve_rendez_vous** : Ne peut pas approuver les rendez-vous
- ❌ **create_ordonnances** : Ne peut pas créer des ordonnances
- ✅ **manage_paiements** : Peut créer et gérer les paiements
- ✅ **view_paiements** : Peut voir les paiements
- ✅ **send_receipts** : Peut envoyer des reçus aux patients

### Accès
- `/accueil/index.php` - Interface d'accueil
- `/rendez-vous.php` - Créer des rendez-vous pour les patients
- `/paiements/creer-paiement.php` - Créer des paiements

### Restrictions Importantes
- **Pas de consultations** : Ne peut pas créer de consultations ou ordonnances
- **Pas d'approbation** : Ne peut pas approuver les rendez-vous (réservé aux médecins)
- **Focus patients** : Son rôle principal est la gestion des patients et paiements

---

## Règles de Sécurité Globales

### 1. Vérification des Permissions
Toutes les pages sensibles doivent utiliser :
```php
requirePermission('nom_permission');
// ou
requireRole('nom_role');
```

### 2. Filtrage par Service (Médecins)
Les médecins doivent être filtrés par leur spécialisation :
```php
// Un médecin ne voit que les données de son service
WHERE m.Spécialisation_med = ? AND r.id_service = ?
```

### 3. Filtrage par Patient
Les patients ne voient que leurs propres données :
```php
// Un patient ne voit que ses données
WHERE p.id_patient = ?
```

### 4. Médecins en Attente
Les médecins non approuvés ont des permissions limitées :
- Ne peuvent pas voir les patients
- Ne peuvent pas gérer les rendez-vous
- Ne peuvent pas créer d'ordonnances
- Ne peuvent pas voir les paiements

---

## Matrice des Permissions

| Permission | Admin | Médecin | Patient | Accueil |
|------------|-------|---------|---------|---------|
| view_all | ✅ | ❌ | ❌ | ❌ |
| manage_medecins | ✅ | ❌ | ❌ | ❌ |
| manage_patients | ✅ | ❌ | ❌ | ✅ |
| view_patients | ✅ | ✅* | ❌ | ✅ |
| manage_services | ✅ | ❌ | ❌ | ❌ |
| manage_rendez_vous | ✅ | ✅* | ❌ | ❌ |
| create_rendez_vous | ✅ | ✅* | ✅ | ✅ |
| manage_consultations | ✅ | ✅* | ❌ | ❌ |
| view_consultations | ✅ | ✅* | ✅** | ❌ |
| manage_ordonnances | ✅ | ✅* | ❌ | ❌ |
| view_ordonnances | ✅ | ✅* | ✅** | ❌ |
| view_reports | ✅ | ❌ | ❌ | ❌ |
| manage_users | ✅ | ❌ | ❌ | ❌ |
| approve_rendez_vous | ✅ | ✅* | ❌ | ❌ |
| create_ordonnances | ✅ | ✅* | ❌ | ❌ |
| manage_paiements | ✅ | ❌ | ❌ | ✅ |
| view_paiements | ✅ | ✅* | ✅** | ✅ |
| send_receipts | ✅ | ✅* | ❌ | ✅ |

* = Uniquement pour son service  
** = Uniquement ses propres paiements

---

## Vérifications à Faire

1. ✅ Toutes les pages admin vérifient `requireAdmin()`
2. ✅ Toutes les pages médecin vérifient `requireMedecin()`
3. ✅ Toutes les pages accueil vérifient `requireAccueil()`
4. ✅ Les médecins sont filtrés par service dans toutes les requêtes
5. ✅ Les patients ne voient que leurs propres données
6. ✅ Les médecins en attente ont des permissions limitées

---

## Notes Importantes

- **Séparation des rôles** : Chaque rôle a un objectif précis et ne peut pas empiéter sur les autres
- **Sécurité** : Toutes les vérifications sont faites côté serveur (PHP)
- **Filtrage automatique** : Les données sont automatiquement filtrées selon le rôle
- **Permissions granulaires** : Chaque action a sa propre permission
