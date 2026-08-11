<?php
/**
 * fonctions.php
 * -------------
 * Petites fonctions utilitaires utilisées sur plusieurs pages, pour
 * éviter de répéter le même code partout (principe "DRY").
 */

declare(strict_types=1);

/**
 * Affiche un montant en francs CFA, lisible pour un humain.
 * Exemple : formaterMontant(1500000) => "1 500 000 FCFA"
 */
function formaterMontant(float $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Echappe une chaîne avant affichage dans une page HTML, pour se
 * protéger des attaques XSS (exigence du cahier des charges).
 * Raccourci pratique à utiliser partout où on affiche une donnée
 * qui vient de l'utilisateur ou de la base de données.
 */
function e(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige immédiatement vers une autre page du site et arrête le script.
 */
function rediriger(string $urlRelative): never
{
    header('Location: ' . $urlRelative);
    exit;
}

/**
 * Enregistre une action dans le journal d'audit (traçabilité obligatoire).
 */
function journaliser(?int $utilisateurId, string $action, string $details = ''): void
{
    $pdo = obtenirConnexionBDD();
    $requete = $pdo->prepare(
        'INSERT INTO journal_audit (utilisateur_id, action, details, adresse_ip) VALUES (:uid, :action, :details, :ip)'
    );
    $requete->execute([
        'uid'     => $utilisateurId,
        'action'  => $action,
        'details' => $details,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'inconnue',
    ]);
}
