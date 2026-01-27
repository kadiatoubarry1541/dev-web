# Configuration des Types de Consultations par Service

## 📍 Emplacement des Choix

Les différents types de consultations possibles pour chaque service sont définis dans le fichier :

**`config/types_consultations.php`**

Ce fichier contient un tableau associatif `$TYPES_CONSULTATIONS_PAR_SERVICE` qui liste tous les types de consultations disponibles pour chaque service médical.

## 📝 Comment Modifier les Choix

### Ajouter un Type de Consultation

Pour ajouter un nouveau type de consultation à un service existant :

1. Ouvrez le fichier `config/types_consultations.php`
2. Trouvez le service concerné dans le tableau `$TYPES_CONSULTATIONS_PAR_SERVICE`
3. Ajoutez le nouveau type dans le tableau du service :

```php
'Maternité' => [
    'Consultation prénatale',
    'Échographie obstétricale',
    'Votre nouveau type de consultation',  // ← Ajoutez ici
    // ...
],
```

### Ajouter un Nouveau Service

Pour ajouter un nouveau service avec ses types de consultations :

1. Ouvrez le fichier `config/types_consultations.php`
2. Ajoutez une nouvelle entrée dans le tableau :

```php
$TYPES_CONSULTATIONS_PAR_SERVICE = [
    // ... services existants ...
    
    'Votre Nouveau Service' => [
        'Type de consultation 1',
        'Type de consultation 2',
        'Type de consultation 3',
        // ...
    ],
];
```

### Modifier un Type de Consultation

Pour modifier un type existant, remplacez simplement le texte dans le tableau :

```php
'Maternité' => [
    'Consultation prénatale modifiée',  // ← Modifié
    // ...
],
```

### Supprimer un Type de Consultation

Pour supprimer un type, retirez simplement la ligne du tableau :

```php
'Maternité' => [
    // 'Consultation prénatale',  // ← Commenté ou supprimé
    'Échographie obstétricale',
    // ...
],
```

## 🔍 Où les Choix Apparaissent

Les types de consultations définis dans ce fichier apparaissent dans :

1. **Formulaire de création d'ordonnance** (`medecin/creer-ordonnance.php`)
   - Dans le menu déroulant "Sélectionner une Consultation"
   - Sous la section "Types de consultations disponibles pour [Service]"

## 📋 Structure du Fichier

Le fichier contient :

- **`$TYPES_CONSULTATIONS_PAR_SERVICE`** : Tableau principal avec tous les services et leurs types
- **`getTypesConsultationsParService($nom_service)`** : Fonction pour récupérer les types d'un service
- **`getAllTypesConsultations()`** : Fonction pour récupérer tous les types
- **`typeConsultationExiste($nom_service, $type_consultation)`** : Fonction pour vérifier l'existence d'un type

## ⚠️ Important

- Les noms des services doivent correspondre exactement aux noms dans la table `SERVICES` de la base de données
- Les modifications sont immédiates après sauvegarde du fichier
- Assurez-vous de respecter la syntaxe PHP (virgules, guillemets, etc.)

## 📌 Exemple Complet

```php
$TYPES_CONSULTATIONS_PAR_SERVICE = [
    'Maternité' => [
        'Consultation prénatale',
        'Échographie obstétricale',
        'Suivi de grossesse',
        'Consultation post-natale',
    ],
    
    'Médecine générale' => [
        'Consultation générale',
        'Consultation de routine',
        'Consultation d\'urgence',
    ],
];
```

## 🔄 Après Modification

Après avoir modifié le fichier :

1. Sauvegardez le fichier
2. Rechargez la page de création d'ordonnance
3. Les nouveaux types apparaîtront automatiquement dans le menu déroulant

---

**Note** : Les types de consultations servent de référence. Pour créer une ordonnance, vous devez sélectionner une consultation existante dans la base de données.
