# Flux Complet de Prise de Rendez-vous - MediCo.

## Processus Complet

### 1. LE PATIENT PREND UN RENDEZ-VOUS

#### A. Patient Connecté (Recommandé)
1. Le patient se connecte à son compte
2. Va sur la page `/rendez-vous.php`
3. **Ses informations sont automatiquement détectées** (nom, matricule)
4. Il remplit uniquement :
   - Service (ex: Consultation générale, Maternité, etc.)
   - Médecin (filtré automatiquement par service)
   - Date et heure (format: jj/mm/aaaa hh:mm)
   - Motif (optionnel)
5. Clique sur "Réserver le rendez-vous"
6. **Le rendez-vous est créé avec le statut "planifié"**

#### B. Patient Non Connecté
1. Le patient va sur `/rendez-vous.php`
2. Il doit d'abord rechercher son compte par matricule
3. Une fois trouvé, il remplit les mêmes champs (service, médecin, date, motif)
4. Clique sur "Réserver le rendez-vous"
5. **Le rendez-vous est créé avec le statut "planifié"**

---

### 2. LE MÉDECIN VOIT LA DEMANDE

1. Le médecin se connecte à son compte
2. Va sur `/medecin/mes-rendez-vous.php`
3. **Il voit TOUS les rendez-vous de son service** (pas seulement ceux qui lui sont assignés)
4. Les rendez-vous avec statut "planifié" sont affichés avec un bouton "Confirmer ce rendez-vous"
5. Le médecin voit :
   - Nom du patient
   - Date et heure du rendez-vous
   - Service
   - Médecin assigné
   - Motif (si fourni)

---

### 3. LE MÉDECIN ACCEPTE LE RENDEZ-VOUS

1. Le médecin clique sur "Confirmer ce rendez-vous"
2. Une confirmation apparaît : "Voulez-vous confirmer ce rendez-vous ? Le patient recevra une notification."
3. Le médecin confirme
4. **Le statut passe de "planifié" à "confirmé"**
5. **Une notification est automatiquement créée pour le patient**

---

### 4. LE PATIENT REÇOIT LA NOTIFICATION

1. Le patient se connecte à son compte
2. Va sur `/profil.php`
3. Dans la section "Mes Notifications", il voit :
   - Un badge avec le nombre de nouvelles notifications
   - La notification avec le titre "Rendez-vous confirmé"
   - Le message détaillé avec :
     - Date et heure du rendez-vous
     - Nom du médecin
     - Service
     - Motif (si fourni)
     - Message : "Nous vous attendons à l'heure prévue."
4. La notification est marquée comme "Nouveau" jusqu'à ce qu'il la lise
5. Le patient peut marquer la notification comme lue ou toutes les notifications comme lues

---

## Statuts des Rendez-vous

- **planifié** : Le patient a créé la demande, en attente de confirmation par le médecin
- **confirmé** : Le médecin a accepté le rendez-vous, le patient a été notifié
- **terminé** : Le rendez-vous a eu lieu
- **annulé** : Le rendez-vous a été annulé

---

## Vérifications de Sécurité

### Pour le Patient
- ✅ Un patient ne peut créer des rendez-vous que pour lui-même (sauf admin/accueil)
- ✅ Les champs nom/matricule sont pré-remplis si le patient est connecté
- ✅ Validation que le patient existe dans la base de données

### Pour le Médecin
- ✅ Un médecin ne voit que les rendez-vous de son service
- ✅ Un médecin peut confirmer n'importe quel rendez-vous de son service
- ✅ Vérification que le rendez-vous appartient bien au service du médecin

### Notifications
- ✅ Création automatique lors de la confirmation par le médecin
- ✅ Notification visible uniquement par le patient concerné
- ✅ Badge de notification non lue dans le header

---

## Points Importants

1. **Le patient n'a pas besoin de ressaisir son nom/matricule** s'il est connecté
2. **Tous les médecins d'un service voient toutes les demandes** de ce service
3. **La notification est automatique** - pas besoin d'action supplémentaire
4. **Le patient est informé en temps réel** dès que le médecin confirme

---

## Fichiers Concernés

- `/rendez-vous.php` - Page de prise de rendez-vous
- `/medecin/mes-rendez-vous.php` - Liste des rendez-vous pour le médecin
- `/medecin/approuver-rdv.php` - Script d'approbation (AJAX)
- `/profil.php` - Profil patient avec notifications
- `/config/database_functions.php` - Fonctions de création et gestion
- `/config/permissions.php` - Vérifications de permissions

---

## Messages Utilisateur

### Patient (Création)
- ✅ "Votre demande de rendez-vous a été envoyée avec succès ! Le médecin confirmera votre rendez-vous prochainement."

### Médecin (Confirmation)
- ✅ "Rendez-vous confirmé avec succès ! Le patient a été notifié."

### Patient (Notification)
- 📧 Titre : "Rendez-vous confirmé"
- 📧 Message : "Votre rendez-vous du [date] à [heure] avec le Dr. [nom] ([service]) a été confirmé. Motif : [motif]. Nous vous attendons à l'heure prévue."

---

## Résolution de Problèmes

### Le patient ne voit pas ses rendez-vous
- Vérifier que le patient est bien connecté
- Vérifier que `id_patient` est bien dans la session utilisateur

### Le médecin ne voit pas les demandes
- Vérifier que la spécialisation du médecin correspond au nom du service
- Vérifier que les rendez-vous ont bien le bon `id_service`

### La notification n'est pas créée
- Vérifier que la table `NOTIFICATIONS` existe
- Vérifier que `creerNotification()` est appelée dans `updateStatutRendezVous()`
- Vérifier les logs d'erreur PHP
