<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiements_utils.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();

$taxationPreselectionnee = (int) ($_GET['taxation_id'] ?? 0);
$erreurs = [];
$messageSucces = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taxationId    = (int) ($_POST['taxation_id'] ?? 0);
    $montantPaye   = trim((string) ($_POST['montant_paye'] ?? ''));
    $modePaiement  = $_POST['mode_paiement'] ?? '';
    $reference     = trim((string) ($_POST['reference'] ?? '')) ?: null;
    $datePaiement  = $_POST['date_paiement'] ?? date('Y-m-d');

    $requeteTaxation = $pdo->prepare('SELECT * FROM taxations WHERE id = :id');
    $requeteTaxation->execute(['id' => $taxationId]);
    $taxation = $requeteTaxation->fetch();

    if (!$taxation) {
        $erreurs[] = 'Taxation introuvable.';
    }
    if ($montantPaye === '' || !is_numeric($montantPaye) || (float) $montantPaye <= 0) {
        $erreurs[] = 'Le montant payé doit être un nombre positif.';
    }
    if (!in_array($modePaiement, ['especes', 'virement', 'mobile_money', 'cheque'], true)) {
        $erreurs[] = 'Le mode de paiement est invalide.';
    }

    if (empty($erreurs)) {
        $requeteInsertion = $pdo->prepare(
            'INSERT INTO paiements (taxation_id, montant_paye, mode_paiement, reference, date_paiement, saisi_par)
             VALUES (:taxation_id, :montant, :mode, :reference, :date, :saisi_par)'
        );
        $requeteInsertion->execute([
            'taxation_id' => $taxationId, 'montant' => (float) $montantPaye, 'mode' => $modePaiement,
            'reference' => $reference, 'date' => $datePaiement, 'saisi_par' => $_SESSION['utilisateur_id'],
        ]);

        actualiserStatutTaxation($pdo, $taxationId);

        journaliser($_SESSION['utilisateur_id'], 'ENREGISTREMENT_PAIEMENT',
            "Paiement de " . formaterMontant((float) $montantPaye) . " sur la taxation #$taxationId");

        $messageSucces = 'Paiement enregistré avec succès.';
        $taxationPreselectionnee = 0; // on vide le formulaire après succès
    }
}

// Taxations non entièrement payées, pour le menu déroulant du formulaire
$taxationsAPayer = $pdo->query(
    "SELECT t.id, t.type_taxe, t.annee_exercice, t.montant_du, c.nom_raison_sociale,
            t.montant_du - COALESCE((SELECT SUM(p.montant_paye) FROM paiements p WHERE p.taxation_id = t.id), 0) AS reste_a_payer
     FROM taxations t
     JOIN contribuables c ON c.id = t.contribuable_id
     WHERE t.statut != 'payee'
     ORDER BY t.date_echeance ASC"
)->fetchAll();

// Historique des paiements déjà enregistrés
$parPage = 20;
$pageActuelle = max(1, (int) ($_GET['page'] ?? 1));
$decalage = ($pageActuelle - 1) * $parPage;

$totalPaiements = (int) $pdo->query('SELECT COUNT(*) FROM paiements')->fetchColumn();
$totalPages = (int) max(1, ceil($totalPaiements / $parPage));

$requetePaiements = $pdo->prepare(
    "SELECT p.*, t.type_taxe, t.annee_exercice, c.nom_raison_sociale
     FROM paiements p
     JOIN taxations t ON t.id = p.taxation_id
     JOIN contribuables c ON c.id = t.contribuable_id
     ORDER BY p.date_paiement DESC
     LIMIT :limite OFFSET :decalage"
);
$requetePaiements->bindValue('limite', $parPage, PDO::PARAM_INT);
$requetePaiements->bindValue('decalage', $decalage, PDO::PARAM_INT);
$requetePaiements->execute();
$paiements = $requetePaiements->fetchAll();

$libellesMode = ['especes' => 'Espèces', 'virement' => 'Virement', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque'];

$titrePage = 'Paiements';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-4">Paiements</h1>

<?php if ($messageSucces !== ''): ?><div class="alert alert-success"><?= e($messageSucces) ?></div><?php endif; ?>
<?php if (!empty($erreurs)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mt-0">Enregistrer un paiement</h2>
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Taxation concernée <span class="text-danger">*</span></label>
                <select name="taxation_id" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($taxationsAPayer as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $taxationPreselectionnee === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nom_raison_sociale']) ?> — <?= e($t['type_taxe']) ?> <?= (int) $t['annee_exercice'] ?>
                            (reste : <?= e(formaterMontant((float) $t['reste_a_payer'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Montant payé (FCFA) <span class="text-danger">*</span></label>
                <input type="number" step="1" min="1" name="montant_paye" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                <select name="mode_paiement" class="form-select" required>
                    <option value="especes">Espèces</option>
                    <option value="virement">Virement</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="cheque">Chèque</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Référence (optionnel)</label>
                <input type="text" name="reference" class="form-control" placeholder="N° de transaction, chèque...">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date du paiement</label>
                <input type="date" name="date_paiement" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<h2 class="h5">Historique des paiements</h2>
<div class="table-responsive mb-3">
<table class="table table-striped align-middle">
    <thead><tr><th>Date</th><th>Contribuable</th><th>Taxe</th><th class="text-end">Montant</th><th>Mode</th><th>Référence</th></tr></thead>
    <tbody>
        <?php if (empty($paiements)): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun paiement enregistré.</td></tr><?php endif; ?>
        <?php foreach ($paiements as $p): ?>
        <tr>
            <td><?= e($p['date_paiement']) ?></td>
            <td><?= e($p['nom_raison_sociale']) ?></td>
            <td><span class="badge bg-info text-dark"><?= e($p['type_taxe']) ?></span> <?= (int) $p['annee_exercice'] ?></td>
            <td class="text-end fw-bold"><?= e(formaterMontant((float) $p['montant_paye'])) ?></td>
            <td><?= e($libellesMode[$p['mode_paiement']] ?? $p['mode_paiement']) ?></td>
            <td><?= e($p['reference'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav><ul class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $pageActuelle ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>