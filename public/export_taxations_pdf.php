<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
exigerConnexion();

require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

$pdo = obtenirConnexionBDD();
$anneeExercice = (int) ($_GET['annee'] ?? date('Y'));

$requete = $pdo->prepare(
    'SELECT c.nom_raison_sociale, t.type_taxe, t.montant_du, t.statut, t.date_echeance
     FROM taxations t
     JOIN contribuables c ON c.id = t.contribuable_id
     WHERE t.annee_exercice = :annee
     ORDER BY c.nom_raison_sociale, t.type_taxe'
);
$requete->execute(['annee' => $anneeExercice]);
$lignes = $requete->fetchAll();

$montantTotal = array_sum(array_column($lignes, 'montant_du'));

/**
 * FPDF ne comprend nativement que l'encodage ISO-8859-1 (Latin-1) pour ses polices de base,
 * pas l'UTF-8. Toutes nos données viennent de MySQL en UTF-8 (accents français) : il faut donc
 * les convertir avant de les écrire dans le PDF, sinon les accents s'affichent mal.
 */
function versLatin1(?string $texte): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string) $texte);
}

class PdfEtatTaxations extends FPDF
{
    public int $anneeExercice = 0;

    // En-tête répété automatiquement sur chaque page
    function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 8, versLatin1('ImpôtsLocaux-SN'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->Cell(0, 7, versLatin1("État récapitulatif des taxations locales — Exercice {$this->anneeExercice}"), 0, 1, 'C');
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 5, versLatin1('Document généré le ' . date('d/m/Y à H:i')), 0, 1, 'C');
        $this->Ln(4);
    }

    // Pied de page répété automatiquement, avec numéro de page
    function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PdfEtatTaxations();
$pdf->anneeExercice = $anneeExercice;
$pdf->AliasNbPages(); // pour que {nb} dans le Footer soit remplacé par le nombre total de pages
$pdf->AddPage();

// --- En-tête du tableau ---
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(19, 28, 51); // couleur marine de l'identité visuelle du site
$pdf->SetTextColor(255, 255, 255);
$largeurs = [60, 30, 35, 30, 35];
$titresColonnes = ['Contribuable', 'Taxe', 'Montant dû', 'Statut', 'Échéance'];
foreach ($titresColonnes as $i => $titre) {
    $pdf->Cell($largeurs[$i], 8, versLatin1($titre), 1, 0, 'C', true);
}
$pdf->Ln();

// --- Corps du tableau ---
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);
$libellesStatut = ['emise' => 'Émise', 'payee' => 'Payée', 'partiellement_payee' => 'Part. payée', 'en_retard' => 'En retard'];

$ligneAlternee = false;
foreach ($lignes as $ligne) {
    $pdf->SetFillColor($ligneAlternee ? 251 : 255, $ligneAlternee ? 251 : 255, $ligneAlternee ? 253 : 255);
    $pdf->Cell($largeurs[0], 7, versLatin1($ligne['nom_raison_sociale']), 1, 0, 'L', true);
    $pdf->Cell($largeurs[1], 7, versLatin1($ligne['type_taxe']), 1, 0, 'C', true);
    $pdf->Cell($largeurs[2], 7, versLatin1(formaterMontant((float) $ligne['montant_du'])), 1, 0, 'R', true);
    $pdf->Cell($largeurs[3], 7, versLatin1($libellesStatut[$ligne['statut']] ?? $ligne['statut']), 1, 0, 'C', true);
    $pdf->Cell($largeurs[4], 7, $ligne['date_echeance'], 1, 0, 'C', true);
    $pdf->Ln();
    $ligneAlternee = !$ligneAlternee;
}

if (empty($lignes)) {
    $pdf->Cell(array_sum($largeurs), 10, versLatin1('Aucune taxation émise pour cet exercice.'), 1, 0, 'C');
    $pdf->Ln();
}

// --- Ligne de total ---
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(246, 236, 217); // accent doré doux, cohérent avec l'identité visuelle
$pdf->Cell($largeurs[0] + $largeurs[1], 8, versLatin1('TOTAL'), 1, 0, 'R', true);
$pdf->Cell($largeurs[2], 8, versLatin1(formaterMontant($montantTotal)), 1, 0, 'R', true);
$pdf->Cell($largeurs[3] + $largeurs[4], 8, '', 1, 0, '', true);
$pdf->Ln();

journaliser($_SESSION['utilisateur_id'], 'EXPORT_PDF', "Export PDF de l'état des taxations, exercice $anneeExercice, " . count($lignes) . " ligne(s)");

// 'D' = force le téléchargement (Download) plutôt que d'afficher le PDF dans l'onglet
$pdf->Output('D', "etat_taxations_{$anneeExercice}.pdf");
exit;