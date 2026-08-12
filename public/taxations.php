<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paiements_utils.php';
exigerConnexion();

$pdo = obtenirConnexionBDD();

$typeFiltre    = $_GET['type'] ?? '';
$statutFiltre  = $_GET['statut'] ?? '';
$anneeFiltre   = (int) ($_GET['annee'] ?? 0);
$recherche     = trim((string) ($_GET['q'] ?? ''));

$conditions = [];
$parametres = [];

if (in_array($typeFiltre, ['CFPB', 'CFPNB', 'PATENTE', 'TEOM', 'VIGNETTE'], true)) {
    $conditions[] = 't.type_taxe = :type';
    $parametres['type'] = $typeFiltre;
}
if (in_array($statutFiltre, ['emise', 'payee', 'partiellement_payee', 'en_retard'], true)) {
    $conditions[] = 't.statut = :statut';
    $parametres['statut'] = $statutFiltre;
}
if ($anneeFiltre > 0) {
    $conditions[] = 't.annee_exercice = :annee';
    $parametres['annee'] = $anneeFiltre;
}
if ($recherche !== '') {
    $conditions[] = 'c.nom_raison_sociale LIKE :recherche';
    $parametres['recherche'] = '%' . $recherche . '%';
}

$clauseOu = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$parPage = 20;
$pageActuelle = max(1, (int) ($_GET['page'] ?? 1));
$decalage = ($pageActuelle - 1) * $parPage;

$requeteTotal = $pdo->prepare("SELECT COUNT(*) FROM taxations t JOIN contribuables c ON c.id = t.contribuable_id $clauseOu");
$requeteTotal->execute($parametres);
$totalResultats = (int) $requeteTotal->fetchColumn();
$totalPages = (int) max(1, ceil($totalResultats / $parPage));

$sql = "SELECT t.*, c.nom_raison_sociale FROM taxations t
        JOIN contribuables c ON c.id = t.contribuable_id
        $clauseOu
        ORDER BY t.date_emission DESC
        LIMIT :limite OFFSET :decalage";
$requete = $pdo->prepare($sql);
foreach ($parametres as $cle => $valeur) {
    $requete->bindValue($cle, $valeur);
}
$requete->bindValue('limite', $parPage, PDO::PARAM_INT);
$requete->bindValue('decalage', $decalage, PDO::PARAM_INT);
$requete->execute();
$taxations = $requete->fetchAll();

// On rafraîchit le statut des lignes affichées (ex: passage automatique à "en retard"
// si la date d'échéance est dépassée sans paiement complet).
foreach ($taxations as &$t) {
    actualiserStatutTaxation($pdo, (int) $t['id']);
}
unset($t);
// On relit après recalcul pour que l'affichage soit à jour.
$requete->execute();
$taxations = $requete->fetchAll();

$libellesStatut = [
    'emise' => ['Émise', 'secondary'], 'payee' => ['Payée', 'success'],
    'partiellement_payee' => ['Partiellement payée', 'warning'], 'en_retard' => ['En retard', 'danger'],
];

$titrePage = 'Taxations';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-3">Taxations</h1>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" class="form-control" placeholder="Nom du contribuable..." value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-2">
        <select name="type" class="form-select">
            <option value="">Toutes les taxes</option>
            <?php foreach (['CFPB', 'CFPNB', 'PATENTE', 'TEOM', 'VIGNETTE'] as $t): ?>
                <option value="<?= $t ?>" <?= $typeFiltre === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="statut" class="form-select">
            <option value="">Tous les statuts</option>
            <?php foreach ($libellesStatut as $code => [$libelle, $couleur]): ?>
                <option value="<?= $code ?>" <?= $statutFiltre === $code ? 'selected' : '' ?>><?= $libelle ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" name="annee" class="form-control" placeholder="Année" value="<?= $anneeFiltre ?: '' ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Filtrer</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>Contribuable</th><th>Taxe</th><th>Exercice</th>
            <th class="text-end">Montant dû</th><th>Statut</th><th>Échéance</th><th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($taxations)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Aucune taxation trouvée.</td></tr>
        <?php endif; ?>
        <?php foreach ($taxations as $t): [$libelle, $couleur] = $libellesStatut[$t['statut']]; ?>
        <tr>
            <td><a href="contribuable_fiche.php?id=<?= (int) $t['contribuable_id'] ?>"><?= e($t['nom_raison_sociale']) ?></a></td>
            <td><span class="badge bg-info text-dark"><?= e($t['type_taxe']) ?></span></td>
            <td><?= (int) $t['annee_exercice'] ?></td>
            <td class="text-end fw-bold"><?= e(formaterMontant((float) $t['montant_du'])) ?></td>
            <td><span class="badge text-bg-<?= $couleur ?>"><?= e($libelle) ?></span></td>
            <td><?= e($t['date_echeance']) ?></td>
            <td>
                <?php if ($t['statut'] !== 'payee' && in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
                    <a href="paiements.php?taxation_id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-cash"></i> Encaisser
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#detail<?= (int) $t['id'] ?>">
                    <i class="bi bi-info-circle"></i>
                </button>
            </td>
        </tr>
        <tr class="collapse" id="detail<?= (int) $t['id'] ?>">
            <td colspan="7" class="bg-light">
                <pre class="mb-0 small"><?= e($t['detail_calcul'] ?? 'Aucun détail disponible.') ?></pre>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav><ul class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $pageActuelle ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($recherche) ?>&type=<?= urlencode($typeFiltre) ?>&statut=<?= urlencode($statutFiltre) ?>&annee=<?= $anneeFiltre ?>"><?= $p ?></a>
        </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>