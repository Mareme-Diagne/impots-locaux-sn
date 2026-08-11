<?php
/**
 * config.example.php
 * -------------------
 * Ce fichier contient les réglages de connexion à la base de données.
 * Il ne contient AUCUNE information réellement secrète pour un usage
 * local XAMPP, mais on sépare quand même ce fichier de config.local.php
 * (qui, lui, n'est jamais envoyé sur GitHub — voir .gitignore) pour
 * garder la bonne habitude professionnelle : le code source ne doit
 * jamais contenir les vrais identifiants d'un environnement en production.
 *
 * Étape d'installation : copier ce fichier en "config.local.php" dans
 * le même dossier, puis ajuster les valeurs si besoin.
 */

return [
    'db_host'     => 'localhost',
    'db_name'     => 'impots_locaux_sn',
    'db_user'     => 'root',   // identifiant MySQL par défaut sur XAMPP
    'db_password' => '',       // mot de passe MySQL par défaut sur XAMPP (vide)
    'db_charset'  => 'utf8mb4',

    // Durée d'inactivité (en secondes) avant déconnexion automatique de session
    'session_expiration' => 1800, // 30 minutes
];
