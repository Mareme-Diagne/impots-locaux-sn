<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pdo = obtenirConnexionBDD();

// --- Recherche et filtre (fonctionnalité obligatoire du cahier des charges) ---
$recherche = trim((string) ($_GET['q'] ?? ''));
$typeFiltre = $_GET['type'] ?? '';

$conditions = [];
$parametres = [];

if ($recherche !== '') {
    $conditions[] = '(nom_raison_sociale LIKE :recherche OR ninea LIKE :recherche OR email LIKE :recherche)';
    $parametres['recherche'] = '%' . $recherche . '%';
}
if (in_array($typeFiltre, ['personne_physique', 'entreprise'], true)) {
    $conditions[] = 'type = :type';
    $parametres['type'] = $typeFiltre;
}

$clauseOu = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// --- Pagination (au moins 20 résultats par page, exigé par le cahier des charges) ---
$parPage = 20;
$pageActuelle = max(1, (int) ($_GET['page'] ?? 1));
$decalage = ($pageActuelle - 1) * $parPage;

$requeteTotal = $pdo->prepare("SELECT COUNT(*) FROM contribuables $clauseOu");
$requeteTotal->execute($parametres);
$totalResultats = (int) $requeteTotal->fetchColumn();
$totalPages = (int) max(1, ceil($totalResultats / $parPage));

$sql = "SELECT * FROM contribuables $clauseOu ORDER BY nom_raison_sociale ASC LIMIT :limite OFFSET :decalage";
$requete = $pdo->prepare($sql);
foreach ($parametres as $cle => $valeur) {
    $requete->bindValue($cle, $valeur);
}
$requete->bindValue('limite', $parPage, PDO::PARAM_INT);
$requete->bindValue('decalage', $decalage, PDO::PARAM_INT);
$requete->execute();
$contribuables = $requete->fetchAll();

$titrePage = 'Contribuables';
require __DIR__ . '/../includes/entete.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Contribuables</h1>
    <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
        <a href="contribuable_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau contribuable</a>
    <?php endif; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="text" name="q" class="form-control" placeholder="Nom, NINEA ou email..."
               value="<?= e($recherche) ?>">
    </div>
    <div class="col-md-3">
        <select name="type" class="form-select">
            <option value="">Tous les types</option>
            <option value="personne_physique" <?= $typeFiltre === 'personne_physique' ? 'selected' : '' ?>>Personne physique</option>
            <option value="entreprise" <?= $typeFiltre === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
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
            <th>Nom / Raison sociale</th>
            <th>Type</th>
            <th>NINEA</th>
            <th>Téléphone</th>
            <th>Commune</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($contribuables)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun contribuable trouvé.</td></tr>
        <?php endif; ?>
        <?php foreach ($contribuables as $c): ?>
        <tr>
            <td><?= e($c['nom_raison_sociale']) ?></td>
            <td><?= $c['type'] === 'entreprise' ? 'Entreprise' : 'Personne physique' ?></td>
            <td><?= e($c['ninea'] ?? '—') ?></td>
            <td><?= e($c['telephone'] ?? '—') ?></td>
            <td><?= e($c['commune'] ?? '—') ?></td>
            <td class="text-end">
                <a href="contribuable_fiche.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i> Voir
                </a>
                <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
                <a href="contribuable_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i>
                </a>
                <a href="contribuable_supprimer.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Supprimer ce contribuable et tous ses biens/taxations liés ?');">
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
                <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($recherche) ?>&type=<?= urlencode($typeFiltre) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>