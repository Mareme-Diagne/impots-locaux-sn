<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calculs_foncier.php';
exigerConnexion(['administrateur', 'agent']); // le consultant ne déclenche pas de calculs

$pdo = obtenirConnexionBDD();

$anneeExercice = (int) ($_GET['annee'] ?? $_POST['annee'] ?? date('Y'));
$baremes = obtenirBaremes($pdo, $anneeExercice);

$messageResultat = '';

// --- Émission effective des taxations sélectionnées (uniquement en POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $biensSelectionnes = $_POST['biens'] ?? []; // tableau d'IDs de biens cochés
    $nombreEmises = 0;
    $nombreIgnorees = 0;

    foreach ($biensSelectionnes as $bienIdBrut) {
        $bienId = (int) $bienIdBrut;

        $requeteBien = $pdo->prepare('SELECT * FROM biens_immobiliers WHERE id = :id');
        $requeteBien->execute(['id' => $bienId]);
        $bien = $requeteBien->fetch();
        if (!$bien) {
            continue;
        }

        $typeTaxe = $bien['nature'] === 'bati' ? 'CFPB' : 'CFPNB';

        if (taxationDejaEmise($pdo, $bienId, $typeTaxe, $anneeExercice)) {
            $nombreIgnorees++;
            continue; // on ne double-jamais une taxation déjà émise pour ce bien/année
        }

        $resultat = $typeTaxe === 'CFPB'
            ? calculerCFPB($bien, $baremes, $anneeExercice)
            : calculerCFPNB($bien, $baremes);

        if (!$resultat['applicable']) {
            $nombreIgnorees++;
            continue;
        }

        $dateEmission = date('Y-m-d');
        $dateEcheance = $anneeExercice . '-12-31'; // échéance simplifiée : fin de l'année d'exercice

        $requeteInsertion = $pdo->prepare(
            'INSERT INTO taxations (contribuable_id, type_taxe, bien_id, annee_exercice, base_calcul,
             montant_du, detail_calcul, statut, date_emission, date_echeance)
             VALUES (:contribuable_id, :type, :bien_id, :annee, :base, :montant, :detail, \'emise\', :emission, :echeance)'
        );
        $requeteInsertion->execute([
            'contribuable_id' => $bien['contribuable_id'],
            'type'    => $typeTaxe,
            'bien_id' => $bienId,
            'annee'   => $anneeExercice,
            'base'    => $resultat['base_calcul'],
            'montant' => $resultat['montant'],
            'detail'  => $resultat['detail'],
            'emission' => $dateEmission,
            'echeance' => $dateEcheance,
        ]);

        journaliser($_SESSION['utilisateur_id'], 'CALCUL_TAXE', "$typeTaxe émise pour le bien #$bienId, exercice $anneeExercice, montant " . formaterMontant($resultat['montant']));
        $nombreEmises++;
    }

    $messageResultat = "$nombreEmises taxation(s) émise(s). $nombreIgnorees bien(s) ignoré(s) (déjà taxé, exonéré ou non applicable).";
}

// --- Aperçu : pour chaque bien, on calcule "à blanc" (sans rien enregistrer) pour affichage ---
$biens = $pdo->query(
    'SELECT b.*, c.nom_raison_sociale FROM biens_immobiliers b
     JOIN contribuables c ON c.id = b.contribuable_id
     ORDER BY b.nature, b.designation'
)->fetchAll();

$apercus = [];
foreach ($biens as $bien) {
    $typeTaxe = $bien['nature'] === 'bati' ? 'CFPB' : 'CFPNB';
    $resultat = $typeTaxe === 'CFPB' ? calculerCFPB($bien, $baremes, $anneeExercice) : calculerCFPNB($bien, $baremes);
    $dejaEmise = taxationDejaEmise($pdo, (int) $bien['id'], $typeTaxe, $anneeExercice);

    $apercus[] = [
        'bien' => $bien,
        'type_taxe' => $typeTaxe,
        'resultat' => $resultat,
        'deja_emise' => $dejaEmise,
    ];
}

$titrePage = 'Calcul CFPB / CFPNB';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-3">Calcul CFPB / CFPNB</h1>

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

<?php if (empty($baremes)): ?>
    <div class="alert alert-warning">
        Aucun barème de taux n'est enregistré pour l'exercice <?= $anneeExercice ?> dans la table
        <code>bareme_taux</code>. Les calculs ci-dessous utilisent des valeurs par défaut de secours.
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="annee" value="<?= $anneeExercice ?>">
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th></th>
                <th>Bien</th>
                <th>Contribuable</th>
                <th>Taxe</th>
                <th class="text-end">Base de calcul</th>
                <th class="text-end">Montant estimé</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apercus as $item): $r = $item['resultat']; ?>
            <tr class="<?= !$r['applicable'] || $item['deja_emise'] ? 'table-secondary' : '' ?>">
                <td>
                    <?php if ($r['applicable'] && !$item['deja_emise'] && ($r['montant'] ?? 0) >= 0): ?>
                        <input type="checkbox" name="biens[]" value="<?= (int) $item['bien']['id'] ?>" class="form-check-input">
                    <?php endif; ?>
                </td>
                <td><?= e($item['bien']['designation']) ?></td>
                <td><?= e($item['bien']['nom_raison_sociale']) ?></td>
                <td><span class="badge bg-info text-dark"><?= $item['type_taxe'] ?></span></td>
                <td class="text-end"><?= isset($r['base_calcul']) ? e(formaterMontant($r['base_calcul'])) : '—' ?></td>
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
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun bien enregistré. Ajoutez d'abord des biens dans le module "Biens & terrains".</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <button type="submit" class="btn btn-primary" onclick="return confirm('Émettre les taxations cochées pour l\'exercice <?= $anneeExercice ?> ?');">
        <i class="bi bi-check2-circle"></i> Émettre les taxations cochées
    </button>
</form>

<?php require __DIR__ . '/../includes/pied.php'; ?>