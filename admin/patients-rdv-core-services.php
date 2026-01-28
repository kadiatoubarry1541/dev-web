<?php
/**
 * Liste de tous les patients qui ont au moins un rendez-vous
 * dans l'un des 4 services principaux :
 * - Consultation générale
 * - Chirurgie
 * - Maternité
 * - Ophtalmologie
 *
 * Accessible uniquement à l'administrateur.
 */

require_once 'partials/header.php';
require_once '../config/database_functions.php';

$message       = '';
$message_type  = '';
$patients      = [];

try {
    if (function_exists('getPatientsWithRendezVousInCoreServices')) {
        $patients = getPatientsWithRendezVousInCoreServices();
    } else {
        $patients = [];
        $message = "La fonction de récupération des patients avec rendez-vous n'est pas disponible.";
        $message_type = "danger";
    }
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des patients : " . $e->getMessage();
    $message_type = "danger";
}
?>

<style>
    .page-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .page-header {
        margin-bottom: 30px;
    }

    .page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 8px;
    }

    .page-header p {
        color: #718096;
        font-size: 15px;
    }

    .badge-services {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .badge-service-item {
        padding: 4px 10px;
        border-radius: 999px;
        background: #ebf8ff;
        color: #2b6cb0;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge-service-item i {
        font-size: 12px;
    }

    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table-header {
        background: #f7fafc;
        padding: 20px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .table-header h2 {
        font-size: 20px;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }

    .table-count {
        color: #718096;
        font-size: 14px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: #f7fafc;
    }

    th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    td {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #2d3748;
        font-size: 14px;
    }

    tbody tr:hover {
        background: #f7fafc;
    }

    .no-data {
        padding: 60px 20px;
        text-align: center;
        color: #718096;
    }

    .no-data i {
        font-size: 48px;
        margin-bottom: 20px;
        color: #cbd5e0;
    }

    .btn-retour {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        margin-bottom: 20px;
    }

    .btn-retour:hover {
        background: #5a6268;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        color: white;
        text-decoration: none;
    }
</style>

<div class="page-container">
    <a href="liste-services.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i> Retour à la liste des services
    </a>

    <div class="page-header">
        <h1><i class="fas fa-users"></i> Patients avec rendez-vous dans les 4 services</h1>
        <p>
            Cette page affiche tous les patients qui ont au moins un rendez-vous
            dans l'un des services suivants :
        </p>
        <div class="badge-services">
            <span class="badge-service-item">
                <i class="fas fa-stethoscope"></i> Consultation générale
            </span>
            <span class="badge-service-item">
                <i class="fas fa-scalpel"></i> Chirurgie
            </span>
            <span class="badge-service-item">
                <i class="fas fa-baby"></i> Maternité
            </span>
            <span class="badge-service-item">
                <i class="fas fa-eye"></i> Ophtalmologie
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type == 'danger' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $message_type == 'danger' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $message_type == 'danger' ? '#f5c6cb' : '#c3e6cb'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <div class="table-header">
            <div>
                <h2>Tous les patients (4 services)</h2>
                <div class="table-count">
                    <?php echo count($patients); ?> patient(s) avec au moins un rendez-vous
                </div>
            </div>
        </div>

        <?php if (empty($patients)): ?>
            <div class="no-data">
                <i class="fas fa-user-slash"></i>
                <p>Aucun patient n'a encore de rendez-vous dans ces 4 services.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom complet</th>
                        <th>Matricule</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Date de naissance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars(($patient['Nom_patient'] ?? '') . ' ' . ($patient['Prénom_patient'] ?? '')); ?>
                                </strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($patient['Matricule_patient'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($patient['Tel_patient'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($patient['Email_patient'] ?? '-'); ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($patient['Date_naissance'])) {
                                    $date = new DateTime($patient['Date_naissance']);
                                    echo $date->format('d/m/Y');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>

