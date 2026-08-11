<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calculs_foncier.php';   // pour obtenirBaremes()
require_once __DIR__ . '/../includes/calculs_patente.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();

$anneeExercice = (int) ($_GET['annee'] ?? $_POST['annee'] ?? date('Y'));
$baremes = obtenirBaremes($pdo, $anneeExercice);

$messageResultat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activitesSelectionnees = $_POST['activites'] ?? [];
    $nombreEmises = 0;
    $nombreIgnorees = 0;

    foreach ($activitesSelectionnees as $activiteIdBrut) {
        $activiteId = (int) $activiteIdBrut;

        $requeteActivite = $pdo->prepare('SELECT * FROM activites_patentables WHERE id = :id');
        $requeteActivite->execute(['id' => $activiteId]);
        $activite = $requeteActivite->fetch();
        if (!$activite) {
            continue;
        }

        if (patenteDejaEmise($pdo, $activiteId, $anneeExercice)) {
            $nombreIgnorees++;
            continue;
        }

        $resultat = calculerPatente($activite, $baremes, $pdo, $anneeExercice);
        if (!$resultat['applicable']) {
            $nombreIgnorees++;
            continue;
        }

        $dateEmission = date('Y-m-d');
        $dateEcheance = $anneeExercice . '-12-31';

        $requeteInsertion = $pdo->prepare(
            'INSERT INTO taxations (contribuable_id, type_taxe, activite_id, annee_exercice, base_calcul,
             montant_du, detail_calcul, statut, date_emission, date_echeance)
             VALUES (:contribuable_id, \'PATENTE\', :activite_id, :annee, :base, :montant, :detail, \'emise\', :emission, :echeance)'
        );
        $requeteInsertion->execute([
            'contribuable_id' => $activite['contribuable_id'],
            'activite_id' => $activiteId,
            'annee' => $anneeExercice,
            'base' => $resultat['base_calcul'],
            'montant' => $resultat['montant'],
            'detail' => $resultat['detail'],
            'emission' => $dateEmission,
            'echeance' => $dateEcheance,
        ]);

        journaliser($_SESSION['utilisateur_id'], 'CALCUL_TAXE', "PATENTE émise pour l'activité #$activiteId, exercice $anneeExercice, montant " . formaterMontant($resultat['montant']));
        $nombreEmises++;
    }

    $messageResultat = "$nombreEmises Patente(s) émise(s). $nombreIgnorees activité(s) ignorée(s) (déjà taxée ou non applicable).";
}

$activites = $pdo->query(
    'SELECT a.*, c.nom_raison_sociale FROM activites_patentables a
     JOIN contribuables c ON c.id = a.contribuable_id
     ORDER BY a.libelle_activite'
)->fetchAll();

$apercus = [];
foreach ($activites as $activite) {
    $resultat = calculerPatente($activite, $baremes, $pdo, $anneeExercice);
    $dejaEmise = patenteDejaEmise($pdo, (int) $activite['id'], $anneeExercice);
    $apercus[] = ['activite' => $activite, 'resultat' => $resultat, 'deja_emise' => $dejaEmise];
}

$titrePage = 'Calcul Patente';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-3">Calcul de la Patente</h1>

<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
        <label class="form-label">Année d'exercice</label>
        <input type="number" name="annee" class="form-control" value="<?= $anneeExercice ?>" min="2020" max="2100">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Actualiser l'aperçu</button>
    </div>
</form>

<?php if ($messageResultat !== ''): ?>
    <div class="alert alert-success"><?= e($messageResultat) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="annee" value="<?= $anneeExercice ?>">
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th></th>
                <th>Activité</th>
                <th>Contribuable</th>
                <th class="text-end">Chiffre d'affaires</th>
                <th class="text-end">Montant estimé</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apercus as $item): $r = $item['resultat']; ?>
            <tr class="<?= !$r['applicable'] || $item['deja_emise'] ? 'table-secondary' : '' ?>">
                <td>
                    <?php if ($r['applicable'] && !$item['deja_emise']): ?>
                        <input type="checkbox" name="activites[]" value="<?= (int) $item['activite']['id'] ?>" class="form-check-input">
                    <?php endif; ?>
                </td>
                <td><?= e($item['activite']['libelle_activite']) ?></td>
                <td><?= e($item['activite']['nom_raison_sociale']) ?></td>
                <td class="text-end"><?= e(formaterMontant((float) $item['activite']['chiffre_affaires_annuel'])) ?></td>
                <td class="text-end fw-bold"><?= isset($r['montant']) ? e(formaterMontant($r['montant'])) : '—' ?></td>
                <td>
                    <?php if ($item['deja_emise']): ?>
                        <span class="badge text-bg-secondary">Déjà émise cette année</span>
                    <?php elseif (!$r['applicable']): ?>
                        <span class="badge text-bg-light text-muted" title="<?= e($r['motif']) ?>">Non applicable</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning">À émettre</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($apercus)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune activité patentable enregistrée.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <button type="submit" class="btn btn-primary" onclick="return confirm('Émettre les patentes cochées pour l\'exercice <?= $anneeExercice ?> ?');">
        <i class="bi bi-check2-circle"></i> Émettre les patentes cochées
    </button>
</form>

<?php require __DIR__ . '/../includes/pied.php'; ?>