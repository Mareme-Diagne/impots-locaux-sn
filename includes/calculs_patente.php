<?php
/**
 * calculs_patente.php
 * --------------------
 * Calcul de la Contribution des Patentes = droit fixe + droit proportionnel.
 *   - Le droit fixe dépend d'une tranche de chiffre d'affaires (table bareme_patente_droit_fixe)
 *   - Le droit proportionnel est un pourcentage de la valeur locative des locaux professionnels
 */

declare(strict_types=1);

/**
 * Retrouve le droit fixe applicable pour un chiffre d'affaires donné, en cherchant
 * la tranche dans laquelle il tombe (ca_max = NULL signifie "pas de plafond").
 */
function obtenirDroitFixePatente(PDO $pdo, float $chiffreAffaires, int $anneeExercice): ?float
{
    $requete = $pdo->prepare(
        'SELECT droit_fixe FROM bareme_patente_droit_fixe
         WHERE annee_exercice = :annee
           AND ca_min <= :ca
           AND (ca_max IS NULL OR ca_max > :ca2)
         ORDER BY ca_min DESC
         LIMIT 1'
    );
    $requete->execute(['annee' => $anneeExercice, 'ca' => $chiffreAffaires, 'ca2' => $chiffreAffaires]);
    $resultat = $requete->fetchColumn();

    return $resultat !== false ? (float) $resultat : null;
}

/**
 * Calcule le montant de la Patente pour une activité patentable.
 * Retourne le même format que calculerCFPB()/calculerCFPNB() pour rester cohérent
 * dans les pages qui affichent ces résultats.
 */
function calculerPatente(array $activite, array $baremes, PDO $pdo, int $anneeExercice): array
{
    $chiffreAffaires = (float) $activite['chiffre_affaires_annuel'];
    $valeurLocative   = (float) $activite['valeur_locative_locaux'];

    $droitFixe = obtenirDroitFixePatente($pdo, $chiffreAffaires, $anneeExercice);
    if ($droitFixe === null) {
        return [
            'applicable' => false,
            'motif' => "Aucune tranche de droit fixe trouvée pour un CA de " . formaterMontant($chiffreAffaires)
                     . " (exercice $anneeExercice). Vérifiez la table bareme_patente_droit_fixe.",
        ];
    }

    $tauxProportionnel = $baremes['PATENTE_TAUX_DROIT_PROPORTIONNEL'] ?? 0.19;
    $droitProportionnel = round($valeurLocative * $tauxProportionnel, 0);
    $montant = $droitFixe + $droitProportionnel;

    $detail = sprintf(
        "Chiffre d'affaires annuel : %s\nDroit fixe (selon tranche de CA) : %s\n"
      . "Valeur locative des locaux : %s\nTaux du droit proportionnel : %s%%\n"
      . "Droit proportionnel : %s\nMontant total de la Patente : %s",
        formaterMontant($chiffreAffaires),
        formaterMontant($droitFixe),
        formaterMontant($valeurLocative),
        $tauxProportionnel * 100,
        formaterMontant($droitProportionnel),
        formaterMontant($montant)
    );

    return [
        'applicable' => true,
        'base_calcul' => $chiffreAffaires,
        'montant' => $montant,
        'detail' => $detail,
    ];
}

/**
 * Vérifie si une Patente a déjà été émise pour cette activité, cette année
 * (équivalent de taxationDejaEmise() dans calculs_foncier.php, mais sur activite_id).
 */
function patenteDejaEmise(PDO $pdo, int $activiteId, int $anneeExercice): bool
{
    $requete = $pdo->prepare(
        "SELECT COUNT(*) FROM taxations WHERE activite_id = :activite_id AND type_taxe = 'PATENTE' AND annee_exercice = :annee"
    );
    $requete->execute(['activite_id' => $activiteId, 'annee' => $anneeExercice]);
    return ((int) $requete->fetchColumn()) > 0;
}