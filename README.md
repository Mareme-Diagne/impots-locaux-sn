# ImpôtsLocaux-SN

Plateforme web de gestion des taxes locales et parafiscales sénégalaises pour une entreprise ou un cabinet :
Contribution Foncière des Propriétés Bâties (CFPB), Contribution Foncière des Propriétés Non Bâties (CFPNB),
Contribution des Patentes, Taxe d'Enlèvement des Ordures Ménagères (TEOM) et vignette (taxe sur les véhicules).

Projet académique — Master CCA, École Supérieure Polytechnique de Dakar.

## 1. À quoi sert cette application ?

Une entreprise possède souvent plusieurs biens (locaux, terrains, véhicules) et doit payer chaque année
plusieurs taxes locales différentes, calculées chacune avec ses propres règles. Cette application permet de :

- enregistrer le patrimoine de l'entreprise (biens bâtis, terrains, véhicules, activités patentables) ;
- calculer automatiquement chaque taxe selon les taux en vigueur (taux modifiables sans toucher au code) ;
- suivre les échéances de paiement et générer les avis d'imposition ;
- garder un historique complet (qui a fait quoi, et quand).

## 2. Les 5 taxes gérées

| Taxe | Base de calcul | Taux (paramétrable) |
|---|---|---|
| CFPB | Valeur locative du bien bâti, après abattement pour charges (40%) et abattement résidence principale | 5% (7,5% pour usines/établissements industriels) |
| CFPNB | Valeur vénale du terrain non bâti | 5% |
| Patente | Droit fixe (selon activité) + droit proportionnel (sur valeur locative des locaux professionnels) | Barème par tranche de chiffre d'affaires |
| TEOM | Valeur locative mensuelle du bien | 3,6% |
| Vignette | Puissance fiscale / catégorie du véhicule | Barème par tranche |

> Les taux ci-dessus sont ceux du Code Général des Impôts sénégalais au moment de la conception du projet.
> Ils sont stockés dans la base de données (table `bareme_taux`) et non codés en dur, pour pouvoir être
> mis à jour si la loi de finances change un taux — sans modifier une seule ligne de code PHP.

## 3. Stack technique

- PHP 8+ (orienté objet léger, sans framework — pour rester simple à lire et à expliquer)
- MySQL / MariaDB (via PDO, requêtes préparées)
- Bootstrap 5 pour l'interface
- Chart.js pour le tableau de bord
- XAMPP (Apache + MySQL + PHP)

## 4. Installation (XAMPP)

1. Copier le dossier `impots-locaux-sn` dans `htdocs/` de XAMPP.
2. Démarrer Apache et MySQL depuis le panneau XAMPP.
3. Ouvrir phpMyAdmin (`http://localhost/phpmyadmin`), créer une base nommée `impots_locaux_sn`.
4. Importer le fichier `database/schema.sql` (structure + données de démonstration).
5. Copier `config/config.example.php` en `config/config.local.php` et vérifier les identifiants MySQL
   (par défaut sur XAMPP : utilisateur `root`, mot de passe vide).
6. Ouvrir **une seule fois** `http://localhost/impots-locaux-sn/database/init_mots_de_passe.php` dans le
   navigateur : ce script sécurise les mots de passe des comptes de démonstration (voir explication dans
   le fichier lui-même).
7. Ouvrir `http://localhost/impots-locaux-sn/public/` dans le navigateur.

## 5. Comptes de démonstration

| Rôle | Identifiant | Mot de passe |
|---|---|---|
| Administrateur | admin | Admin@2026 |
| Agent (utilisateur avancé) | agent | Agent@2026 |
| Consultant (lecture seule) | consultant | Lecture@2026 |

*(mots de passe stockés hachés en base avec `password_hash`, jamais en clair)*

## 6. Organisation du dépôt

```
impots-locaux-sn/
├── database/          schéma SQL + données de démonstration
├── config/            connexion à la base de données
├── includes/          fonctions communes, authentification, calculs des taxes
├── public/            pages accessibles (point d'entrée du site)
├── assets/            CSS / JS / images
└── exports/           fichiers PDF/CSV générés (vide au départ)
```

## 7. Avancement

- [x] Étape 1 — Initialisation du dépôt
- [x] Étape 2 — Base de données (schéma + données de démo)
- [x] Étape 3 — Connexion à la base
- [x] Étape 4 — Authentification, session sécurisée, rôles et journal d'audit
- [x] Étape 5 — Gestion du patrimoine (contribuables + biens)
- [ ] Étape 6 — Calcul CFPB / CFPNB
- [ ] Étape 7 — Calcul Patente
- [ ] Étape 8 — Calcul TEOM et vignette
- [ ] Étape 9 — Tableau de bord et graphiques
- [ ] Étape 10 — Exports PDF / CSV
- [ ] Étape 11 — Sécurisation, responsive, finitions
