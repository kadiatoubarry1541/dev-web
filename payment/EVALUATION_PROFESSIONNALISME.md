# Évaluation du niveau professionnel du module Paiement

Ce document évalue honnêtement le module paiement : ce qui est bien, et ce qui doit être amélioré pour un usage réel en production.

---

## Ce qui est déjà bien (niveau correct)

| Point | Détail |
|-------|--------|
| **Requêtes préparées** | Les INSERT/UPDATE/SELECT utilisent `prepare()` + `execute()` → protection contre les injections SQL. |
| **Échappement HTML** | Les sorties utilisateur utilisent `htmlspecialchars()` → protection XSS sur les écrans vus. |
| **Contrôle d’accès** | `requireLogin()` et `requirePermission('manage_paiements')` limitent l’accès aux bons rôles. |
| **Logs d’erreurs** | Les erreurs sont envoyées dans `error_log()` au lieu d’être seulement affichées. |
| **Validation des entrées** | Montant > 0, patient requis, téléphone requis pour Orange Money. |
| **Documentation** | `TEST_RAPIDE.md`, `README.md`, guides Orange Money présents. |
| **Mode simulation** | Permet de tester Orange Money sans API réelle. |
| **Flux métier** | Création → Orange / simulation → retour → facture / reçu cohérent. |

Pour un projet d’étude ou une petite clinique en interne, c’est déjà suffisant pour avancer.

---

## Ce qui n’est pas encore “production-ready”

### 1. Sécurité

| Problème | Impact | Priorité |
|----------|--------|----------|
| **Pas de protection CSRF** | Un site tiers peut déclencher des actions (création paiement, simulation, etc.) si l’utilisateur est connecté. | Haute |
| **Page de simulation ouverte** | `orange_simulate.php?order_id=XXX` + POST `action=confirm_payment` peut marquer un paiement comme payé sans vraie vérification. Toute personne qui devine ou obtient un `order_id` peut “valider” en mode test. | Haute en prod |
| **Callback non authentifié** | `validateNotification()` ne vérifie pas de signature Orange. N’importe qui pouvant envoyer un POST sur `orange_callback.php` peut simuler un paiement réussi. | Haute en prod |
| **Messages d’erreur trop détaillés** | En cas d’exception BDD, le message exact est affiché à l’utilisateur (ex. `create.php`). Risque de fuite d’infos (noms de tables, structure). | Moyenne |
| **Secrets dans le code** | `merchant_id` / `merchant_key` dans `orange_config.php`. En prod, il faut des variables d’environnement (`.env`) et ne jamais committer les vrais secrets. | Haute en prod |

### 2. Architecture / maintenabilité

| Problème | Impact |
|----------|--------|
| **Détection de schéma à la volée** | `SHOW COLUMNS FROM PAIEMENT LIKE '...'` dans plusieurs scripts. Le code s’adapte au schéma au lieu de le supposer fixe. Ça alourdit le code et complique les évolutions. |
| **SQL dupliqué et branches multiples** | Dans `create.php`, beaucoup de `if (has_methode, has_id_facture, …)` qui construisent des requêtes différentes. Une base avec un schéma clair + migrations permettrait une seule requête d’insertion. |
| **Logique métier dans les vues** | Une partie du flux et des choix (statut, facture, etc.) est mélangée avec l’affichage. Une couche “service” ou “use case” rendrait le tout plus testable et plus clair. |

### 3. Comportement et robustesse

| Problème | Impact |
|----------|--------|
| **`order_id` faible** | `OM_$id_patient_time_rand(1000,9999)` est prévisible. Mieux : un identifiant unique (ex. UUID / `uniqid()` + entropie) pour éviter les collisions et la devinette. |
| **Simulation accessible en prod** | Si `simulation_mode = true` en production, la page de simulation reste utilisable. Il faudrait soit désactiver complètement l’accès à `orange_simulate.php` hors environnement de test, soit la cacher derrière un flag “env = dev/test”. |
| **Pas d’idempotence** | En cas de double clic ou de retour arrière, les données en session peuvent être rejouées ou perdues. Un token unique par intention de paiement (ex. stocké en BDD avec statut “en cours”) permettrait d’éviter les doublons et les incohérences. |
| **Pas de limite de débit** | Les endpoints de création / simulation / callback peuvent être appelés en boucle. En prod, un rate limiting (par IP et/ou par utilisateur) est recommandé. |

### 4. SSL et configuration

| Problème | Détail |
|----------|--------|
| **`CURLOPT_SSL_VERIFYPEER = false`** | Dans `orange_money_api.php`, la vérification SSL est désactivée pour les appels à Orange. Ça expose aux attaques man-in-the-middle. En production, il faut la réactiver et utiliser un environnement avec certificats corrects. |
| **URLs en dur** | `callback_url` et `return_url` en `localhost` dans la config. En prod, il faut des URLs réelles (HTTPS) et idéalement pilotées par la config / l’environnement. |

---

## Verdict

- **Contexte actuel (apprentissage, démo, interne)**  
  Le module est exploitable et plutôt propre pour un projet étudiant ou une première version en interne : SQL préparé, permissions, logs, documentation et simulation Orange Money sont un bon point de départ.

- **Contexte “vraiment professionnel” (production, argent réel, audit)**  
  Il manque encore :
  - protection CSRF sur tous les formulaires concernant les paiements ;
  - sécurisation du callback Orange (vérification de signature / secret partagé) ;
  - restriction stricte de la page de simulation (uniquement en dev/test) ;
  - secrets hors du code (variables d’environnement) ;
  - messages d’erreur génériques pour l’utilisateur et détail uniquement dans les logs ;
  - schéma de BDD fixe et maîtrisé (migrations) plutôt que détection à l’exécution.

---

## Plan d’action pour monter en niveau

1. **Court terme (sécurité minimale)**  
   - Ajouter des tokens CSRF sur les formulaires de création de paiement et sur la page de simulation.  
   - Ne plus afficher les messages d’exception bruts à l’utilisateur ; les garder seulement dans les logs.  
   - En production : désactiver ou restreindre fortement l’accès à `orange_simulate.php`.

2. **Moyen terme (préparation prod)**  
   - Utiliser un fichier `.env` pour `merchant_id`, `merchant_key`, URLs de callback/return, et ne jamais les committer.  
   - Implémenter la vérification de signature dans `validateNotification()` selon la doc Orange.  
   - Renforcer `order_id` (UUID ou équivalent).  
   - Remettre `CURLOPT_SSL_VERIFYPEER = true` et configurer correctement les certificats.

3. **Long terme (qualité “pro”)**  
   - Schéma de base fixe via migrations ; supprimer les `SHOW COLUMNS` et simplifier les requêtes.  
   - Introduire une couche service pour la logique métier des paiements.  
   - Ajouter du rate limiting sur les endpoints sensibles.  
   - Prévoir un mécanisme d’idempotence pour la création de paiements et le callback.

---

En résumé : **fonctionnel et correct pour apprendre / démo / usage interne**, mais **pas encore au niveau “production professionnelle”** tant que les points de sécurité et d’architecture ci-dessus ne sont pas traités.
