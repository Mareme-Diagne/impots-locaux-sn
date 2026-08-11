<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

demarrerSession();

if (!empty($_SESSION['utilisateur_id'])) {
    rediriger('/impots-locaux-sn/public/index.php');
}

$messageErreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant     = trim((string) ($_POST['identifiant'] ?? ''));
    $motDePasseSaisi = (string) ($_POST['mot_de_passe'] ?? '');

    if ($identifiant === '' || $motDePasseSaisi === '') {
        $messageErreur = 'Veuillez renseigner votre identifiant et votre mot de passe.';
    } else {
        $_SESSION['tentatives_connexion'] ??= [];
        $_SESSION['tentatives_connexion'] = array_filter(
            $_SESSION['tentatives_connexion'],
            fn($horodatage) => $horodatage > time() - 60
        );

        if (count($_SESSION['tentatives_connexion']) >= 5) {
            $messageErreur = 'Trop de tentatives. Merci de patienter une minute avant de réessayer.';
        } else {
            $utilisateur = tenterConnexion($identifiant, $motDePasseSaisi);

            if ($utilisateur === null) {
                $_SESSION['tentatives_connexion'][] = time();
                $messageErreur = 'Identifiant ou mot de passe incorrect.';
            } else {
                ouvrirSessionUtilisateur($utilisateur);
                rediriger('/impots-locaux-sn/public/index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — ImpôtsLocaux-SN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="page-connexion">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
            <div class="card-body p-4">
                <h1 class="h4 text-center mb-1">ImpôtsLocaux-SN</h1>
                <p class="text-center text-muted mb-4">Gestion des taxes locales et parafiscales</p>

                <?php if ($messageErreur !== ''): ?>
                    <div class="alert alert-danger py-2"><?= e($messageErreur) ?></div>
                <?php endif; ?>

                <form method="post" novalidate id="formulaireConnexion">
                    <div class="mb-3">
                        <label for="identifiant" class="form-label">Identifiant</label>
                        <input type="text" class="form-control" id="identifiant" name="identifiant"
                               required minlength="3" autofocus
                               value="<?= e($_POST['identifiant'] ?? '') ?>">
                        <div class="invalid-feedback">Veuillez saisir votre identifiant.</div>
                    </div>
                    <div class="mb-3">
                        <label for="mot_de_passe" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe"
                               required minlength="4">
                        <div class="invalid-feedback">Veuillez saisir votre mot de passe.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </form>

                <p class="text-muted small mt-3 mb-0 text-center">
                    Comptes de démonstration disponibles dans le README du projet.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script>
        const formulaire = document.getElementById('formulaireConnexion');
        formulaire.addEventListener('submit', function (evenement) {
            if (!formulaire.checkValidity()) {
                evenement.preventDefault();
                evenement.stopPropagation();
            }
            formulaire.classList.add('was-validated');
        });
    </script>
</body>
</html>