# Prendre un rendez-vous – Guide patient

Ce guide décrit comment un patient peut réserver un rendez-vous dans MediCo.

---

## Deux possibilités

### 1. Avec un compte (recommandé)

1. **Connectez-vous** avec votre compte patient sur le site.
2. Allez dans le menu **Rendez-vous** ou sur la page **Prendre rendez-vous** (depuis l’accueil ou votre espace patient).
3. Vos **nom** et **matricule** sont déjà remplis.
4. Choisissez le **service** (ex. Médecine générale, Maternité, Chirurgie, Ophtalmologie).
5. Saisissez la **date et l’heure** souhaitées (format : jj/mm/aaaa hh:mm, ex. 28/01/2026 14:30).
6. Vous pouvez indiquer un **motif** de consultation (optionnel).
7. Cliquez sur **« Réserver le rendez-vous »**.
8. Votre demande est envoyée avec le statut **« planifié »**. Un médecin du service la confirmera et vous verrez la confirmation dans vos **notifications** et dans **Mes rendez-vous**.

### 2. Sans compte

1. Allez sur la page **Rendez-vous** (lien dans le menu du site).
2. Renseignez votre **nom** et votre **matricule** dans le formulaire.  
   → Le matricule vous a été remis à l’accueil lors de votre première visite.
3. Choisissez le **service**, la **date et l’heure**, et éventuellement le **motif**.
4. Cliquez sur **« Réserver le rendez-vous »**.
5. Votre demande est enregistrée. La confirmation vous sera communiquée selon les modalités de la clinique (si vous avez un compte, elle apparaîtra dans vos notifications une fois connecté).

---

## Où aller pour prendre un rendez-vous ?

- **Menu principal** : **Rendez-vous** → ouvre la page de réservation.
- **Espace patient** : bouton **« Prendre rendez-vous »** ou **« Prendre un rendez-vous »**.
- **Page d’accueil** : liens **« Prendre rendez-vous »** / **« Réserver un rendez-vous »** selon les blocs.

---

## Après la réservation

- Votre rendez-vous est d’abord en **« planifié »** (en attente de confirmation).
- Un médecin du service choisi **confirme** le rendez-vous.
- Une fois confirmé, vous recevez une **notification** (si vous êtes connecté) et vous pouvez voir le rendez-vous dans **Mes rendez-vous** ou **Mon profil**.

---

## En cas de problème

- **Matricule non reconnu** : vérifiez le matricule fourni à l’accueil ou passez en personne pour le récupérer.
- **Pas de compte** : vous pouvez quand même réserver en utilisant votre matricule et votre nom sur la page Rendez-vous.
- **Vous ne voyez pas vos rendez-vous** : connectez-vous avec votre compte patient et consultez **Mon profil** ou **Mes rendez-vous** dans l’espace patient.

---

## D'où viennent les informations pré-remplies ?

Quand vous êtes connecté et que vous voyez « Patient connecté » avec votre nom et votre matricule :

- **Nom et matricule** viennent en priorité de votre **dossier patient** (table PATIENTS) : matricule, prénom, nom enregistrés à l’accueil ou lors de votre inscription.
- Si le dossier patient n’a pas encore ces champs, le système peut afficher le **nom** et le **matricule** enregistrés dans votre **compte** (lors de la connexion / inscription).

Pour enregistrer un rendez-vous, le système a besoin d’un **identifiant de dossier patient** présent dans la base PATIENTS. Si votre compte n’est pas encore lié à un dossier patient (même email, même matricule), ou si l’identifiant utilisé à l’envoi du formulaire n’est pas le bon, un message du type « dossier non reconnu » ou « patient n’existe pas » peut s’afficher. Dans ce cas, l’accueil peut vérifier que votre compte (email) est bien associé à votre dossier patient en clinique.

---

*Document à usage des patients – MediCo.*
