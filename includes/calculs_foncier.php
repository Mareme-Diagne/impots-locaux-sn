<?php
/**
 * calculs_foncier.php
 * --------------------
 * Toute la logique de calcul de la CFPB et de la CFPNB, séparée des pages
 * qui l'affichent. Avantage : ces fonctions peuvent être testées et
 * expliquées indépendamment de l'interface — utile pour la soutenance.
 *
 * Principe général : on ne code JAMAIS un taux ou un abattement en dur ici.
 * Toutes les valeurs viennent de la table bareme_taux (voir database/schema.sql),
 * passées en paramètre sous forme de tableau associatif ['CODE' => valeur].
 */

declare(strict_types=1);

/**
 * Charge tous les taux/seuils applicables pour une année d'exercice donnée,
 * sous forme de tableau ['CFPB_TAUX_STANDARD' => 0.05, ...].
 */
function obtenirBaremes(PDO $pdo, int $anneeExercice): array
{
    $requete = $pdo->prepare('SELECT code, valeur FROM bareme_taux WHERE annee_exercice = :annee');
    $requete->execute(['annee' => $anneeExercice]);

    $baremes = [];
    foreach ($requete->fetchAll() as $ligne) {
        $baremes[$ligne['code']] = (float) $ligne['valeur'];
    }
    return $baremes;
}

/**
 * Calcule la Contribution Foncière des Propriétés Bâties (CFPB) pour un bien.
 *
 * Étapes du calcul (dans cet ordre) :
 *   1. Le bien doit être "bâti" et non exonéré (5 ans après achèvement)
 *   2. Valeur locative annuelle - abattement pour charges (40%)
 *   3. Si résidence principale : on retire en plus un abattement fixe
 *   4. Le résultat est multiplié par le taux (standard 5%, industriel 7,5%)
 *
 * Retourne toujours le même format, que le bien soit imposable ou non,
 * pour que la page d'appel n'ait pas à connaître les règles métier.
 */
function calculerCFPB(array $bien, array $baremes, int $anneeExercice): array
{
    if ($bien['nature'] !== 'bati') {
        return ['applicable' => false, 'motif' => 'Ce bien n\'est pas bâti — non concerné par la CFPB.'];
    }
    if ($bien['valeur_locative_annuelle'] === null) {
        return ['applicable' => false, 'motif' => 'Valeur locative annuelle manquante sur la fiche du bien.'];
    }

    // --- 1) Exonération temporaire après achèvement de la construction ---
    $anneesExoneration = (int) ($baremes['CFPB_EXONERATION_ANNEES'] ?? 5);
    if ($bien['annee_achevement'] !== null) {
        $anciennete = $anneeExercice - (int) $bien['annee_achevement'];
        if ($anciennete >= 0 && $anciennete < $anneesExoneration) {
            return [
                'applicable' => true,
                'base_calcul' => (float) $bien['valeur_locative_annuelle'],
                'montant' => 0.0,
                'detail' => "Exonéré : construction achevée en {$bien['annee_achevement']}, "
                          . "exonération de {$anneesExoneration} ans (encore " . ($anneesExoneration - $anciennete) . " an(s)).",
            ];
        }
    }

    // --- 2) Taux selon l'usage du bien ---
    $codeTaux = $bien['usage_bien'] === 'industriel' ? 'CFPB_TAUX_INDUSTRIEL' : 'CFPB_TAUX_STANDARD';
    $taux = $baremes[$codeTaux] ?? 0.05;

    // --- 3) Abattement pour charges et entretien (40% par défaut) ---
    $valeurLocativeBrute = (float) $bien['valeur_locative_annuelle'];
    $tauxAbattementCharges = $baremes['CFPB_ABATTEMENT_CHARGES'] ?? 0.40;
    $valeurApresChargesEtEntretien = $valeurLocativeBrute * (1 - $tauxAbattementCharges);

    // --- 4) Abattement résidence principale (montant fixe, pas un pourcentage) ---
    $abattementResidence = 0.0;
    if ($bien['usage_bien'] === 'residence_principale') {
        $abattementResidence = $baremes['CFPB_ABATTEMENT_RESIDENCE_PRINCIPALE'] ?? 0.0;
    }

    $baseImposable = max(0.0, $valeurApresChargesEtEntretien - $abattementResidence);
    $montant = round($baseImposable * $taux, 0);

    $detail = sprintf(
        "Valeur locative brute : %s\nAbattement charges (%s%%) : -%s\n%sBase imposable : %s\nTaux appliqué (%s) : %s%%\nMontant CFPB : %s",
        formaterMontant($valeurLocativeBrute),
        $tauxAbattementCharges * 100,
        formaterMontant($valeurLocativeBrute * $tauxAbattementCharges),
        $abattementResidence > 0 ? "Abattement résidence principale : -" . formaterMontant($abattementResidence) . "\n" : '',
        formaterMontant($baseImposable),
        $bien['usage_bien'] === 'industriel' ? 'industriel' : 'standard',
        $taux * 100,
        formaterMontant($montant)
    );

    return ['applicable' => true, 'base_calcul' => $baseImposable, 'montant' => $montant, 'detail' => $detail];
}

/**
 * Calcule la Contribution Foncière des Propriétés Non Bâties (CFPNB).
 * Plus simple que la CFPB : pas d'abattement, un taux unique sur la valeur vénale.
 */
function calculerCFPNB(array $bien, array $baremes): array
{
    if ($bien['nature'] !== 'non_bati') {
        return ['applicable' => false, 'motif' => 'Ce bien n\'est pas un terrain non bâti — non concerné par la CFPNB.'];
    }
    if ($bien['valeur_venale'] === null) {
        return ['applicable' => false, 'motif' => 'Valeur vénale manquante sur la fiche du bien.'];
    }

    $taux = $baremes['CFPNB_TAUX'] ?? 0.05;
    $base = (float) $bien['valeur_venale'];
    $montant = round($base * $taux, 0);

    $detail = sprintf(
        "Valeur vénale : %s\nTaux appliqué : %s%%\nMontant CFPNB : %s",
        formaterMontant($base), $taux * 100, formaterMontant($montant)
    );

    return ['applicable' => true, 'base_calcul' => $base, 'montant' => $montant, 'detail' => $detail];
}

/**
 * Vérifie si une taxation existe déjà pour ce bien / ce type / cette année,
 * pour éviter de générer deux fois la même taxe si on relance le calcul.
 */
function taxationDejaEmise(PDO $pdo, int $bienId, string $typeTaxe, int $anneeExercice): bool
{
    $requete = $pdo->prepare(
        'SELECT COUNT(*) FROM taxations WHERE bien_id = :bien_id AND type_taxe = :type AND annee_exercice = :annee'
    );
    $requete->execute(['bien_id' => $bienId, 'type' => $typeTaxe, 'annee' => $anneeExercice]);
    return ((int) $requete->fetchColumn()) > 0;
}