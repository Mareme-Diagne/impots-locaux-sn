<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(['administrateur', 'agent']); // le consultant ne peut pas créer/modifier

$pdo = obtenirConnexionBDD();

$idContribuable = isset($_GET['id']) ? (int) $_GET['id'] : null;
$modeEdition = $idContribuable !== null;

$valeurs = [
    'type' => 'entreprise',
    'nom_raison_sociale' => '',
    'ninea' => '',
    'telephone' => '',
    'email' => '',
    'adresse' => '',
    'commune' => '',
];

if ($modeEdition) {
    $requete = $pdo->prepare('SELECT * FROM contribuables WHERE id = :id');
    $requete->execute(['id' => $idContribuable]);
    $contribuableExistant = $requete->fetch();

    if (!$contribuableExistant) {
        http_response_code(404);
        die('Contribuable introuvable.');
    }
    $valeurs = $contribuableExistant;
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['type']               = $_POST['type'] ?? '';
    $valeurs['nom_raison_sociale'] = trim((string) ($_POST['nom_raison_sociale'] ?? ''));
    $valeurs['ninea']              = trim((string) ($_POST['ninea'] ?? '')) ?: null;
    $valeurs['telephone']          = trim((string) ($_POST['telephone'] ?? '')) ?: null;
    $valeurs['email']              = trim((string) ($_POST['email'] ?? '')) ?: null;
    $valeurs['adresse']            = trim((string) ($_POST['adresse'] ?? '')) ?: null;
    $valeurs['commune']            = trim((string) ($_POST['commune'] ?? '')) ?: null;

    // --- Validation côté serveur : la seule qui compte vraiment pour la sécurité ---
    if (!in_array($valeurs['type'], ['personne_physique', 'entreprise'], true)) {
        $erreurs[] = 'Le type de contribuable est invalide.';
    }
    if ($valeurs['nom_raison_sociale'] === '') {
        $erreurs[] = 'Le nom (ou la raison sociale) est obligatoire.';
    }
    if ($valeurs['type'] === 'entreprise' && ($valeurs['ninea'] === null || $valeurs['ninea'] === '')) {
        $erreurs[] = 'Le NINEA est obligatoire pour une entreprise.';
    }
    if ($valeurs['email'] !== null && $valeurs['email'] !== '' && !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'adresse email n\'est pas valide.';
    }

    if (empty($erreurs)) {
        if ($modeEdition) {
            $requete = $pdo->prepare(
                'UPDATE contribuables SET type = :type, nom_raison_sociale = :nom, ninea = :ninea,
                 telephone = :telephone, email = :email, adresse = :adresse, commune = :commune
                 WHERE id = :id'
            );
            $requete->execute([
                'type' => $valeurs['type'], 'nom' => $valeurs['nom_raison_sociale'], 'ninea' => $valeurs['ninea'],
                'telephone' => $valeurs['telephone'], 'email' => $valeurs['email'],
                'adresse' => $valeurs['adresse'], 'commune' => $valeurs['commune'], 'id' => $idContribuable,
            ]);
            journaliser($_SESSION['utilisateur_id'], 'MODIFICATION_CONTRIBUABLE', 'ID ' . $idContribuable . ' : ' . $valeurs['nom_raison_sociale']);
        } else {
            $requete = $pdo->prepare(
                'INSERT INTO contribuables (type, nom_raison_sociale, ninea, telephone, email, adresse, commune)
                 VALUES (:type, :nom, :ninea, :telephone, :email, :adresse, :commune)'
            );
            $requete->execute([
                'type' => $valeurs['type'], 'nom' => $valeurs['nom_raison_sociale'], 'ninea' => $valeurs['ninea'],
                'telephone' => $valeurs['telephone'], 'email' => $valeurs['email'],
                'adresse' => $valeurs['adresse'], 'commune' => $valeurs['commune'],
            ]);
            $idContribuable = (int) $pdo->lastInsertId();
            journaliser($_SESSION['utilisateur_id'], 'CREATION_CONTRIBUABLE', 'ID ' . $idContribuable . ' : ' . $valeurs['nom_raison_sociale']);
        }

        rediriger('contribuable_fiche.php?id=' . $idContribuable);
    }
}

$titrePage = $modeEdition ? 'Modifier un contribuable' : 'Nouveau contribuable';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-4"><?= e($titrePage) ?></h1>

<?php if (!empty($erreurs)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="row g-3" novalidate id="formulaireContribuable" style="max-width: 720px;">
    <div class="col-md-4">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select name="type" class="form-select" required id="champType">
            <option value="entreprise" <?= $valeurs['type'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
            <option value="personne_physique" <?= $valeurs['type'] === 'personne_physique' ? 'selected' : '' ?>>Personne physique</option>
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label">Nom / Raison sociale <span class="text-danger">*</span></label>
        <input type="text" name="nom_raison_sociale" class="form-control" required minlength="2"
               value="<?= e($valeurs['nom_raison_sociale']) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">NINEA <span class="text-danger" id="etoileNinea">*</span></label>
        <input type="text" name="ninea" class="form-control" id="champNinea" value="<?= e($valeurs['ninea'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Téléphone</label>
        <input type="text" name="telephone" class="form-control" value="<?= e($valeurs['telephone'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e($valeurs['email'] ?? '') ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label">Adresse</label>
        <input type="text" name="adresse" class="form-control" value="<?= e($valeurs['adresse'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">Commune</label>
        <input type="text" name="commune" class="form-control" value="<?= e($valeurs['commune'] ?? '') ?>">
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $modeEdition ? 'Enregistrer les modifications' : 'Créer le contribuable' ?></button>
        <a href="contribuables.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
    // Petit confort : le NINEA n'est requis (en JS comme en PHP) que pour une entreprise.
    const champType  = document.getElementById('champType');
    const champNinea = document.getElementById('champNinea');
    function ajusterObligationNinea() {
        champNinea.required = (champType.value === 'entreprise');
    }
    champType.addEventListener('change', ajusterObligationNinea);
    ajusterObligationNinea();
</script>

<?php require __DIR__ . '/../includes/pied.php'; ?>