<?php
/**
 * entete.php
 * ----------
 * Structure commune : sidebar fixe à gauche + zone de contenu à droite.
 * A inclure APRES avoir appelé exigerConnexion().
 */
$roleActuel = $_SESSION['utilisateur_role'] ?? 'consultant';
$pageActuelle = basename($_SERVER['PHP_SELF']);

function lienActif(string $fichier, string $pageActuelle): string
{
    return $fichier === $pageActuelle ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titrePage) ? e($titrePage) . ' — ' : '' ?>ImpôtsLocaux-SN</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="app-layout">

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <span><i class="bi bi-bank2"></i> ImpôtsLocaux-SN</span>
                <button id="boutonFermerSidebar" class="sidebar-close-btn d-lg-none" aria-label="Fermer le menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="<?= lienActif('index.php', $pageActuelle) ?>">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
                <a href="contribuables.php" class="<?= lienActif('contribuables.php', $pageActuelle) ?>">
                    <i class="bi bi-people"></i> Contribuables
                </a>
                <a href="biens.php" class="<?= lienActif('biens.php', $pageActuelle) ?>">
                    <i class="bi bi-houses"></i> Biens & terrains
                </a>
                <a href="taxations.php" class="<?= lienActif('taxations.php', $pageActuelle) ?>">
                    <i class="bi bi-receipt"></i> Taxations
                </a>

                <?php if (in_array($roleActuel, ['administrateur', 'agent'], true)): ?>
                    <div class="sidebar-section-title">Calculs</div>
                    <a href="calcul_cfpb_cfpnb.php" class="<?= lienActif('calcul_cfpb_cfpnb.php', $pageActuelle) ?>">
                        <i class="bi bi-calculator"></i> CFPB / CFPNB
                    </a>
                    <a href="calcul_patente.php" class="<?= lienActif('calcul_patente.php', $pageActuelle) ?>">
                        <i class="bi bi-briefcase"></i> Patente
                    </a>
                    <a href="calcul_teom_vignette.php" class="<?= lienActif('calcul_teom_vignette.php', $pageActuelle) ?>">
                        <i class="bi bi-trash3"></i> TEOM / Vignette
                    </a>
                    <a href="paiements.php" class="<?= lienActif('paiements.php', $pageActuelle) ?>">
                        <i class="bi bi-cash-coin"></i> Paiements
                    </a>
                <?php endif; ?>

                <?php if ($roleActuel === 'administrateur'): ?>
                    <div class="sidebar-section-title">Administration</div>
                    <a href="bareme.php" class="<?= lienActif('bareme.php', $pageActuelle) ?>">
                        <i class="bi bi-sliders"></i> Barème des taux
                    </a>
                    <div class="sidebar-section-title">Rapports</div>
                    <a href="export_taxations_pdf.php?annee=<?= date('Y') ?>"
                        class="<?= lienActif('export_taxations_pdf.php', $pageActuelle) ?>">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </a>
                    <a href="export_taxations_csv.php?annee=<?= date('Y') ?>"
                        class="<?= lienActif('export_taxations_csv.php', $pageActuelle) ?>">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV / Excel
                    </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-name"><?= e($_SESSION['utilisateur_nom'] ?? '') ?></div>
                    <span class="badge bg-secondary text-uppercase"><?= e($roleActuel) ?></span>
                </div>
                <a href="deconnexion.php" class="btn btn-outline-light btn-sm w-100 mt-2">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <div class="content-wrapper">
            <header class="topbar d-lg-none">
                <button id="boutonBasculerSidebar" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list"></i>
                </button>
                <span class="fw-semibold">ImpôtsLocaux-SN</span>
            </header>

            <main class="content-main">