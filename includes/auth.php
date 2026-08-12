<?php
/**
 * auth.php
 * --------
 * Tout ce qui concerne "qui est connecté et a-t-il le droit de voir
 * cette page ?". A inclure en tout début de chaque page protégée.
 *
 * Logique en 3 fonctions simples :
 *   - demarrerSession()      : ouvre une session sécurisée
 *   - tenterConnexion(...)   : vérifie identifiant + mot de passe
 *   - exigerConnexion(...)   : bloque l'accès si non connecté / mauvais rôle
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/securite.php';

function demarrerSession(): void
{
    envoyerEntetesSecurite();
    if (session_status() === PHP_SESSION_NONE) {
        // Options de sécurité de session : le cookie de session n'est
        // accessible ni en JavaScript (httponly) ni en HTTP non chiffré
        // une fois en production (secure), ce qui limite le vol de session.
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // Déconnexion automatique après une période d'inactivité (exigence du cahier des charges).
    $config = require __DIR__ . '/../config/config.local.php';
    $expiration = $config['session_expiration'] ?? 1800;

    if (isset($_SESSION['derniere_activite']) && (time() - $_SESSION['derniere_activite'] > $expiration)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['derniere_activite'] = time();
}

/**
 * Vérifie l'identifiant et le mot de passe fournis.
 * Retourne les données de l'utilisateur si c'est correct, ou null sinon.
 * Ne révèle jamais si c'est l'identifiant ou le mot de passe qui est faux
 * (bonne pratique : ne pas aider un attaquant à deviner les comptes existants).
 */
function tenterConnexion(string $identifiant, string $motDePasseSaisi): ?array
{
    $pdo = obtenirConnexionBDD();

    $requete = $pdo->prepare(
        'SELECT * FROM utilisateurs WHERE identifiant = :identifiant AND actif = 1 LIMIT 1'
    );
    $requete->execute(['identifiant' => $identifiant]);
    $utilisateur = $requete->fetch();

    if (!$utilisateur) {
        return null;
    }

    // password_verify() compare le mot de passe saisi au hachage stocké,
    // sans jamais avoir besoin de connaître le mot de passe en clair.
    if (!password_verify($motDePasseSaisi, $utilisateur['mot_de_passe_hash'])) {
        return null;
    }

    return $utilisateur;
}

/**
 * A appeler après une connexion réussie : mémorise l'utilisateur en session.
 */
function ouvrirSessionUtilisateur(array $utilisateur): void
{
    // On régénère l'identifiant de session à chaque connexion (protection
    // contre la "fixation de session", une technique d'attaque classique).
    session_regenerate_id(true);

    $_SESSION['utilisateur_id'] = $utilisateur['id'];
    $_SESSION['utilisateur_nom'] = $utilisateur['nom_complet'];
    $_SESSION['utilisateur_role'] = $utilisateur['role'];

    journaliser((int) $utilisateur['id'], 'CONNEXION', 'Connexion réussie de ' . $utilisateur['identifiant']);
}

/**
 * A placer en haut de chaque page qui nécessite d'être connecté.
 * $rolesAutorises : liste des rôles admis sur cette page (vide = tous les rôles connectés).
 * Exemple : exigerConnexion(['administrateur']) pour une page réservée à l'admin.
 */
function exigerConnexion(array $rolesAutorises = []): void
{
    demarrerSession();

    if (empty($_SESSION['utilisateur_id'])) {
        rediriger('/impots-locaux-sn/public/connexion.php');
    }

    if (!empty($rolesAutorises) && !in_array($_SESSION['utilisateur_role'], $rolesAutorises, true)) {
        rediriger('/impots-locaux-sn/public/erreur_403.php');
    }
}

function deconnecter(): void
{
    demarrerSession();
    if (!empty($_SESSION['utilisateur_id'])) {
        journaliser((int) $_SESSION['utilisateur_id'], 'DECONNEXION');
    }
    session_unset();
    session_destroy();
    rediriger('/impots-locaux-sn/public/connexion.php');
}
