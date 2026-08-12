<?php
/**
 * securite.php
 * ------------
 * En-têtes HTTP de sécurité, envoyés sur toutes les pages protégées.
 * A inclure tout en haut d'auth.php (donc appliqué partout automatiquement).
 */

declare(strict_types=1);

function envoyerEntetesSecurite(): void
{
    // Empêche le navigateur de "deviner" un type de fichier différent de celui déclaré
    // (protection contre certaines attaques XSS basées sur la confusion de type MIME).
    header('X-Content-Type-Options: nosniff');

    // Interdit d'afficher le site dans une <iframe> sur un autre site (protection contre le clickjacking).
    header('X-Frame-Options: DENY');

    // Limite les informations envoyées au site d'origine quand on clique un lien externe.
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content-Security-Policy : n'autorise le chargement de scripts/styles que depuis notre
    // propre site et depuis les CDN utilisés (Bootstrap, Chart.js). Réduit fortement l'impact
    // d'une éventuelle faille XSS, en empêchant l'exécution de scripts injectés depuis ailleurs.
    header(
        "Content-Security-Policy: default-src 'self'; "
      . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
      . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
      . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
      . "img-src 'self' data:;"
    );
}