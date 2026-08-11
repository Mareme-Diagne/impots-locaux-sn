<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$requete = $pdo->prepare('SELECT * FROM biens_immobiliers WHERE id = :id');
$requete->execute(['id' => $id]);
$bien = $requete->fetch();

if (!$bien) {
    http_response_code(404);
    die('Bien introuvable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $suppression = $pdo->prepare('DELETE FROM biens_immobiliers WHERE id = :id');
    $suppression->execute(['id' => $id]);

    journaliser($_SESSION['utilisateur_id'], 'SUPPRESSION_BIEN', 'ID ' . $id . ' : ' . $bien['designation']);

    rediriger('biens.php');
}

$titrePage = 'Confirmer la suppression';
require __DIR__ . '/../includes/entete.php';
?>

<div class="alert alert-danger" style="max-width: 600px;">
    <h2 class="h5">Confirmer la suppression</h2>
    <p>Vous êtes sur le point de supprimer définitivement le bien
       <strong><?= e($bien['designation']) ?></strong> et toutes les taxations liées. Cette action est irréversible.</p>
    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-danger">Oui, supprimer définitivement</button>
        <a href="biens.php" class="btn btn-outline-secondary">Annuler</a>
    </form>
</div>

<?php require __DIR__ . '/../includes/pied.php'; ?>