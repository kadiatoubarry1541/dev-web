# 🎓 Guide d'Apprentissage - Orange Money

Ce guide vous aidera à apprendre et tester l'intégration Orange Money étape par étape.

## 📚 Objectif

Apprendre comment fonctionne un système de paiement Orange Money en testant sans avoir besoin de credentials réels.

## ✅ Étape 1 : Préparer la Base de Données

### 1.1 Exécuter le Script SQL

Ouvrez phpMyAdmin ou votre client MySQL et exécutez ce script :

```sql
-- Ajouter "orange_money" à la méthode de paiement
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

-- Ajouter la colonne pour stocker l'ID de commande Orange
ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;
```

**OU** utilisez le fichier : `payment/add_orange_money_method.sql`

### 1.2 Vérifier que ça fonctionne

Exécutez cette requête pour vérifier :

```sql
SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement';
```

Vous devriez voir "orange_money" dans la liste des valeurs possibles.

## 🧪 Étape 2 : Tester avec le Mode Simulation

### 2.1 Vérifier la Configuration

Le fichier `payment/orange_config.php` doit avoir :

```php
'simulation_mode' => true,  // ← Mode simulation activé
```

C'est déjà configuré par défaut ! ✅

### 2.2 Créer un Paiement Test

1. **Connectez-vous** en tant qu'administrateur ou accueil
2. **Allez sur** : `http://localhost/ProjetClinique/payment/create.php`
3. **Remplissez le formulaire** :
   - Sélectionnez un patient
   - Entrez un montant (ex: 50000 GNF)
   - **Choisissez "Orange Money"** dans la méthode de paiement
   - Un champ apparaît : **"Numéro de téléphone Orange Money"**
   - Entrez un numéro (ex: +224 612 34 56 78)
4. **Cliquez sur "Enregistrer le Paiement"**

### 2.3 Observer le Flux

Après avoir cliqué, vous serez redirigé vers la **page de simulation** (`orange_simulate.php`).

**Sur cette page, vous verrez :**
- ⚠️ Un bandeau "Mode Simulation"
- Les détails du paiement (patient, montant, order ID)
- Deux boutons :
  - ✅ **"Simuler Paiement Réussi"** → Simule un paiement qui fonctionne
  - ❌ **"Simuler Annulation"** → Simule un paiement annulé

### 2.4 Tester le Succès

1. Cliquez sur **"Simuler Paiement Réussi"**
2. Vous serez redirigé vers `orange_return.php`
3. **Observez** :
   - ✅ Message de succès
   - Détails du paiement
   - Statut : "Payé"
   - Numéro de facture généré automatiquement

### 2.5 Vérifier dans la Base de Données

Exécutez cette requête pour voir le paiement créé :

```sql
SELECT * FROM PAIEMENT 
WHERE Méthode_paiement = 'orange_money' 
ORDER BY Date_creation DESC 
LIMIT 1;
```

**Vous devriez voir :**
- `Méthode_paiement` = 'orange_money'
- `orange_order_id` = 'OM_...' (ID unique)
- `Statut` = 'payé' (si vous avez cliqué sur succès)
- `id_facture` = 'FACT-...' (généré automatiquement)

### 2.6 Tester l'Annulation

1. Créez un nouveau paiement Orange Money
2. Sur la page de simulation, cliquez sur **"Simuler Annulation"**
3. Observez le message d'annulation

## 🔍 Étape 3 : Comprendre le Code

### 3.1 Le Flux Complet

```
1. create.php
   ↓ (utilisateur sélectionne Orange Money)
2. orange_process.php
   ↓ (crée le paiement en base avec statut "en_attente")
3. orange_simulate.php (mode simulation)
   OU
   orange_money_api.php → API Orange réelle (mode production)
   ↓ (utilisateur effectue le paiement)
4. orange_callback.php (webhook - notification Orange)
   ↓ (met à jour le statut à "payé")
5. orange_return.php (retour utilisateur)
   ↓ (affiche le résultat)
6. view.php (voir les détails)
```

### 3.2 Fichiers à Explorer

**Pour comprendre le fonctionnement :**

1. **`orange_money_api.php`** (lignes 1-50)
   - Classe `OrangeMoneyAPI`
   - Méthode `simulatePayment()` → Mode simulation
   - Méthode `initiatePayment()` → API réelle

2. **`orange_process.php`** (lignes 20-80)
   - Récupère les données du formulaire
   - Crée le paiement en base
   - Appelle l'API (simulation ou réelle)
   - Redirige vers la page de paiement

3. **`orange_simulate.php`**
   - Page de test simple
   - Permet de simuler succès/échec
   - Met à jour le statut en base

4. **`orange_callback.php`**
   - Reçoit les notifications d'Orange (webhook)
   - Met à jour le statut automatiquement
   - Génère le reçu si payé

## 🎯 Étape 4 : Exercices Pratiques

### Exercice 1 : Créer 3 Paiements Test

Créez 3 paiements Orange Money avec :
- Paiement 1 : 10000 GNF → Simuler succès
- Paiement 2 : 25000 GNF → Simuler annulation
- Paiement 3 : 50000 GNF → Simuler succès

**Vérifiez** dans la liste des paiements (`payment/index.php`) que vous voyez bien les 3 paiements avec leurs statuts.

### Exercice 2 : Observer les Reçus

Pour les paiements avec statut "payé", vérifiez :
1. Allez sur `payment/view.php?id=X` (remplacez X par l'ID du paiement)
2. Cliquez sur "Générer le Reçu" ou "Voir le Reçu"
3. Le reçu est généré automatiquement dans `uploads/reçus/`

### Exercice 3 : Modifier le Code

**Test 1** : Modifier le message de succès
- Ouvrez `orange_return.php`
- Trouvez le message "Paiement effectué avec succès !"
- Modifiez-le pour voir le changement

**Test 2** : Ajouter un log
- Ouvrez `orange_process.php`
- Ajoutez après la ligne 47 :
  ```php
  error_log("Order ID généré : " . $order_id);
  ```
- Créez un paiement et vérifiez les logs PHP

## 📊 Étape 5 : Analyser les Données

### Requêtes SQL Utiles

**Voir tous les paiements Orange Money :**
```sql
SELECT 
    p.id_paiement,
    p.Montant,
    p.Statut,
    p.orange_order_id,
    p.id_facture,
    p.Date_paiement,
    pat.Nom_patient,
    pat.Prénom_patient
FROM PAIEMENT p
LEFT JOIN PATIENTS pat ON p.id_patient = pat.id_patient
WHERE p.Méthode_paiement = 'orange_money'
ORDER BY p.Date_paiement DESC;
```

**Statistiques Orange Money :**
```sql
SELECT 
    Statut,
    COUNT(*) as nombre,
    SUM(Montant) as total_montant
FROM PAIEMENT
WHERE Méthode_paiement = 'orange_money'
GROUP BY Statut;
```

## 🚀 Étape 6 : Passer à l'API Réelle (Optionnel)

Quand vous serez prêt à tester avec l'API réelle :

1. **Obtenez vos credentials** auprès d'Orange
2. **Modifiez** `orange_config.php` :
   ```php
   'simulation_mode' => false,
   'merchant_id' => 'VOTRE_ID',
   'merchant_key' => 'VOTRE_KEY',
   ```
3. **Testez** avec l'environnement sandbox d'Orange
4. **Observez** les différences avec la simulation

## 💡 Conseils d'Apprentissage

1. **Testez chaque étape** une par une
2. **Lisez les commentaires** dans le code
3. **Modifiez le code** pour voir ce qui change
4. **Utilisez les logs** pour comprendre le flux
5. **Posez des questions** sur ce que vous ne comprenez pas

## 🐛 Dépannage

### Le paiement ne s'affiche pas dans la liste

- Vérifiez que le script SQL a été exécuté
- Vérifiez que `orange_order_id` existe dans la table

### La page de simulation ne s'affiche pas

- Vérifiez que `simulation_mode => true` dans `orange_config.php`
- Vérifiez les logs PHP pour les erreurs

### Le reçu n'est pas généré

- Vérifiez que le dossier `uploads/reçus/` existe et est accessible en écriture
- Vérifiez les permissions du dossier

## 📝 Notes Importantes

- ✅ Le mode simulation est **parfait pour apprendre**
- ✅ Vous n'avez **pas besoin** de credentials Orange pour tester
- ✅ Tous les paiements sont **enregistrés** dans la base de données
- ✅ Les reçus sont **générés automatiquement** quand le paiement est payé
- ✅ Vous pouvez **tester autant de fois** que vous voulez

## 🎉 Félicitations !

Vous avez maintenant un système complet de paiement Orange Money que vous pouvez tester et modifier pour apprendre !

Bon apprentissage ! 🚀
