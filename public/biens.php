<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pdo = obtenirConnexionBDD();

// --- Recherche et filtres ---
$recherche   = trim((string) ($_GET['q'] ?? ''));
$natureFiltre = $_GET['nature'] ?? '';
$contribuableFiltre = (int) ($_GET['contribuable_id'] ?? 0);

$conditions = [];
$parametres = [];

if ($recherche !== '') {
    $conditions[] = '(b.designation LIKE :recherche OR b.commune LIKE :recherche OR c.nom_raison_sociale LIKE :recherche)';
    $parametres['recherche'] = '%' . $recherche . '%';
}
if (in_array($natureFiltre, ['bati', 'non_bati'], true)) {
    $conditions[] = 'b.nature = :nature';
    $parametres['nature'] = $natureFiltre;
}
if ($contribuableFiltre > 0) {
    $conditions[] = 'b.contribuable_id = :contribuable_id';
    $parametres['contribuable_id'] = $contribuableFiltre;
}

$clauseOu = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// --- Pagination (>= 20 résultats/page) ---
$parPage = 20;
$pageActuelle = max(1, (int) ($_GET['page'] ?? 1));
$decalage = ($pageActuelle - 1) * $parPage;

$requeteTotal = $pdo->prepare("SELECT COUNT(*) FROM biens_immobiliers b JOIN contribuables c ON c.id = b.contribuable_id $clauseOu");
$requeteTotal->execute($parametres);
$totalResultats = (int) $requeteTotal->fetchColumn();
$totalPages = (int) max(1, ceil($totalResultats / $parPage));

$sql = "SELECT b.*, c.nom_raison_sociale
        FROM biens_immobiliers b
        JOIN contribuables c ON c.id = b.contribuable_id
        $clauseOu
        ORDER BY b.designation ASC
        LIMIT :limite OFFSET :decalage";
$requete = $pdo->prepare($sql);
foreach ($parametres as $cle => $valeur) {
    $requete->bindValue($cle, $valeur);
}
$requete->bindValue('limite', $parPage, PDO::PARAM_INT);
$requete->bindValue('decalage', $decalage, PDO::PARAM_INT);
$requete->execute();
$biens = $requete->fetchAll();

$libellesUsage = [
    'residence_principale' => 'Résidence principale',
    'locatif' => 'Locatif',
    'commercial' => 'Commercial',
    'industriel' => 'Industriel',
    'terrain_nu' => 'Terrain nu',
];

$titrePage = 'Biens & terrains';
require __DIR__ . '/../includes/entete.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Biens & terrains</h1>
    <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
        <a href="bien_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau bien</a>
    <?php endif; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="text" name="q" class="form-control" placeholder="Désignation, commune, contribuable..."
               value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-3">
        <select name="nature" class="form-select">
            <option value="">Toutes natures</option>
            <option value="bati" <?= $natureFiltre === 'bati' ? 'selected' : '' ?>>Bâti</option>
            <option value="non_bati" <?= $natureFiltre === 'non_bati' ? 'selected' : '' ?>>Non bâti</option>
        </select>
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Rechercher</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>Désignation</th>
            <th>Contribuable</th>
            <th>Nature</th>
            <th>Usage</th>
            <th>Commune</th>
            <th class="text-end">Valeur locative / vénale</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($biens)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Aucun bien trouvé.</td></tr>
        <?php endif; ?>
        <?php foreach ($biens as $b): ?>
        <tr>
            <td><?= e($b['designation']) ?></td>
            <td><a href="contribuable_fiche.php?id=<?= (int) $b['contribuable_id'] ?>"><?= e($b['nom_raison_sociale']) ?></a></td>
            <td><?= $b['nature'] === 'bati' ? 'Bâti' : 'Non bâti' ?></td>
            <td><?= e($libellesUsage[$b['usage_bien']] ?? $b['usage_bien']) ?></td>
            <td><?= e($b['commune']) ?></td>
            <td class="text-end"><?= e(formaterMontant((float) ($b['valeur_locative_annuelle'] ?? $b['valeur_venale'] ?? 0))) ?></td>
            <td class="text-end">
                <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
                <a href="bien_form.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i>
                </a>
                <a href="bien_supprimer.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $pageActuelle ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($recherche) ?>&nature=<?= urlencode($natureFiltre) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>