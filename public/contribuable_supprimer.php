<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(['administrateur', 'agent']);

$pdo = obtenirConnexionBDD();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$requete = $pdo->prepare('SELECT * FROM contribuables WHERE id = :id');
$requete->execute(['id' => $id]);
$contribuable = $requete->fetch();

if (!$contribuable) {
    http_response_code(404);
    die('Contribuable introuvable.');
}

// La suppression réelle n'a lieu QUE sur une requête POST confirmée : un simple lien GET
// (comme celui de la liste) ne fait qu'amener sur cette page de confirmation, il ne supprime rien.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierJetonCsrf($_POST['jeton_csrf'] ?? null)) {
        http_response_code(403);
        die('Requête invalide (jeton de sécurité manquant ou expiré). Retournez à la liste et réessayez.');
    }

    $suppression = $pdo->prepare('DELETE FROM contribuables WHERE id = :id');
    $suppression->execute(['id' => $id]);

    journaliser($_SESSION['utilisateur_id'], 'SUPPRESSION_CONTRIBUABLE', 'ID ' . $id . ' : ' . $contribuable['nom_raison_sociale']);

    rediriger('contribuables.php');
}

$titrePage = 'Confirmer la suppression';
require __DIR__ . '/../includes/entete.php';
?>

<div class="alert alert-danger" style="max-width: 600px;">
    <h2 class="h5">Confirmer la suppression</h2>
    <p>Vous êtes sur le point de supprimer définitivement le contribuable
       <strong><?= e($contribuable['nom_raison_sociale']) ?></strong>, ainsi que <strong>tous ses biens,
       activités, véhicules et taxations liés</strong>. Cette action est irréversible.</p>
    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="jeton_csrf" value="<?= e(jetonCsrf()) ?>">
        <button type="submit" class="btn btn-danger">Oui, supprimer définitivement</button>
        <a href="contribuables.php" class="btn btn-outline-secondary">Annuler</a>
    </form>
</div>

<?php require __DIR__ . '/../includes/pied.php'; ?>