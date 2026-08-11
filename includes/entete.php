<?php
/**
 * entete.php
 * ----------
 * Barre de navigation commune à toutes les pages protégées.
 * A inclure APRES avoir appelé exigerConnexion(), pour que
 * $_SESSION['utilisateur_role'] soit toujours disponible ici.
 *
 * Le menu s'adapte au rôle : un "consultant" ne voit pas les liens
 * de saisie ou de configuration des taux, réservés à l'agent/l'admin.
 */
$roleActuel = $_SESSION['utilisateur_role'] ?? 'consultant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titrePage) ? e($titrePage) . ' — ' : '' ?>ImpôtsLocaux-SN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">ImpôtsLocaux-SN</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
                <li class="nav-item"><a class="nav-link" href="contribuables.php"><i class="bi bi-people"></i> Contribuables</a></li>
                <li class="nav-item"><a class="nav-link" href="biens.php"><i class="bi bi-houses"></i> Biens & terrains</a></li>
                <li class="nav-item"><a class="nav-link" href="taxations.php"><i class="bi bi-receipt"></i> Taxations</a></li>
                <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
                <li class="nav-item"><a class="nav-link" href="calcul_cfpb_cfpnb.php"><i class="bi bi-calculator"></i> Calcul CFPB/CFPNB</a></li>
                <li class="nav-item"><a class="nav-link" href="paiements.php"><i class="bi bi-cash-coin"></i> Paiements</a></li>
                <?php endif; ?>
                <?php if ($roleActuel === 'administrateur'): ?>
                <li class="nav-item"><a class="nav-link" href="bareme.php"><i class="bi bi-sliders"></i> Barème des taux</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-light me-3">
                <?= e($_SESSION['utilisateur_nom'] ?? '') ?>
                <span class="badge bg-secondary text-uppercase"><?= e($roleActuel) ?></span>
            </span>
            <a href="deconnexion.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>
</nav>
<main class="container-fluid py-4">
