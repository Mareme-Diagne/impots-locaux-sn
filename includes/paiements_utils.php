<?php
/**
 * paiements_utils.php
 * --------------------
 * Recalcule le statut d'une taxation (émise / partiellement payée / payée /
 * en retard) à partir de la somme de ses paiements et de sa date d'échéance.
 */

declare(strict_types=1);

function actualiserStatutTaxation(PDO $pdo, int $taxationId): void
{
    $requeteTaxation = $pdo->prepare('SELECT montant_du, date_echeance, statut FROM taxations WHERE id = :id');
    $requeteTaxation->execute(['id' => $taxationId]);
    $taxation = $requeteTaxation->fetch();
    if (!$taxation) {
        return;
    }

    $requeteSomme = $pdo->prepare('SELECT COALESCE(SUM(montant_paye), 0) FROM paiements WHERE taxation_id = :id');
    $requeteSomme->execute(['id' => $taxationId]);
    $totalPaye = (float) $requeteSomme->fetchColumn();

    $montantDu = (float) $taxation['montant_du'];
    $enRetard  = strtotime($taxation['date_echeance']) < strtotime(date('Y-m-d'));

    if ($montantDu > 0 && $totalPaye >= $montantDu) {
        $nouveauStatut = 'payee';
    } elseif ($totalPaye > 0) {
        $nouveauStatut = $enRetard ? 'en_retard' : 'partiellement_payee';
    } else {
        $nouveauStatut = $enRetard ? 'en_retard' : 'emise';
    }

    if ($nouveauStatut !== $taxation['statut']) {
        $pdo->prepare('UPDATE taxations SET statut = :statut WHERE id = :id')
            ->execute(['statut' => $nouveauStatut, 'id' => $taxationId]);
    }
}