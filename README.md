<p align="center">
  <img src="assets/img/logo.svg" alt="Logo ImpôtsLocaux-SN" width="480">
</p>

<p align="center">
  Plateforme web de gestion des taxes locales et parafiscales sénégalaises<br>
  Projet académique — Master CCA, École Supérieure Polytechnique de Dakar
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8%2B-777bb4?logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-PDO-4479a1?logo=mysql&logoColor=white">
  <img alt="Bootstrap" src="https://img.shields.io/badge/Bootstrap-5.3-7952b3?logo=bootstrap&logoColor=white">
  <img alt="Statut" src="https://img.shields.io/badge/Statut-Termin%C3%A9-2f8f5b">
</p>

---

## 1. Présentation

Une entreprise ou une personne possède souvent plusieurs biens (locaux, terrains, véhicules) et
doit payer chaque année plusieurs taxes locales différentes, chacune avec ses propres règles de
calcul. **ImpôtsLocaux-SN** centralise cette gestion : enregistrement du patrimoine, calcul
automatique des taxes selon les taux en vigueur, suivi des échéances et des paiements, et
production des documents officiels (états PDF, exports Excel).

Le projet répond au **Projet 15** du cahier des charges *"70 projets Web/XAMPP"* (Master CCA,
ESP Dakar, M. Ousmane LY) : *Plateforme web de gestion des taxes locales et parafiscales
sénégalaises (CFPB, CFPNB, patentes, TEOM)*.

## 2. Les 5 taxes gérées

| Taxe | Ce qu'elle concerne | Base de calcul | Taux (paramétrable en base) |
|---|---|---|---|
| **CFPB** | Bien bâti (maison, local) | Valeur locative, après abattements | 5 % (7,5 % industriel) |
| **CFPNB** | Terrain non bâti | Valeur vénale | 5 % |
| **Patente** | Activité commerciale | Droit fixe (tranche de CA) + droit proportionnel | Barème + 19 % |
| **TEOM** | Bien bâti (ordures ménagères) | Valeur locative mensuelle | 3,6 % |
| **Vignette** | Véhicule à moteur | Puissance fiscale (CV) | Barème par tranche |

> Tous les taux et seuils sont stockés dans la base de données (tables `bareme_taux`,
> `bareme_patente_droit_fixe`, `bareme_vignette`) et non codés en dur dans le PHP. Si un taux
> change avec la loi de finances, on modifie une ligne en base — aucun code à toucher.

## 3. Fonctionnalités

- **Authentification sécurisée** à 3 rôles (administrateur / agent / consultant), mots de passe
  hachés (`password_hash`), sessions avec expiration automatique, protection anti-brute-force
- **Gestion du patrimoine** : contribuables, biens bâtis/non bâtis, activités patentables,
  véhicules — CRUD complet avec recherche, filtres et pagination
- **Moteur de calcul fiscal** séparé de l'affichage, avec traçabilité du détail de chaque calcul
  (exonérations, abattements, taux appliqué)
- **Tableau de bord** avec graphiques (Chart.js) : répartition par taxe, par statut, évolution
  mensuelle
- **Exports** PDF (état officiel récapitulatif via FPDF) et CSV/Excel
- **Journal d'audit** horodaté de toutes les actions sensibles
- **Sécurité** : requêtes préparées PDO partout, échappement XSS systématique, en-têtes HTTP
  (CSP, X-Frame-Options), protection CSRF sur les suppressions, `.htaccess` sur les dossiers
  sensibles
- **Interface responsive** (sidebar rétractable, tableaux adaptés) utilisable sur mobile,
  tablette et desktop

## 4. Stack technique

- **Back-end** : PHP 8+ orienté objet léger (sans framework — volontairement, pour rester
  simple à lire et à expliquer), MySQL/MariaDB via PDO (requêtes préparées)
- **Front-end** : Bootstrap 5, Bootstrap Icons, Chart.js, police Fraunces + Inter
- **Export** : FPDF (PDF), CSV natif PHP
- **Environnement** : XAMPP (Apache + MySQL + PHP + phpMyAdmin)

## 5. Installation (XAMPP)

1. Copier le dossier du projet dans `htdocs/` de XAMPP.
2. Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.
3. Ouvrir phpMyAdmin (`http://localhost/phpmyadmin`) → onglet **Bases de données** → créer
   `impots_locaux_sn` avec l'interclassement `utf8mb4_unicode_ci`.
4. Sélectionner la base → onglet **Importer** → choisir `database/schema.sql` → vérifier que
   "Jeu de caractères du fichier" est bien `utf-8` → Exécuter.
5. Copier `config/config.example.php` en `config/config.local.php` (identifiants XAMPP par
   défaut : utilisateur `root`, mot de passe vide — déjà pré-remplis).
6. Ouvrir **une seule fois** `http://localhost/impots-locaux-sn/database/init_mots_de_passe.php`
   pour sécuriser les mots de passe des comptes de démonstration.
7. Télécharger [FPDF](http://www.fpdf.org/en/dl.php) et placer `fpdf.php` **et** le dossier
   `font/` dans `vendor/fpdf/` (nécessaire pour les exports PDF).
8. Ouvrir `http://localhost/impots-locaux-sn/public/` dans le navigateur.

## 6. Comptes de démonstration

| Rôle | Identifiant | Mot de passe | Accès |
|---|---|---|---|
| Administrateur | `admin` | `Admin@2026` | Tout, y compris le barème des taux |
| Agent | `agent` | `Agent@2026` | Saisie, calculs, paiements |
| Consultant | `consultant` | `Lecture@2026` | Lecture seule |

## 7. Organisation du dépôt
impots-locaux-sn/
├── assets/ CSS, JS (Chart.js), images (logo, favicon)
├── config/ connexion à la base de données
├── database/ schéma SQL, données de démonstration, script d'init des mots de passe
├── includes/ authentification, sécurité, fonctions communes, moteurs de calcul
├── public/ pages accessibles (point d'entrée du site)
├── vendor/fpdf/ librairie FPDF (génération des PDF)
└── exports/ fichiers générés (vide au départ)

## 8. Modules de calcul

Chaque taxe a son propre moteur de calcul, séparé de l'affichage, pour rester testable et
explicable indépendamment de l'interface :

- `includes/calculs_foncier.php` → CFPB / CFPNB
- `includes/calculs_patente.php` → Patente
- `includes/calculs_teom_vignette.php` → TEOM / Vignette

Chaque fonction retourne le détail du calcul étape par étape (`detail_calcul`), conservé dans la
table `taxations` pour la traçabilité.

## 9. Avancement

- [x] Base de données (9 tables + barèmes + données de démo)
- [x] Authentification, rôles, journal d'audit
- [x] Gestion du patrimoine (contribuables, biens)
- [x] Calcul CFPB / CFPNB
- [x] Calcul Patente
- [x] Calcul TEOM et vignette
- [x] Tableau de bord et graphiques
- [x] Exports PDF / CSV
- [x] Sécurisation, responsive, finitions

## 10. Auteurs

Projet réalisé par Mariama Diagne dans le cadre du Master CCA — École Supérieure Polytechnique de Dakar.