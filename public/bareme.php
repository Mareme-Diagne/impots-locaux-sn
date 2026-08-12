<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(['administrateur']); // configuration sensible : admin seulement

$pdo = obtenirConnexionBDD();
$messageSucces = '';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'modifier_taux') {
        $id = (int) ($_POST['id'] ?? 0);
        $nouvelleValeur = trim((string) ($_POST['valeur'] ?? ''));
        if (!is_numeric($nouvelleValeur)) {
            $erreurs[] = 'La valeur doit être un nombre.';
        } else {
            $pdo->prepare('UPDATE bareme_taux SET valeur = :valeur WHERE id = :id')
                ->execute(['valeur' => (float) $nouvelleValeur, 'id' => $id]);
            journaliser($_SESSION['utilisateur_id'], 'MODIFICATION_BAREME', "Taux #$id modifié à $nouvelleValeur");
            $messageSucces = 'Taux mis à jour.';
        }
    }

    if ($action === 'ajouter_tranche_patente') {
        $pdo->prepare(
            'INSERT INTO bareme_patente_droit_fixe (ca_min, ca_max, droit_fixe, annee_exercice) VALUES (:min, :max, :fixe, :annee)'
        )->execute([
            'min' => (float) $_POST['ca_min'], 'max' => $_POST['ca_max'] !== '' ? (float) $_POST['ca_max'] : null,
            'fixe' => (float) $_POST['droit_fixe'], 'annee' => (int) $_POST['annee_exercice'],
        ]);
        journaliser($_SESSION['utilisateur_id'], 'AJOUT_TRANCHE_PATENTE', 'Nouvelle tranche ajoutée');
        $messageSucces = 'Tranche de Patente ajoutée.';
    }

    if ($action === 'supprimer_tranche_patente') {
        $pdo->prepare('DELETE FROM bareme_patente_droit_fixe WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
        $messageSucces = 'Tranche supprimée.';
    }

    if ($action === 'ajouter_tranche_vignette') {
        $pdo->prepare(
            'INSERT INTO bareme_vignette (puissance_min, puissance_max, montant, annee_exercice) VALUES (:min, :max, :montant, :annee)'
        )->execute([
            'min' => (int) $_POST['puissance_min'], 'max' => $_POST['puissance_max'] !== '' ? (int) $_POST['puissance_max'] : null,
            'montant' => (float) $_POST['montant'], 'annee' => (int) $_POST['annee_exercice'],
        ]);
        journaliser($_SESSION['utilisateur_id'], 'AJOUT_TRANCHE_VIGNETTE', 'Nouvelle tranche ajoutée');
        $messageSucces = 'Tranche de Vignette ajoutée.';
    }

    if ($action === 'supprimer_tranche_vignette') {
        $pdo->prepare('DELETE FROM bareme_vignette WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
        $messageSucces = 'Tranche supprimée.';
    }
}

$tauxSimples   = $pdo->query('SELECT * FROM bareme_taux ORDER BY code')->fetchAll();
$tranchesPatente = $pdo->query('SELECT * FROM bareme_patente_droit_fixe ORDER BY annee_exercice DESC, ca_min')->fetchAll();
$tranchesVignette = $pdo->query('SELECT * FROM bareme_vignette ORDER BY annee_exercice DESC, puissance_min')->fetchAll();

$titrePage = 'Barème des taux';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-1">Barème des taux</h1>
<p class="text-muted mb-4">Ces valeurs pilotent tous les calculs fiscaux du site. Une modification ici s'applique immédiatement, sans toucher au code.</p>

<?php if ($messageSucces !== ''): ?><div class="alert alert-success"><?= e($messageSucces) ?></div><?php endif; ?>
<?php if (!empty($erreurs)): ?><div class="alert alert-danger"><?php foreach ($erreurs as $erreur): ?><?= e($erreur) ?><br><?php endforeach; ?></div><?php endif; ?>

<h2 class="h5">Taux et abattements (CFPB, CFPNB, TEOM, Patente)</h2>
<div class="table-responsive mb-4">
<table class="table table-striped align-middle">
    <thead><tr><th>Libellé</th><th>Code</th><th>Exercice</th><th class="text-end" style="width:220px;">Valeur</th></tr></thead>
    <tbody>
        <?php foreach ($tauxSimples as $t): ?>
        <tr>
            <td><?= e($t['libelle']) ?></td>
            <td><code><?= e($t['code']) ?></code></td>
            <td><?= (int) $t['annee_exercice'] ?></td>
            <td>
                <form method="post" class="d-flex gap-2 justify-content-end">
                    <input type="hidden" name="action" value="modifier_taux">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <input type="number" step="0.0001" name="valeur" value="<?= e((string) $t['valeur']) ?>" class="form-control form-control-sm" style="width:130px;">
                    <span class="align-self-center small text-muted"><?= $t['unite'] === 'pourcentage' ? '(0.05 = 5%)' : $t['unite'] ?></span>
                    <button type="submit" class="btn btn-sm btn-outline-primary">OK</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2 class="h5">Tranches de droit fixe — Patente</h2>
<div class="table-responsive mb-2">
<table class="table table-striped align-middle">
    <thead><tr><th class="text-end">CA min</th><th class="text-end">CA max</th><th class="text-end">Droit fixe</th><th>Exercice</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($tranchesPatente as $tr): ?>
        <tr>
            <td class="text-end"><?= e(formaterMontant((float) $tr['ca_min'])) ?></td>
            <td class="text-end"><?= $tr['ca_max'] !== null ? e(formaterMontant((float) $tr['ca_max'])) : 'Illimité' ?></td>
            <td class="text-end"><?= e(formaterMontant((float) $tr['droit_fixe'])) ?></td>
            <td><?= (int) $tr['annee_exercice'] ?></td>
            <td>
                <form method="post" onsubmit="return confirm('Supprimer cette tranche ?');">
                    <input type="hidden" name="action" value="supprimer_tranche_patente">
                    <input type="hidden" name="id" value="<?= (int) $tr['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<form method="post" class="row g-2 mb-4 align-items-end">
    <input type="hidden" name="action" value="ajouter_tranche_patente">
    <div class="col-auto"><label class="form-label small">CA min</label><input type="number" name="ca_min" class="form-control form-control-sm" required></div>
    <div class="col-auto"><label class="form-label small">CA max (vide = illimité)</label><input type="number" name="ca_max" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small">Droit fixe</label><input type="number" name="droit_fixe" class="form-control form-control-sm" required></div>
    <div class="col-auto"><label class="form-label small">Exercice</label><input type="number" name="annee_exercice" class="form-control form-control-sm" value="<?= date('Y') ?>" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Ajouter une tranche</button></div>
</form>

<h2 class="h5">Tranches de Vignette</h2>
<div class="table-responsive mb-2">
<table class="table table-striped align-middle">
    <thead><tr><th class="text-end">Puissance min</th><th class="text-end">Puissance max</th><th class="text-end">Montant</th><th>Exercice</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($tranchesVignette as $tr): ?>
        <tr>
            <td class="text-end"><?= (int) $tr['puissance_min'] ?> CV</td>
            <td class="text-end"><?= $tr['puissance_max'] !== null ? (int) $tr['puissance_max'] . ' CV' : 'Illimité' ?></td>
            <td class="text-end"><?= e(formaterMontant((float) $tr['montant'])) ?></td>
            <td><?= (int) $tr['annee_exercice'] ?></td>
            <td>
                <form method="post" onsubmit="return confirm('Supprimer cette tranche ?');">
                    <input type="hidden" name="action" value="supprimer_tranche_vignette">
                    <input type="hidden" name="id" value="<?= (int) $tr['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<form method="post" class="row g-2 align-items-end">
    <input type="hidden" name="action" value="ajouter_tranche_vignette">
    <div class="col-auto"><label class="form-label small">Puissance min (CV)</label><input type="number" name="puissance_min" class="form-control form-control-sm" required></div>
    <div class="col-auto"><label class="form-label small">Puissance max (vide = illimité)</label><input type="number" name="puissance_max" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small">Montant</label><input type="number" name="montant" class="form-control form-control-sm" required></div>
    <div class="col-auto"><label class="form-label small">Exercice</label><input type="number" name="annee_exercice" class="form-control form-control-sm" value="<?= date('Y') ?>" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Ajouter une tranche</button></div>
</form>

<?php require __DIR__ . '/../includes/pied.php'; ?>