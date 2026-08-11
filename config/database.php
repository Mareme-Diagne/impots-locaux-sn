<?php
/**
 * database.php
 * ------------
 * Fournit UNE SEULE fonction : obtenirConnexionBDD().
 * Toutes les autres pages du site appellent cette fonction pour parler
 * à la base de données. On centralise la connexion ici pour :
 *   - ne jamais écrire les identifiants MySQL à plusieurs endroits ;
 *   - utiliser PDO avec des requêtes préparées partout (protection
 *     contre les injections SQL, exigée par le cahier des charges).
 */

declare(strict_types=1);

function obtenirConnexionBDD(): PDO
{
    // On garde une seule connexion ouverte par requête HTTP (pas une par appel).
    static $connexion = null;

    if ($connexion !== null) {
        return $connexion;
    }

    $cheminConfig = __DIR__ . '/config.local.php';
    if (!file_exists($cheminConfig)) {
        // Message clair pour un utilisateur non technique qui vient d'installer le projet.
        die('Erreur d\'installation : le fichier config/config.local.php est introuvable. '
          . 'Copiez config/config.example.php vers config/config.local.php puis réessayez.');
    }

    $config = require $cheminConfig;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_name'],
        $config['db_charset']
    );

    try {
        $connexion = new PDO($dsn, $config['db_user'], $config['db_password'], [
            // Les erreurs SQL deviennent de vraies exceptions PHP (plus facile à déboguer)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Les résultats sont retournés sous forme de tableaux associatifs ['colonne' => valeur]
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Les requêtes préparées sont réellement exécutées côté MySQL (pas simulées côté PHP)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $exception) {
        // On n'affiche jamais le message technique complet à l'écran (pourrait révéler des infos
        // sensibles) : on le journalise et on montre un message générique.
        error_log('Erreur de connexion BDD : ' . $exception->getMessage());
        die('Impossible de se connecter à la base de données. Vérifiez que MySQL est démarré dans XAMPP.');
    }

    return $connexion;
}
