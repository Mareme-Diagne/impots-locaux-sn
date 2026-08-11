<?php
/**
 * calculs_teom_vignette.php
 * ---------------------------
 * TEOM (Taxe d'Enlèvement des Ordures Ménagères) : 3,6% de la valeur locative
 * MENSUELLE d'un bien bâti (donc on divise la valeur annuelle par 12 d'abord).
 *
 * Vignette (taxe sur les véhicules à moteur) : un montant fixe selon une
 * tranche de puissance fiscale (voir table bareme_vignette).
 *
 * NOTE POUR LA SOUTENANCE : par souci de simplicité pédagogique, ce projet applique
 * le même barème de vignette à toutes les catégories de véhicules (tourisme, utilitaire,
 * poids lourd). Dans la réalité, chaque catégorie a son propre barème — la table
 * bareme_vignette pourrait être étendue avec une colonne "categorie" pour aller plus loin.
 */

declare(strict_types=1);

/**
 * Calcule la TEOM pour un bien bâti.
 */
function calculerTEOM(array $bien, array $baremes): array
{
    if ($bien['nature'] !== 'bati') {
        return ['applicable' => false, 'motif' => 'La TEOM ne concerne que les biens bâtis.'];
    }
    if ($bien['valeur_locative_annuelle'] === null) {
        return ['applicable' => false, 'motif' => 'Valeur locative annuelle manquante sur la fiche du bien.'];
    }

    $valeurLocativeMensuelle = (float) $bien['valeur_locative_annuelle'] / 12;
    $taux = $baremes['TEOM_TAUX'] ?? 0.036;
    $montant = round($valeurLocativeMensuelle * $taux, 0);

    $detail = sprintf(
        "Valeur locative annuelle : %s\nValeur locative mensuelle (÷12) : %s\nTaux TEOM : %s%%\nMontant TEOM : %s",
        formaterMontant((float) $bien['valeur_locative_annuelle']),
        formaterMontant($valeurLocativeMensuelle),
        $taux * 100,
        formaterMontant($montant)
    );

    return ['applicable' => true, 'base_calcul' => $valeurLocativeMensuelle, 'montant' => $montant, 'detail' => $detail];
}

/**
 * Retrouve le montant de vignette applicable pour une puissance fiscale donnée,
 * en cherchant la tranche correspondante (puissance_max = NULL = pas de plafond).
 */
function obtenirMontantVignette(PDO $pdo, int $puissanceFiscale, int $anneeExercice): ?float
{
    $requete = $pdo->prepare(
        'SELECT montant FROM bareme_vignette
         WHERE annee_exercice = :annee
           AND puissance_min <= :puissance
           AND (puissance_max IS NULL OR puissance_max >= :puissance2)
         ORDER BY puissance_min DESC
         LIMIT 1'
    );
    $requete->execute(['annee' => $anneeExercice, 'puissance' => $puissanceFiscale, 'puissance2' => $puissanceFiscale]);
    $resultat = $requete->fetchColumn();

    return $resultat !== false ? (float) $resultat : null;
}

/**
 * Calcule la vignette pour un véhicule.
 */
function calculerVignette(array $vehicule, PDO $pdo, int $anneeExercice): array
{
    $puissance = (int) $vehicule['puissance_fiscale'];
    $montant = obtenirMontantVignette($pdo, $puissance, $anneeExercice);

    if ($montant === null) {
        return [
            'applicable' => false,
            'motif' => "Aucune tranche de vignette trouvée pour {$puissance} CV (exercice $anneeExercice).",
        ];
    }

    $detail = sprintf(
        "Véhicule : %s (%s)\nPuissance fiscale : %d CV\nMontant de la vignette : %s",
        $vehicule['immatriculation'], $vehicule['categorie'], $puissance, formaterMontant($montant)
    );

    return ['applicable' => true, 'base_calcul' => $puissance, 'montant' => $montant, 'detail' => $detail];
}

function teomDejaEmise(PDO $pdo, int $bienId, int $anneeExercice): bool
{
    $requete = $pdo->prepare("SELECT COUNT(*) FROM taxations WHERE bien_id = :bien_id AND type_taxe = 'TEOM' AND annee_exercice = :annee");
    $requete->execute(['bien_id' => $bienId, 'annee' => $anneeExercice]);
    return ((int) $requete->fetchColumn()) > 0;
}

function vignetteDejaEmise(PDO $pdo, int $vehiculeId, int $anneeExercice): bool
{
    $requete = $pdo->prepare("SELECT COUNT(*) FROM taxations WHERE vehicule_id = :vehicule_id AND type_taxe = 'VIGNETTE' AND annee_exercice = :annee");
    $requete->execute(['vehicule_id' => $vehiculeId, 'annee' => $anneeExercice]);
    return ((int) $requete->fetchColumn()) > 0;
}