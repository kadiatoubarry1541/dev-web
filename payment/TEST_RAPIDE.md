# 🚀 Test Rapide - Orange Money (Mode Simulation)

Guide ultra-simple pour tester Orange Money en 5 minutes !

## ✅ Étape 1 : Préparer la Base de Données (1 minute)

Ouvrez **phpMyAdmin** et exécutez ce code SQL :

```sql
ALTER TABLE PAIEMENT 
MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces';

ALTER TABLE PAIEMENT 
ADD COLUMN IF NOT EXISTS orange_order_id VARCHAR(100) NULL AFTER id_facture;
```

✅ **C'est tout !** La base de données est prête.

## 🎯 Étape 2 : Tester un Paiement (2 minutes)

### 2.1 Aller sur la page de création

1. Connectez-vous (admin ou accueil)
2. Allez sur : `http://localhost/ProjetClinique/payment/create.php`

### 2.2 Remplir le formulaire

1. **Sélectionnez un patient** (n'importe lequel)
2. **Montant** : Entrez `50000` (ou n'importe quel montant)
3. **Méthode de paiement** : Choisissez **"Orange Money"** ⭐
4. **Un nouveau champ apparaît** : "Numéro de téléphone Orange Money"
   - Entrez : `+224 612 34 56 78` (ou n'importe quel numéro)
5. **Cliquez sur "Enregistrer le Paiement"**

### 2.3 Page de Simulation

Vous arrivez sur une page orange avec :
- ⚠️ **"Mode Simulation"** en haut
- Les détails du paiement
- **2 boutons** :
  - ✅ **"Simuler Paiement Réussi"** ← Cliquez sur celui-ci !
  - ❌ **"Simuler Annulation"**

### 2.4 Résultat

Après avoir cliqué sur "Simuler Paiement Réussi" :
- ✅ Message de succès
- ✅ Statut : "Payé"
- ✅ Numéro de facture généré
- ✅ Reçu créé automatiquement

## 📊 Étape 3 : Voir le Résultat (1 minute)

### Option 1 : Dans la liste des paiements

1. Allez sur : `http://localhost/ProjetClinique/payment/index.php`
2. Vous verrez votre paiement Orange Money
3. Cliquez sur **"Voir"** pour les détails

### Option 2 : Dans la base de données

Exécutez dans phpMyAdmin :

```sql
SELECT * FROM PAIEMENT 
WHERE Méthode_paiement = 'orange_money' 
ORDER BY Date_creation DESC 
LIMIT 1;
```

Vous verrez :
- `Méthode_paiement` = 'orange_money'
- `orange_order_id` = 'OM_...' (ID unique)
- `Statut` = 'payé'
- `id_facture` = 'FACT-...'

## 🎓 Ce que vous avez appris

✅ Comment créer un paiement Orange Money  
✅ Comment fonctionne le mode simulation  
✅ Comment le système enregistre les paiements  
✅ Comment les reçus sont générés automatiquement  

## 🔄 Testez Autrement

### Test 1 : Simuler une annulation

1. Créez un nouveau paiement Orange Money
2. Sur la page de simulation, cliquez sur **"Simuler Annulation"**
3. Observez le message d'annulation

### Test 2 : Voir plusieurs paiements

Créez 3-4 paiements Orange Money avec différents montants et observez-les dans la liste.

### Test 3 : Voir le reçu

1. Allez sur un paiement payé
2. Cliquez sur **"Voir le Reçu"**
3. Le reçu s'ouvre dans un nouvel onglet

## 💡 Astuce

**Le mode simulation est PARFAIT pour apprendre !**

- ✅ Pas besoin de credentials Orange
- ✅ Pas besoin d'argent réel
- ✅ Vous pouvez tester autant de fois que vous voulez
- ✅ Tout est enregistré dans votre base de données

## 🎉 Félicitations !

Vous avez testé votre premier paiement Orange Money ! 🚀

---

**Prochaine étape** : Lisez `GUIDE_APPRENTISSAGE.md` pour comprendre le code en détail.
