# Flux Complet de Prise de Rendez-vous - MediCo.

## Règle métier

**La création des rendez-vous ne dépend que de l'agent d'accueil** ; l'administrateur est là en secours (ex. absence de médecin). Le médecin ne crée pas les RDV, il les valide/confirme. Le patient (ou un visiteur) dépose une **demande** sur `/rendez-vous.php` ; l'accueil la traite et crée le RDV, puis le médecin confirme.

## Processus Complet

### 1. DEMANDE DE RENDEZ-VOUS (patient ou visiteur)

#### A. Patient Connecté (Recommandé)
1. Le patient se connecte à son compte
2. Va sur la page `/rendez-vous.php`
3. **Ses informations sont automatiquement détectées** (nom, matricule)
4. Il remplit : service, date/heure, motif
5. Clique sur "Réserver le rendez-vous"
6. **Une demande est enregistrée** ; l'accueil créera le RDV, puis un médecin le confirmera.

#### B. Patient Non Connecté ou Visiteur
1. Il va sur `/rendez-vous.php`
2. Il renseigne nom, matricule (ou cherche son compte), service, date/heure, motif
3. Clique sur "Réserver le rendez-vous"
4. **Une demande est enregistrée** ; l'accueil créera le RDV et un médecin le confirmera.

---

### 2. L'ACCUEIL CRÉE LE RENDEZ-VOUS

1. L'agent d'accueil se connecte et va sur `/accueil/demandes-rdv.php`
2. Il voit les **demandes en attente** (patient, service, date/heure, motif)
3. Pour une demande liée à un patient connu : il confirme le lien patient ↔ demande, puis **crée le RDV**
4. Le RDV passe en statut "planifié" et est visible par le médecin du service. (L'admin peut aussi créer des RDV depuis `/rendez-vous.php` en secours.)

---

### 3. LE MÉDECIN VOIT LA DEMANDE

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

### 4. LE MÉDECIN ACCEPTE LE RENDEZ-VOUS

1. Le médecin clique sur "Confirmer ce rendez-vous"
2. Une confirmation apparaît : "Voulez-vous confirmer ce rendez-vous ? Le patient recevra une notification."
3. Le médecin confirme
4. **Le statut passe de "planifié" à "confirmé"**
5. **Une notification est automatiquement créée pour le patient**

---

### 5. LE PATIENT REÇOIT LA NOTIFICATION

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

### Pour le Patient / Visiteur
- ✅ Le patient ou le visiteur dépose une **demande** sur `/rendez-vous.php` ; seul l'accueil (ou l'admin) crée le RDV
- ✅ Les champs nom/matricule sont pré-remplis si le patient est connecté
- ✅ Validation que le patient existe en base pour lier la demande au dossier

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

- `/rendez-vous.php` - Demande de RDV (patient/visiteur) ou **création** de RDV (accueil/admin uniquement)
- `/accueil/demandes-rdv.php` - Traitement des demandes par l'accueil (création du RDV à partir de la demande)
- `/medecin/mes-rendez-vous.php` - Liste des rendez-vous pour le médecin (médecin redirigé depuis rendez-vous.php s'il tente de créer un RDV)
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
