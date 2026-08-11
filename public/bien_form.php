<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();

$idBien = isset($_GET['id']) ? (int) $_GET['id'] : null;
$modeEdition = $idBien !== null;

$valeurs = [
    'contribuable_id' => (int) ($_GET['contribuable_id'] ?? 0),
    'designation' => '',
    'nature' => 'bati',
    'usage_bien' => 'commercial',
    'commune' => '',
    'valeur_locative_annuelle' => '',
    'valeur_venale' => '',
    'annee_achevement' => '',
];

if ($modeEdition) {
    $requete = $pdo->prepare('SELECT * FROM biens_immobiliers WHERE id = :id');
    $requete->execute(['id' => $idBien]);
    $bienExistant = $requete->fetch();
    if (!$bienExistant) {
        http_response_code(404);
        die('Bien introuvable.');
    }
    $valeurs = $bienExistant;
}

// Liste des contribuables pour le menu déroulant (peu de contribuables attendus sur ce projet
// académique — une vraie plateforme à grande échelle utiliserait une recherche AJAX à la place).
$contribuables = $pdo->query('SELECT id, nom_raison_sociale FROM contribuables ORDER BY nom_raison_sociale')->fetchAll();

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['contribuable_id'] = (int) ($_POST['contribuable_id'] ?? 0);
    $valeurs['designation']     = trim((string) ($_POST['designation'] ?? ''));
    $valeurs['nature']          = $_POST['nature'] ?? '';
    $valeurs['usage_bien']      = $_POST['usage_bien'] ?? '';
    $valeurs['commune']         = trim((string) ($_POST['commune'] ?? ''));
    $valeurs['valeur_locative_annuelle'] = trim((string) ($_POST['valeur_locative_annuelle'] ?? ''));
    $valeurs['valeur_venale']    = trim((string) ($_POST['valeur_venale'] ?? ''));
    $valeurs['annee_achevement'] = trim((string) ($_POST['annee_achevement'] ?? ''));

    // --- Validation côté serveur ---
    if ($valeurs['contribuable_id'] <= 0) {
        $erreurs[] = 'Veuillez choisir le contribuable propriétaire du bien.';
    }
    if ($valeurs['designation'] === '') {
        $erreurs[] = 'La désignation du bien est obligatoire.';
    }
    if (!in_array($valeurs['nature'], ['bati', 'non_bati'], true)) {
        $erreurs[] = 'La nature du bien est invalide.';
    }
    if (!in_array($valeurs['usage_bien'], ['residence_principale', 'locatif', 'commercial', 'industriel', 'terrain_nu'], true)) {
        $erreurs[] = 'L\'usage du bien est invalide.';
    }
    if ($valeurs['commune'] === '') {
        $erreurs[] = 'La commune est obligatoire.';
    }
    // Un bien bâti doit avoir une valeur locative ; un terrain non bâti une valeur vénale.
    if ($valeurs['nature'] === 'bati' && ($valeurs['valeur_locative_annuelle'] === '' || !is_numeric($valeurs['valeur_locative_annuelle']))) {
        $erreurs[] = 'La valeur locative annuelle est obligatoire et doit être un nombre pour un bien bâti.';
    }
    if ($valeurs['nature'] === 'non_bati' && ($valeurs['valeur_venale'] === '' || !is_numeric($valeurs['valeur_venale']))) {
        $erreurs[] = 'La valeur vénale est obligatoire et doit être un nombre pour un terrain non bâti.';
    }
    if ($valeurs['annee_achevement'] !== '' && (!ctype_digit($valeurs['annee_achevement']) || (int) $valeurs['annee_achevement'] > (int) date('Y'))) {
        $erreurs[] = 'L\'année d\'achèvement doit être une année valide, pas dans le futur.';
    }

    if (empty($erreurs)) {
        // On ne garde que la valeur pertinente selon la nature du bien (l'autre reste NULL).
        $valeurLocative = $valeurs['nature'] === 'bati' ? (float) $valeurs['valeur_locative_annuelle'] : null;
        $valeurVenale    = $valeurs['nature'] === 'non_bati' ? (float) $valeurs['valeur_venale'] : null;
        $anneeAchevement = $valeurs['annee_achevement'] !== '' ? (int) $valeurs['annee_achevement'] : null;

        if ($modeEdition) {
            $requete = $pdo->prepare(
                'UPDATE biens_immobiliers SET contribuable_id = :contribuable_id, designation = :designation,
                 nature = :nature, usage_bien = :usage_bien, commune = :commune,
                 valeur_locative_annuelle = :vla, valeur_venale = :vv, annee_achevement = :annee
                 WHERE id = :id'
            );
            $requete->execute([
                'contribuable_id' => $valeurs['contribuable_id'], 'designation' => $valeurs['designation'],
                'nature' => $valeurs['nature'], 'usage_bien' => $valeurs['usage_bien'], 'commune' => $valeurs['commune'],
                'vla' => $valeurLocative, 'vv' => $valeurVenale, 'annee' => $anneeAchevement, 'id' => $idBien,
            ]);
            journaliser($_SESSION['utilisateur_id'], 'MODIFICATION_BIEN', 'ID ' . $idBien . ' : ' . $valeurs['designation']);
        } else {
            $requete = $pdo->prepare(
                'INSERT INTO biens_immobiliers (contribuable_id, designation, nature, usage_bien, commune,
                 valeur_locative_annuelle, valeur_venale, annee_achevement)
                 VALUES (:contribuable_id, :designation, :nature, :usage_bien, :commune, :vla, :vv, :annee)'
            );
            $requete->execute([
                'contribuable_id' => $valeurs['contribuable_id'], 'designation' => $valeurs['designation'],
                'nature' => $valeurs['nature'], 'usage_bien' => $valeurs['usage_bien'], 'commune' => $valeurs['commune'],
                'vla' => $valeurLocative, 'vv' => $valeurVenale, 'annee' => $anneeAchevement,
            ]);
            $idBien = (int) $pdo->lastInsertId();
            journaliser($_SESSION['utilisateur_id'], 'CREATION_BIEN', 'ID ' . $idBien . ' : ' . $valeurs['designation']);
        }

        rediriger('contribuable_fiche.php?id=' . $valeurs['contribuable_id']);
    }
}

$titrePage = $modeEdition ? 'Modifier un bien' : 'Nouveau bien';
require __DIR__ . '/../includes/entete.php';
?>

<h1 class="h3 mb-4"><?= e($titrePage) ?></h1>

<?php if (!empty($erreurs)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="row g-3" novalidate id="formulaireBien" style="max-width: 720px;">
    <div class="col-md-8">
        <label class="form-label">Contribuable propriétaire <span class="text-danger">*</span></label>
        <select name="contribuable_id" class="form-select" required>
            <option value="">-- Choisir --</option>
            <?php foreach ($contribuables as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $valeurs['contribuable_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['nom_raison_sociale']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Nature <span class="text-danger">*</span></label>
        <select name="nature" class="form-select" required id="champNature">
            <option value="bati" <?= $valeurs['nature'] === 'bati' ? 'selected' : '' ?>>Bâti</option>
            <option value="non_bati" <?= $valeurs['nature'] === 'non_bati' ? 'selected' : '' ?>>Non bâti (terrain)</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Désignation <span class="text-danger">*</span></label>
        <input type="text" name="designation" class="form-control" required minlength="2"
               placeholder="Ex : Villa Sacré-Coeur 3" value="<?= e($valeurs['designation']) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Usage <span class="text-danger">*</span></label>
        <select name="usage_bien" class="form-select" required>
            <option value="residence_principale" <?= $valeurs['usage_bien'] === 'residence_principale' ? 'selected' : '' ?>>Résidence principale</option>
            <option value="locatif" <?= $valeurs['usage_bien'] === 'locatif' ? 'selected' : '' ?>>Locatif</option>
            <option value="commercial" <?= $valeurs['usage_bien'] === 'commercial' ? 'selected' : '' ?>>Commercial</option>
            <option value="industriel" <?= $valeurs['usage_bien'] === 'industriel' ? 'selected' : '' ?>>Industriel</option>
            <option value="terrain_nu" <?= $valeurs['usage_bien'] === 'terrain_nu' ? 'selected' : '' ?>>Terrain nu</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Commune <span class="text-danger">*</span></label>
        <input type="text" name="commune" class="form-control" required value="<?= e($valeurs['commune']) ?>">
    </div>

    <div class="col-md-6" id="blocValeurLocative">
        <label class="form-label">Valeur locative annuelle (FCFA) <span class="text-danger">*</span></label>
        <input type="number" step="1" min="0" name="valeur_locative_annuelle" class="form-control"
               value="<?= e((string) $valeurs['valeur_locative_annuelle']) ?>">
        <div class="form-text">Utilisée pour le calcul de la CFPB (bien bâti).</div>
    </div>
    <div class="col-md-6" id="blocValeurVenale">
        <label class="form-label">Valeur vénale (FCFA) <span class="text-danger">*</span></label>
        <input type="number" step="1" min="0" name="valeur_venale" class="form-control"
               value="<?= e((string) $valeurs['valeur_venale']) ?>">
        <div class="form-text">Utilisée pour le calcul de la CFPNB (terrain non bâti).</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Année d'achèvement de la construction</label>
        <input type="number" min="1900" max="<?= date('Y') ?>" name="annee_achevement" class="form-control"
               value="<?= e((string) $valeurs['annee_achevement']) ?>">
        <div class="form-text">Une construction est exonérée de CFPB les 5 années suivant son achèvement.</div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?= $modeEdition ? 'Enregistrer les modifications' : 'Créer le bien' ?></button>
        <a href="biens.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>

<script>
    // Affiche uniquement le champ pertinent selon la nature du bien (bâti => valeur locative,
    // non bâti => valeur vénale), pour éviter de demander une info qui ne sert à rien.
    const champNature = document.getElementById('champNature');
    const blocLocative = document.getElementById('blocValeurLocative');
    const blocVenale   = document.getElementById('blocValeurVenale');

    function ajusterAffichageChamps() {
        const estBati = champNature.value === 'bati';
        blocLocative.style.display = estBati ? '' : 'none';
        blocVenale.style.display   = estBati ? 'none' : '';
        blocLocative.querySelector('input').required = estBati;
        blocVenale.querySelector('input').required    = !estBati;
    }
    champNature.addEventListener('change', ajusterAffichageChamps);
    ajusterAffichageChamps();
</script>

<?php require __DIR__ . '/../includes/pied.php'; ?>