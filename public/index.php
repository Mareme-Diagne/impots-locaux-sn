<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pdo = obtenirConnexionBDD();

// --- KPI généraux (déjà présents depuis l'étape 4) ---
$nombreContribuables = (int) $pdo->query('SELECT COUNT(*) FROM contribuables')->fetchColumn();
$nombreBiens         = (int) $pdo->query('SELECT COUNT(*) FROM biens_immobiliers')->fetchColumn();
$montantTotalDu       = (float) $pdo->query('SELECT COALESCE(SUM(montant_du), 0) FROM taxations')->fetchColumn();
$montantEnRetard      = (float) $pdo->query(
    "SELECT COALESCE(SUM(montant_du), 0) FROM taxations WHERE statut = 'en_retard'"
)->fetchColumn();

// --- Graphique 1 : répartition du montant total par type de taxe ---
$requeteParType = $pdo->query(
    'SELECT type_taxe, COALESCE(SUM(montant_du), 0) AS total
     FROM taxations GROUP BY type_taxe ORDER BY type_taxe'
);
$labelsParType = [];
$valeursParType = [];
foreach ($requeteParType->fetchAll() as $ligne) {
    $labelsParType[]  = $ligne['type_taxe'];
    $valeursParType[] = (float) $ligne['total'];
}

// --- Graphique 2 : évolution mensuelle des montants émis (12 derniers mois) ---
$requeteMensuelle = $pdo->query(
    "SELECT DATE_FORMAT(date_emission, '%Y-%m') AS mois, COALESCE(SUM(montant_du), 0) AS total
     FROM taxations
     WHERE date_emission >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY mois ORDER BY mois"
);
$labelsMensuel = [];
$valeursMensuel = [];
foreach ($requeteMensuelle->fetchAll() as $ligne) {
    $labelsMensuel[]  = $ligne['mois'];
    $valeursMensuel[] = (float) $ligne['total'];
}

// --- Graphique 3 : répartition par statut de paiement ---
$requeteStatut = $pdo->query(
    "SELECT statut, COUNT(*) AS nombre FROM taxations GROUP BY statut"
);
$libellesStatut = ['emise' => 'Émise', 'payee' => 'Payée', 'partiellement_payee' => 'Partiellement payée', 'en_retard' => 'En retard'];
$labelsStatut = [];
$valeursStatut = [];
foreach ($requeteStatut->fetchAll() as $ligne) {
    $labelsStatut[]  = $libellesStatut[$ligne['statut']] ?? $ligne['statut'];
    $valeursStatut[] = (int) $ligne['nombre'];
}

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

<?php if (empty($labelsParType)): ?>
    <div class="alert alert-light border">
        Aucune taxation émise pour l'instant — les graphiques apparaîtront dès que tu auras émis
        des taxations depuis les pages de calcul (CFPB/CFPNB, Patente, TEOM/Vignette).
    </div>
<?php else: ?>
<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mt-0">Répartition par type de taxe</h2>
                <canvas id="graphiqueParType" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mt-0">Répartition par statut</h2>
                <canvas id="graphiqueStatut" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mt-0">Évolution mensuelle des montants émis</h2>
                <canvas id="graphiqueMensuel" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>

<?php if (!empty($labelsParType)): ?>
<script src="../assets/js/chart.umd.min.js"></script>
<script>
// On passe les données calculées en PHP au JavaScript via json_encode — pas de requête
// supplémentaire côté client, tout est déjà prêt au chargement de la page.
const donneesParType = {
    labels: <?= json_encode($labelsParType) ?>,
    valeurs: <?= json_encode($valeursParType) ?>,
};
const donneesMensuelles = {
    labels: <?= json_encode($labelsMensuel) ?>,
    valeurs: <?= json_encode($valeursMensuel) ?>,
};
const donneesStatut = {
    labels: <?= json_encode($labelsStatut) ?>,
    valeurs: <?= json_encode($valeursStatut) ?>,
};

// Palette cohérente avec l'identité visuelle du site (marine + doré + tons doux)
const palette = ['#131c33', '#b8862e', '#2a8a9e', '#2f8f5b', '#a5322f', '#6b5ca5'];

new Chart(document.getElementById('graphiqueParType'), {
    type: 'doughnut',
    data: {
        labels: donneesParType.labels,
        datasets: [{ data: donneesParType.valeurs, backgroundColor: palette }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('graphiqueStatut'), {
    type: 'pie',
    data: {
        labels: donneesStatut.labels,
        datasets: [{ data: donneesStatut.valeurs, backgroundColor: palette }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('graphiqueMensuel'), {
    type: 'bar',
    data: {
        labels: donneesMensuelles.labels,
        datasets: [{ label: 'Montant émis (FCFA)', data: donneesMensuelles.valeurs, backgroundColor: '#131c33' }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('fr-FR') + ' F' } } }
    }
});
</script>
<?php endif; ?>