<?php
/**
 * init_mots_de_passe.php
 * -----------------------
 * A LANCER UNE SEULE FOIS, juste après avoir importé database/schema.sql.
 *
 * Pourquoi ce script existe :
 * Un script SQL ne peut pas contenir de "vrai" mot de passe haché, car
 * password_hash() génère un sel aléatoire différent à chaque exécution.
 * schema.sql insère donc des mots de passe temporaires en clair, préfixés
 * par "A_HACHER:". Ce script les remplace par de vrais hachages sécurisés,
 * puis peut être supprimé.
 *
 * Utilisation : ouvrir http://localhost/impots-locaux-sn/database/init_mots_de_passe.php
 * dans le navigateur, une seule fois.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = obtenirConnexionBDD();

// On ne va chercher que les comptes pas encore hachés (sécurité : le script
// ne fait rien si on le relance par erreur une deuxième fois).
$comptesAHacher = $pdo->query(
    "SELECT id, mot_de_passe_hash FROM utilisateurs WHERE mot_de_passe_hash LIKE 'A_HACHER:%'"
)->fetchAll();

if (empty($comptesAHacher)) {
    echo "Rien à faire : tous les mots de passe sont déjà sécurisés.";
    exit;
}

$requetePreparee = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE id = :id');

foreach ($comptesAHacher as $compte) {
    $motDePasseClair = substr($compte['mot_de_passe_hash'], strlen('A_HACHER:'));
    $hashSecurise    = password_hash($motDePasseClair, PASSWORD_DEFAULT);

    $requetePreparee->execute([
        'hash' => $hashSecurise,
        'id'   => $compte['id'],
    ]);

    echo "Compte #{$compte['id']} sécurisé.<br>";
}

echo "<br><strong>Terminé.</strong> Vous pouvez maintenant vous connecter avec les identifiants du README.";
