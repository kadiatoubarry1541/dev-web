<?php
/**
 * Script de configuration automatique pour Orange Money
 * Vérifie et met à jour la base de données pour supporter Orange Money
 * 
 * Accès : Admin uniquement
 */
require_once '../config/session.php';
require_once '../config/permissions.php';
require_once '../config/database_functions.php';

requireLogin('../login.php');
requirePermission('manage_paiements', '../index.php');

$user_info = getUserInfo();
$messages = [];
$errors = [];
$success = false;

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_orange_money'])) {
    try {
        $pdo = bdd();
        
        // 1. Vérifier et mettre à jour la colonne Méthode_paiement
        $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
        $has_methode = $check_methode->rowCount() > 0;
        
        if (!$has_methode) {
            $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
            $has_methode_alt = $check_methode_alt->rowCount() > 0;
        } else {
            $has_methode_alt = false;
        }
        
        if ($has_methode) {
            // Vérifier si orange_money est déjà dans l'ENUM
            $column_info = $check_methode->fetch();
            $enum_values = $column_info['Type'];
            
            if (strpos($enum_values, 'orange_money') === false) {
                // Ajouter orange_money à l'ENUM
                try {
                    $pdo->exec("ALTER TABLE PAIEMENT MODIFY COLUMN Méthode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces'");
                    $messages[] = "✓ Colonne Méthode_paiement mise à jour avec succès (orange_money ajouté)";
                } catch (Exception $e) {
                    $errors[] = "✗ Erreur lors de la mise à jour de Méthode_paiement : " . $e->getMessage();
                }
            } else {
                $messages[] = "✓ Colonne Méthode_paiement supporte déjà orange_money";
            }
        } elseif ($has_methode_alt) {
            // Vérifier si orange_money est déjà dans l'ENUM
            $column_info = $check_methode_alt->fetch();
            $enum_values = $column_info['Type'];
            
            if (strpos($enum_values, 'orange_money') === false) {
                // Ajouter orange_money à l'ENUM
                try {
                    $pdo->exec("ALTER TABLE PAIEMENT MODIFY COLUMN methode_paiement ENUM('espèces', 'carte', 'chèque', 'virement', 'orange_money') DEFAULT 'espèces'");
                    $messages[] = "✓ Colonne methode_paiement mise à jour avec succès (orange_money ajouté)";
                } catch (Exception $e) {
                    $errors[] = "✗ Erreur lors de la mise à jour de methode_paiement : " . $e->getMessage();
                }
            } else {
                $messages[] = "✓ Colonne methode_paiement supporte déjà orange_money";
            }
        } else {
            $errors[] = "✗ Aucune colonne de méthode de paiement trouvée dans la table PAIEMENT";
        }
        
        // 2. Vérifier et ajouter la colonne orange_order_id
        $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
        $has_orange_id = $check_orange_id->rowCount() > 0;
        
        if (!$has_orange_id) {
            try {
                // Essayer d'ajouter après id_facture
                $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN orange_order_id VARCHAR(100) NULL AFTER id_facture");
                $messages[] = "✓ Colonne orange_order_id ajoutée avec succès";
            } catch (Exception $e) {
                // Si id_facture n'existe pas, ajouter à la fin
                try {
                    $pdo->exec("ALTER TABLE PAIEMENT ADD COLUMN orange_order_id VARCHAR(100) NULL");
                    $messages[] = "✓ Colonne orange_order_id ajoutée avec succès";
                } catch (Exception $e2) {
                    $errors[] = "✗ Erreur lors de l'ajout de orange_order_id : " . $e2->getMessage();
                }
            }
        } else {
            $messages[] = "✓ Colonne orange_order_id existe déjà";
        }
        
        // 3. Vérifier et ajouter l'index
        $check_index = $pdo->query("SHOW INDEX FROM PAIEMENT WHERE Key_name = 'idx_orange_order_id'");
        $has_index = $check_index->rowCount() > 0;
        
        if (!$has_index) {
            try {
                $pdo->exec("CREATE INDEX idx_orange_order_id ON PAIEMENT(orange_order_id)");
                $messages[] = "✓ Index idx_orange_order_id créé avec succès";
            } catch (Exception $e) {
                $errors[] = "✗ Erreur lors de la création de l'index : " . $e->getMessage();
            }
        } else {
            $messages[] = "✓ Index idx_orange_order_id existe déjà";
        }
        
        if (empty($errors)) {
            $success = true;
            $messages[] = "✓ Configuration Orange Money terminée avec succès !";
        }
        
    } catch (Exception $e) {
        $errors[] = "✗ Erreur générale : " . $e->getMessage();
    }
}

// Vérification de l'état actuel
$current_status = [];
try {
    $pdo = bdd();
    
    // Vérifier Méthode_paiement
    $check_methode = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'Méthode_paiement'");
    $has_methode = $check_methode->rowCount() > 0;
    
    if ($has_methode) {
        $column_info = $check_methode->fetch();
        $enum_values = $column_info['Type'];
        $current_status['methode_paiement'] = strpos($enum_values, 'orange_money') !== false ? 'ok' : 'missing';
    } else {
        $check_methode_alt = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'methode_paiement'");
        $has_methode_alt = $check_methode_alt->rowCount() > 0;
        if ($has_methode_alt) {
            $column_info = $check_methode_alt->fetch();
            $enum_values = $column_info['Type'];
            $current_status['methode_paiement'] = strpos($enum_values, 'orange_money') !== false ? 'ok' : 'missing';
        } else {
            $current_status['methode_paiement'] = 'not_found';
        }
    }
    
    // Vérifier orange_order_id
    $check_orange_id = $pdo->query("SHOW COLUMNS FROM PAIEMENT LIKE 'orange_order_id'");
    $current_status['orange_order_id'] = $check_orange_id->rowCount() > 0 ? 'ok' : 'missing';
    
    // Vérifier l'index
    $check_index = $pdo->query("SHOW INDEX FROM PAIEMENT WHERE Key_name = 'idx_orange_order_id'");
    $current_status['index'] = $check_index->rowCount() > 0 ? 'ok' : 'missing';
    
} catch (Exception $e) {
    $errors[] = "Erreur lors de la vérification : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Configuration Orange Money - MediCo.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../assets/css/plugins.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/style.min.css">
    <link class="skin" rel="stylesheet" type="text/css" href="../assets/css/skin/skin-1.css">
    <link rel="stylesheet" type="text/css" href="../assets/css/templete.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .setup-container {
            padding: 40px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .setup-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 800px;
        }
        .status-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-ok {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .status-missing {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        .status-not-found {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .btn-setup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
    </style>
</head>
<body>
<div class="page-wraper">
    <?php require_once '../partials/entete.php'; ?>
    
    <div class="setup-container">
        <div class="container">
            <div class="setup-card">
                <h1 style="color: #002939; margin-bottom: 30px;">
                    <i class="fa fa-mobile"></i> Configuration Orange Money
                </h1>
                
                <?php if (!empty($messages)): ?>
                    <div class="alert alert-success">
                        <strong>Résultats :</strong><br>
                        <?php foreach ($messages as $msg): ?>
                            <?php echo htmlspecialchars($msg); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Erreurs :</strong><br>
                        <?php foreach ($errors as $error): ?>
                            <?php echo htmlspecialchars($error); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <h2 style="color: #002939; margin-top: 30px; margin-bottom: 20px;">État Actuel</h2>
                
                <div class="status-item <?php echo $current_status['methode_paiement'] === 'ok' ? 'status-ok' : ($current_status['methode_paiement'] === 'not_found' ? 'status-not-found' : 'status-missing'); ?>">
                    <i class="fa fa-<?php echo $current_status['methode_paiement'] === 'ok' ? 'check-circle' : ($current_status['methode_paiement'] === 'not_found' ? 'times-circle' : 'exclamation-triangle'); ?>"></i>
                    <div>
                        <strong>Colonne Méthode_paiement</strong><br>
                        <?php 
                        if ($current_status['methode_paiement'] === 'ok') {
                            echo "✓ Supporte Orange Money";
                        } elseif ($current_status['methode_paiement'] === 'not_found') {
                            echo "✗ Colonne non trouvée";
                        } else {
                            echo "⚠ Orange Money non supporté";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="status-item <?php echo $current_status['orange_order_id'] === 'ok' ? 'status-ok' : 'status-missing'; ?>">
                    <i class="fa fa-<?php echo $current_status['orange_order_id'] === 'ok' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <div>
                        <strong>Colonne orange_order_id</strong><br>
                        <?php echo $current_status['orange_order_id'] === 'ok' ? '✓ Existe' : '⚠ Manquante'; ?>
                    </div>
                </div>
                
                <div class="status-item <?php echo $current_status['index'] === 'ok' ? 'status-ok' : 'status-missing'; ?>">
                    <i class="fa fa-<?php echo $current_status['index'] === 'ok' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <div>
                        <strong>Index idx_orange_order_id</strong><br>
                        <?php echo $current_status['index'] === 'ok' ? '✓ Existe' : '⚠ Manquant'; ?>
                    </div>
                </div>
                
                <?php if ($current_status['methode_paiement'] !== 'ok' || $current_status['orange_order_id'] !== 'ok' || $current_status['index'] !== 'ok'): ?>
                    <form method="post" action="" style="margin-top: 30px;">
                        <button type="submit" name="setup_orange_money" class="btn-setup">
                            <i class="fa fa-cog"></i> Configurer Orange Money
                        </button>
                    </form>
                <?php else: ?>
                    <div style="margin-top: 30px; padding: 20px; background: #d4edda; border-radius: 8px; color: #155724;">
                        <i class="fa fa-check-circle"></i> <strong>Configuration complète !</strong> Orange Money est prêt à être utilisé.
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 30px;">
                    <a href="../paiements/creer-paiement.php" class="btn-setup" style="text-decoration: none; display: inline-block;">
                        <i class="fa fa-arrow-left"></i> Retour aux Paiements
                    </a>
                    <a href="README_ORANGE_MONEY.md" target="_blank" class="btn-setup" style="text-decoration: none; display: inline-block; background: #6c757d; margin-left: 10px;">
                        <i class="fa fa-book"></i> Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once '../partials/footer.php'; ?>
</div>

<script src="../assets/js/jquery.min.js"></script>
</body>
</html>
