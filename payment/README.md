# Système de Gestion des Paiements

Ce dossier contient le système de gestion des paiements pour les patients de la clinique.

## Structure des fichiers

- **index.php** : Liste tous les paiements avec filtres et statistiques
- **create.php** : Créer un nouveau paiement pour un patient
- **view.php** : Voir les détails d'un paiement spécifique
- **process.php** : Traiter les actions (génération de reçus, mise à jour de statut)

## Utilisation

### Accès
- Seuls les administrateurs et le personnel d'accueil peuvent accéder à ce système
- Les permissions sont gérées via `config/permissions.php`

### Créer un paiement
1. Accéder à `payment/create.php`
2. Sélectionner un patient
3. Optionnellement sélectionner un service (le montant sera automatiquement rempli)
4. Entrer le montant, la date, la méthode de paiement et le statut
5. Cliquer sur "Enregistrer le Paiement"

### Lister les paiements
1. Accéder à `payment/index.php`
2. Utiliser les filtres pour rechercher par statut ou patient
3. Voir les statistiques en haut de la page

### Voir les détails d'un paiement
1. Cliquer sur "Voir" dans la liste des paiements
2. Toutes les informations du paiement, du patient et du service sont affichées
3. Générer un reçu si le paiement est payé

## Tables de base de données utilisées

- **PAIEMENT** : Table principale des paiements
- **PATIENTS** : Informations des patients
- **SERVICES** : Services médicaux (optionnel)

## Fonctionnalités

- ✅ Création de paiements
- ✅ Liste avec filtres
- ✅ Détails complets
- ✅ Génération automatique de reçus
- ✅ Statistiques en temps réel
- ✅ Numéros de facture automatiques
- ✅ Gestion des statuts (payé, en attente, annulé, remboursé)

## Notes importantes

- Un paiement avec un montant de 0.00 GNF ne peut pas avoir le statut "Payé"
- Les reçus sont générés automatiquement lorsque le statut est "Payé"
- Les numéros de facture sont générés automatiquement pour les paiements payés
- Les reçus sont sauvegardés dans `uploads/reçus/`
