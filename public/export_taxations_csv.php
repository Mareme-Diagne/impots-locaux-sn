<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion(); // tous les rôles connectés peuvent exporter (lecture seule)

$pdo = obtenirConnexionBDD();
$anneeExercice = (int) ($_GET['annee'] ?? date('Y'));

$requete = $pdo->prepare(
    'SELECT c.nom_raison_sociale, t.type_taxe, t.annee_exercice, t.base_calcul, t.montant_du,
            t.statut, t.date_emission, t.date_echeance
     FROM taxations t
     JOIN contribuables c ON c.id = t.contribuable_id
     WHERE t.annee_exercice = :annee
     ORDER BY c.nom_raison_sociale, t.type_taxe'
);
$requete->execute(['annee' => $anneeExercice]);
$lignes = $requete->fetchAll();

// --- En-têtes HTTP pour forcer le téléchargement plutôt que l'affichage dans le navigateur ---
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="taxations_' . $anneeExercice . '.csv"');

$sortie = fopen('php://output', 'w');

// Le BOM UTF-8 est indispensable pour qu'Excel affiche correctement les accents
// (sans lui, Excel suppose souvent un encodage Windows-1252 et affiche des caractères bizarres).
fwrite($sortie, "\xEF\xBB\xBF");

// Séparateur point-virgule : Excel en français attend ";" plutôt que "," par défaut
fputcsv($sortie, ['Contribuable', 'Type de taxe', 'Exercice', 'Base de calcul', 'Montant dû', 'Statut', 'Date émission', 'Date échéance'], ';');

foreach ($lignes as $ligne) {
    fputcsv($sortie, [
        $ligne['nom_raison_sociale'],
        $ligne['type_taxe'],
        $ligne['annee_exercice'],
        $ligne['base_calcul'],
        $ligne['montant_du'],
        $ligne['statut'],
        $ligne['date_emission'],
        $ligne['date_echeance'],
    ], ';');
}

fclose($sortie);

journaliser($_SESSION['utilisateur_id'], 'EXPORT_CSV', "Export CSV des taxations, exercice $anneeExercice, " . count($lignes) . " ligne(s)");
exit;