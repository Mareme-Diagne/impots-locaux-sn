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

/**
 * Génère (ou réutilise) un jeton anti-CSRF unique pour la session en cours.
 * A appeler dans un formulaire sensible (suppression, etc.) pour générer un champ caché.
 */
function jetonCsrf(): string
{
    if (empty($_SESSION['jeton_csrf'])) {
        $_SESSION['jeton_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['jeton_csrf'];
}

/**
 * Vérifie que le jeton soumis correspond à celui de la session — sinon, la requête
 * ne vient probablement pas d'un vrai formulaire de ce site.
 */
function verifierJetonCsrf(?string $jetonSoumis): bool
{
    return !empty($_SESSION['jeton_csrf']) && !empty($jetonSoumis)
        && hash_equals($_SESSION['jeton_csrf'], $jetonSoumis);
}