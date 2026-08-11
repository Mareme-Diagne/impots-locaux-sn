<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(); // tous les rôles connectés peuvent voir le tableau de bord

$pdo = obtenirConnexionBDD();

$nombreContribuables = (int) $pdo->query('SELECT COUNT(*) FROM contribuables')->fetchColumn();
$nombreBiens         = (int) $pdo->query('SELECT COUNT(*) FROM biens_immobiliers')->fetchColumn();
$montantTotalDu       = (float) $pdo->query('SELECT COALESCE(SUM(montant_du), 0) FROM taxations')->fetchColumn();
$montantEnRetard      = (float) $pdo->query(
    "SELECT COALESCE(SUM(montant_du), 0) FROM taxations WHERE statut = 'en_retard'"
)->fetchColumn();

$titrePage = 'Tableau de bord';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-4">Tableau de bord</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card carte-kpi text-bg-primary mb-3">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $nombreContribuables ?></div>
                <div>Contribuables enregistrés</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card carte-kpi text-bg-info mb-3">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $nombreBiens ?></div>
                <div>Biens & terrains</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card carte-kpi text-bg-success mb-3">
            <div class="card-body">
                <div class="fs-4 fw-bold"><?= e(formaterMontant($montantTotalDu)) ?></div>
                <div>Total des taxes émises</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card carte-kpi text-bg-danger mb-3">
            <div class="card-body">
                <div class="fs-4 fw-bold"><?= e(formaterMontant($montantEnRetard)) ?></div>
                <div>Montant en retard de paiement</div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border">
    Les graphiques détaillés (répartition par type de taxe, évolution mensuelle) arrivent à l'étape 9
    du projet — voir la section "Avancement" du README.
</div>

<?php require __DIR__ . '/../includes/pied.php'; ?>
