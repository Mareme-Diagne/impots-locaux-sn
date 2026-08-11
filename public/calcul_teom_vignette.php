<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calculs_foncier.php'; // obtenirBaremes()
require_once __DIR__ . '/../includes/calculs_teom_vignette.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();

$anneeExercice = (int) ($_GET['annee'] ?? $_POST['annee'] ?? date('Y'));
$baremes = obtenirBaremes($pdo, $anneeExercice);

$messageResultat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreEmises = 0;
    $nombreIgnorees = 0;

    // --- TEOM (biens cochés) ---
    foreach (($_POST['biens'] ?? []) as $bienIdBrut) {
        $bienId = (int) $bienIdBrut;

        $requeteBien = $pdo->prepare('SELECT * FROM biens_immobiliers WHERE id = :id');
        $requeteBien->execute(['id' => $bienId]);
        $bien = $requeteBien->fetch();
        if (!$bien || teomDejaEmise($pdo, $bienId, $anneeExercice)) {
            $nombreIgnorees++;
            continue;
        }

        $resultat = calculerTEOM($bien, $baremes);
        if (!$resultat['applicable']) {
            $nombreIgnorees++;
            continue;
        }

        $requeteInsertion = $pdo->prepare(
            "INSERT INTO taxations (contribuable_id, type_taxe, bien_id, annee_exercice, base_calcul,
             montant_du, detail_calcul, statut, date_emission, date_echeance)
             VALUES (:contribuable_id, 'TEOM', :bien_id, :annee, :base, :montant, :detail, 'emise', :emission, :echeance)"
        );
        $requeteInsertion->execute([
            'contribuable_id' => $bien['contribuable_id'], 'bien_id' => $bienId, 'annee' => $anneeExercice,
            'base' => $resultat['base_calcul'], 'montant' => $resultat['montant'], 'detail' => $resultat['detail'],
            'emission' => date('Y-m-d'), 'echeance' => $anneeExercice . '-12-31',
        ]);
        journaliser($_SESSION['utilisateur_id'], 'CALCUL_TAXE', "TEOM émise pour le bien #$bienId, exercice $anneeExercice, montant " . formaterMontant($resultat['montant']));
        $nombreEmises++;
    }

    // --- Vignette (véhicules cochés) ---
    foreach (($_POST['vehicules'] ?? []) as $vehiculeIdBrut) {
        $vehiculeId = (int) $vehiculeIdBrut;

        $requeteVehicule = $pdo->prepare('SELECT * FROM vehicules WHERE id = :id');
        $requeteVehicule->execute(['id' => $vehiculeId]);
        $vehicule = $requeteVehicule->fetch();
        if (!$vehicule || vignetteDejaEmise($pdo, $vehiculeId, $anneeExercice)) {
            $nombreIgnorees++;
            continue;
        }

        $resultat = calculerVignette($vehicule, $pdo, $anneeExercice);
        if (!$resultat['applicable']) {
            $nombreIgnorees++;
            continue;
        }

        $requeteInsertion = $pdo->prepare(
            "INSERT INTO taxations (contribuable_id, type_taxe, vehicule_id, annee_exercice, base_calcul,
             montant_du, detail_calcul, statut, date_emission, date_echeance)
             VALUES (:contribuable_id, 'VIGNETTE', :vehicule_id, :annee, :base, :montant, :detail, 'emise', :emission, :echeance)"
        );
        $requeteInsertion->execute([
            'contribuable_id' => $vehicule['contribuable_id'], 'vehicule_id' => $vehiculeId, 'annee' => $anneeExercice,
            'base' => $resultat['base_calcul'], 'montant' => $resultat['montant'], 'detail' => $resultat['detail'],
            'emission' => date('Y-m-d'), 'echeance' => $anneeExercice . '-12-31',
        ]);
        journaliser($_SESSION['utilisateur_id'], 'CALCUL_TAXE', "VIGNETTE émise pour le véhicule #$vehiculeId, exercice $anneeExercice, montant " . formaterMontant($resultat['montant']));
        $nombreEmises++;
    }

    $messageResultat = "$nombreEmises taxation(s) émise(s). $nombreIgnorees élément(s) ignoré(s) (déjà taxé ou non applicable).";
}

// --- Aperçus ---
$biens = $pdo->query(
    "SELECT b.*, c.nom_raison_sociale FROM biens_immobiliers b
     JOIN contribuables c ON c.id = b.contribuable_id
     WHERE b.nature = 'bati' ORDER BY b.designation"
)->fetchAll();
$apercusTeom = [];
foreach ($biens as $bien) {
    $apercusTeom[] = [
        'bien' => $bien,
        'resultat' => calculerTEOM($bien, $baremes),
        'deja_emise' => teomDejaEmise($pdo, (int) $bien['id'], $anneeExercice),
    ];
}

$vehicules = $pdo->query(
    'SELECT v.*, c.nom_raison_sociale FROM vehicules v
     JOIN contribuables c ON c.id = v.contribuable_id
     ORDER BY v.immatriculation'
)->fetchAll();
$apercusVignette = [];
foreach ($vehicules as $vehicule) {
    $apercusVignette[] = [
        'vehicule' => $vehicule,
        'resultat' => calculerVignette($vehicule, $pdo, $anneeExercice),
        'deja_emise' => vignetteDejaEmise($pdo, (int) $vehicule['id'], $anneeExercice),
    ];
}

$titrePage = 'Calcul TEOM / Vignette';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-3">Calcul TEOM & Vignette</h1>

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

    <h2 class="h5">TEOM — Biens bâtis</h2>
    <div class="table-responsive mb-4">
    <table class="table table-striped align-middle">
        <thead>
            <tr><th></th><th>Bien</th><th>Contribuable</th><th class="text-end">Valeur locative mensuelle</th><th class="text-end">Montant estimé</th><th>Statut</th></tr>
        </thead>
        <tbody>
            <?php foreach ($apercusTeom as $item): $r = $item['resultat']; ?>
            <tr class="<?= !$r['applicable'] || $item['deja_emise'] ? 'table-secondary' : '' ?>">
                <td><?php if ($r['applicable'] && !$item['deja_emise']): ?><input type="checkbox" name="biens[]" value="<?= (int) $item['bien']['id'] ?>" class="form-check-input"><?php endif; ?></td>
                <td><?= e($item['bien']['designation']) ?></td>
                <td><?= e($item['bien']['nom_raison_sociale']) ?></td>
                <td class="text-end"><?= isset($r['base_calcul']) ? e(formaterMontant($r['base_calcul'])) : '—' ?></td>
                <td class="text-end fw-bold"><?= isset($r['montant']) ? e(formaterMontant($r['montant'])) : '—' ?></td>
                <td>
                    <?php if ($item['deja_emise']): ?><span class="badge text-bg-secondary">Déjà émise</span>
                    <?php elseif (!$r['applicable']): ?><span class="badge text-bg-light text-muted" title="<?= e($r['motif']) ?>">Non applicable</span>
                    <?php else: ?><span class="badge text-bg-warning">À émettre</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($apercusTeom)): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun bien bâti enregistré.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>

    <h2 class="h5">Vignette — Véhicules</h2>
    <div class="table-responsive mb-4">
    <table class="table table-striped align-middle">
        <thead>
            <tr><th></th><th>Immatriculation</th><th>Contribuable</th><th class="text-end">Puissance fiscale</th><th class="text-end">Montant estimé</th><th>Statut</th></tr>
        </thead>
        <tbody>
            <?php foreach ($apercusVignette as $item): $r = $item['resultat']; ?>
            <tr class="<?= !$r['applicable'] || $item['deja_emise'] ? 'table-secondary' : '' ?>">
                <td><?php if ($r['applicable'] && !$item['deja_emise']): ?><input type="checkbox" name="vehicules[]" value="<?= (int) $item['vehicule']['id'] ?>" class="form-check-input"><?php endif; ?></td>
                <td><?= e($item['vehicule']['immatriculation']) ?></td>
                <td><?= e($item['vehicule']['nom_raison_sociale']) ?></td>
                <td class="text-end"><?= (int) $item['vehicule']['puissance_fiscale'] ?> CV</td>
                <td class="text-end fw-bold"><?= isset($r['montant']) ? e(formaterMontant($r['montant'])) : '—' ?></td>
                <td>
                    <?php if ($item['deja_emise']): ?><span class="badge text-bg-secondary">Déjà émise</span>
                    <?php elseif (!$r['applicable']): ?><span class="badge text-bg-light text-muted" title="<?= e($r['motif']) ?>">Non applicable</span>
                    <?php else: ?><span class="badge text-bg-warning">À émettre</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($apercusVignette)): ?><tr><td colspan="6" class="text-center text-muted py-4">Aucun véhicule enregistré.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>

    <button type="submit" class="btn btn-primary" onclick="return confirm('Émettre les taxations cochées pour l\'exercice <?= $anneeExercice ?> ?');">
        <i class="bi bi-check2-circle"></i> Émettre les taxations cochées
    </button>
</form>

<?php require __DIR__ . '/../includes/pied.php'; ?>