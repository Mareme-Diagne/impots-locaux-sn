<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();
$titrePage = 'À venir';
require __DIR__ . '/../includes/entete.php';
?>
<div class="alert alert-info">Ce module (<?= e('taxations') ?>) sera construit à une prochaine étape du projet.</div>
<?php require __DIR__ . '/../includes/pied.php'; ?>
