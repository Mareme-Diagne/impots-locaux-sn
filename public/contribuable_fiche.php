<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

$pdo = obtenirConnexionBDD();
$id = (int) ($_GET['id'] ?? 0);

$requete = $pdo->prepare('SELECT * FROM contribuables WHERE id = :id');
$requete->execute(['id' => $id]);
$contribuable = $requete->fetch();

if (!$contribuable) {
    rediriger('erreur_404.php');
}

$biens = $pdo->prepare('SELECT * FROM biens_immobiliers WHERE contribuable_id = :id ORDER BY designation');
$biens->execute(['id' => $id]);
$biens = $biens->fetchAll();

$activites = $pdo->prepare('SELECT * FROM activites_patentables WHERE contribuable_id = :id');
$activites->execute(['id' => $id]);
$activites = $activites->fetchAll();

$vehicules = $pdo->prepare('SELECT * FROM vehicules WHERE contribuable_id = :id');
$vehicules->execute(['id' => $id]);
$vehicules = $vehicules->fetchAll();

$taxations = $pdo->prepare(
    'SELECT * FROM taxations WHERE contribuable_id = :id ORDER BY annee_exercice DESC, date_emission DESC'
);
$taxations->execute(['id' => $id]);
$taxations = $taxations->fetchAll();

$libellesUsage = [
    'residence_principale' => 'Résidence principale',
    'locatif' => 'Locatif',
    'commercial' => 'Commercial',
    'industriel' => 'Industriel',
    'terrain_nu' => 'Terrain nu',
];
$libellesStatutTaxation = [
    'emise' => ['Émise', 'secondary'],
    'payee' => ['Payée', 'success'],
    'partiellement_payee' => ['Partiellement payée', 'warning'],
    'en_retard' => ['En retard', 'danger'],
];

$titrePage = $contribuable['nom_raison_sociale'];
require __DIR__ . '/../includes/entete.php';
?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($contribuable['nom_raison_sociale']) ?></h1>
        <span class="badge bg-secondary"><?= $contribuable['type'] === 'entreprise' ? 'Entreprise' : 'Personne physique' ?></span>
        <?php if ($contribuable['ninea']): ?><span class="text-muted ms-2">NINEA : <?= e($contribuable['ninea']) ?></span><?php endif; ?>
    </div>
    <a href="contribuables.php" class="btn btn-outline-secondary btn-sm">&larr; Retour à la liste</a>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <table class="table table-sm">
            <tr><th style="width:160px;">Téléphone</th><td><?= e($contribuable['telephone'] ?? '—') ?></td></tr>
            <tr><th>Email</th><td><?= e($contribuable['email'] ?? '—') ?></td></tr>
            <tr><th>Adresse</th><td><?= e($contribuable['adresse'] ?? '—') ?></td></tr>
            <tr><th>Commune</th><td><?= e($contribuable['commune'] ?? '—') ?></td></tr>
        </table>
    </div>
</div>

<h2 class="h5">Biens & terrains (<?= count($biens) ?>)</h2>
<div class="table-responsive mb-4">
<table class="table table-sm table-striped">
    <thead><tr><th>Désignation</th><th>Nature</th><th>Usage</th><th>Commune</th><th class="text-end">Valeur locative / vénale</th></tr></thead>
    <tbody>
        <?php if (empty($biens)): ?><tr><td colspan="5" class="text-muted text-center py-3">Aucun bien enregistré.</td></tr><?php endif; ?>
        <?php foreach ($biens as $b): ?>
        <tr>
            <td><?= e($b['designation']) ?></td>
            <td><?= $b['nature'] === 'bati' ? 'Bâti' : 'Non bâti' ?></td>
            <td><?= e($libellesUsage[$b['usage_bien']] ?? $b['usage_bien']) ?></td>
            <td><?= e($b['commune']) ?></td>
            <td class="text-end"><?= e(formaterMontant((float) ($b['valeur_locative_annuelle'] ?? $b['valeur_venale'] ?? 0))) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2 class="h5">Activités patentables (<?= count($activites) ?>)</h2>
<div class="table-responsive mb-4">
<table class="table table-sm table-striped">
    <thead><tr><th>Activité</th><th class="text-end">Chiffre d'affaires annuel</th><th class="text-end">Valeur locative des locaux</th></tr></thead>
    <tbody>
        <?php if (empty($activites)): ?><tr><td colspan="3" class="text-muted text-center py-3">Aucune activité enregistrée.</td></tr><?php endif; ?>
        <?php foreach ($activites as $a): ?>
        <tr>
            <td><?= e($a['libelle_activite']) ?></td>
            <td class="text-end"><?= e(formaterMontant((float) $a['chiffre_affaires_annuel'])) ?></td>
            <td class="text-end"><?= e(formaterMontant((float) $a['valeur_locative_locaux'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2 class="h5">Véhicules (<?= count($vehicules) ?>)</h2>
<div class="table-responsive mb-4">
<table class="table table-sm table-striped">
    <thead><tr><th>Immatriculation</th><th>Catégorie</th><th class="text-end">Puissance fiscale</th></tr></thead>
    <tbody>
        <?php if (empty($vehicules)): ?><tr><td colspan="3" class="text-muted text-center py-3">Aucun véhicule enregistré.</td></tr><?php endif; ?>
        <?php foreach ($vehicules as $v): ?>
        <tr>
            <td><?= e($v['immatriculation']) ?></td>
            <td><?= e(ucfirst($v['categorie'])) ?></td>
            <td class="text-end"><?= (int) $v['puissance_fiscale'] ?> CV</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2 class="h5">Historique des taxations (<?= count($taxations) ?>)</h2>
<div class="table-responsive mb-4">
<table class="table table-sm table-striped">
    <thead><tr><th>Taxe</th><th>Exercice</th><th class="text-end">Montant dû</th><th>Statut</th><th>Échéance</th></tr></thead>
    <tbody>
        <?php if (empty($taxations)): ?><tr><td colspan="5" class="text-muted text-center py-3">Aucune taxation générée pour ce contribuable.</td></tr><?php endif; ?>
        <?php foreach ($taxations as $t): [$libelle, $couleur] = $libellesStatutTaxation[$t['statut']]; ?>
        <tr>
            <td><?= e($t['type_taxe']) ?></td>
            <td><?= (int) $t['annee_exercice'] ?></td>
            <td class="text-end"><?= e(formaterMontant((float) $t['montant_du'])) ?></td>
            <td><span class="badge text-bg-<?= $couleur ?>"><?= e($libelle) ?></span></td>
            <td><?= e($t['date_echeance']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
<a href="contribuable_form.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Modifier</a>
<a href="bien_form.php?contribuable_id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> Ajouter un bien</a>
<?php endif; ?>

<?php require __DIR__ . '/../includes/pied.php'; ?>