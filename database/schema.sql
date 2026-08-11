-- =====================================================================
-- ImpôtsLocaux-SN — Script de création de la base de données
-- =====================================================================
-- Comment lire ce fichier : chaque table correspond à une chose concrète
-- de la vraie vie (une personne, un bien, une taxe...). Les commentaires
-- au-dessus de chaque table expliquent à quoi elle sert, en français
-- simple, pour pouvoir être présentée sans jargon informatique.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS impots_locaux_sn
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE impots_locaux_sn;

-- ---------------------------------------------------------------------
-- 1) UTILISATEURS
-- Ce sont les personnes qui se connectent à l'application (pas les
-- contribuables, qui eux sont dans une autre table). On distingue
-- 3 rôles imposés par le cahier des charges :
--   - administrateur  : peut tout faire, y compris gérer les taux
--   - agent           : peut saisir les biens et calculer les taxes
--   - consultant      : peut seulement consulter (lecture seule)
-- ---------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom_complet        VARCHAR(120) NOT NULL,
    identifiant        VARCHAR(50)  NOT NULL UNIQUE,
    email              VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe_hash  VARCHAR(255) NOT NULL, -- généré par password_hash() en PHP, jamais en clair
    role               ENUM('administrateur','agent','consultant') NOT NULL DEFAULT 'agent',
    actif              TINYINT(1) NOT NULL DEFAULT 1,
    cree_le            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) CONTRIBUABLES
-- La personne ou l'entreprise qui doit payer les taxes.
-- ---------------------------------------------------------------------
CREATE TABLE contribuables (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    type           ENUM('personne_physique','entreprise') NOT NULL,
    nom_raison_sociale VARCHAR(150) NOT NULL,
    ninea          VARCHAR(30)  NULL,      -- numéro d'identification fiscale (entreprises)
    telephone      VARCHAR(30)  NULL,
    email          VARCHAR(150) NULL,
    adresse        VARCHAR(255) NULL,
    commune        VARCHAR(100) NULL,      -- utile car certaines taxes varient selon la commune
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) BAREME_TAUX
-- Tous les taux et seuils utilisés dans les calculs sont ICI, pas
-- codés en dur dans le PHP. Si un taux change avec la loi de finances,
-- on modifie une ligne dans cette table, aucun code à toucher.
-- ---------------------------------------------------------------------
CREATE TABLE bareme_taux (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(50) NOT NULL UNIQUE,  -- ex: 'CFPB_TAUX_STANDARD'
    libelle       VARCHAR(150) NOT NULL,
    valeur        DECIMAL(12,4) NOT NULL,       -- taux (ex: 0.05) ou montant (ex: 1500000)
    unite         VARCHAR(20) NOT NULL,         -- 'pourcentage' ou 'fcfa'
    annee_exercice YEAR NOT NULL,
    modifie_le    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) BIENS_IMMOBILIERS
-- Un terrain ou une construction appartenant à un contribuable.
-- Sert de base pour calculer la CFPB (bâti), la CFPNB (non bâti)
-- et la TEOM (ordures ménagères).
-- ---------------------------------------------------------------------
CREATE TABLE biens_immobiliers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    contribuable_id     INT NOT NULL,
    designation         VARCHAR(150) NOT NULL,      -- ex: "Villa Sacré-Coeur 3"
    nature               ENUM('bati','non_bati') NOT NULL,
    usage_bien           ENUM('residence_principale','locatif','commercial','industriel','terrain_nu') NOT NULL,
    commune              VARCHAR(100) NOT NULL,
    valeur_locative_annuelle DECIMAL(14,2) NULL,    -- utilisée pour les biens bâtis (CFPB / TEOM)
    valeur_venale        DECIMAL(14,2) NULL,        -- utilisée pour les terrains non bâtis (CFPNB)
    annee_achevement     YEAR NULL,                  -- une construction est exonérée les 5 ans suivant son achèvement
    cree_le              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuable_id) REFERENCES contribuables(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) ACTIVITES_PATENTABLES
-- Une activité commerciale ou industrielle exercée par un contribuable,
-- soumise à la Contribution des Patentes.
-- ---------------------------------------------------------------------
CREATE TABLE activites_patentables (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    contribuable_id   INT NOT NULL,
    libelle_activite  VARCHAR(150) NOT NULL,
    chiffre_affaires_annuel DECIMAL(14,2) NOT NULL,
    valeur_locative_locaux  DECIMAL(14,2) NOT NULL DEFAULT 0,  -- pour le droit proportionnel
    cree_le           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuable_id) REFERENCES contribuables(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6) VEHICULES
-- Sert au calcul de la vignette (taxe spéciale sur les véhicules à moteur).
-- ---------------------------------------------------------------------
CREATE TABLE vehicules (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    contribuable_id   INT NOT NULL,
    immatriculation   VARCHAR(20) NOT NULL,
    puissance_fiscale INT NOT NULL,       -- en chevaux fiscaux (CV)
    categorie         ENUM('tourisme','utilitaire','poids_lourd') NOT NULL,
    cree_le           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuable_id) REFERENCES contribuables(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7) TAXATIONS
-- Le coeur de l'application : une ligne = "ce contribuable doit tant
-- de FCFA pour telle taxe, telle année". Générée par les calculs
-- automatiques, mais consultable et exportable.
-- ---------------------------------------------------------------------
CREATE TABLE taxations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    contribuable_id   INT NOT NULL,
    type_taxe         ENUM('CFPB','CFPNB','PATENTE','TEOM','VIGNETTE') NOT NULL,
    bien_id           INT NULL,   -- rempli si la taxe concerne un bien précis (CFPB/CFPNB/TEOM)
    activite_id       INT NULL,   -- rempli pour la patente
    vehicule_id       INT NULL,   -- rempli pour la vignette
    annee_exercice    YEAR NOT NULL,
    base_calcul       DECIMAL(14,2) NOT NULL,   -- la base retenue (valeur locative, CA, etc.)
    montant_du        DECIMAL(14,2) NOT NULL,   -- montant final de la taxe
    detail_calcul     TEXT NULL,                 -- explication du calcul, en clair, pour la traçabilité
    statut            ENUM('emise','payee','partiellement_payee','en_retard') NOT NULL DEFAULT 'emise',
    date_emission     DATE NOT NULL,
    date_echeance     DATE NOT NULL,
    cree_le           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contribuable_id) REFERENCES contribuables(id) ON DELETE CASCADE,
    FOREIGN KEY (bien_id)      REFERENCES biens_immobiliers(id) ON DELETE SET NULL,
    FOREIGN KEY (activite_id)  REFERENCES activites_patentables(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicule_id)  REFERENCES vehicules(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8) PAIEMENTS
-- Les règlements effectués par les contribuables sur une taxation.
-- ---------------------------------------------------------------------
CREATE TABLE paiements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    taxation_id    INT NOT NULL,
    montant_paye   DECIMAL(14,2) NOT NULL,
    mode_paiement  ENUM('especes','virement','mobile_money','cheque') NOT NULL,
    reference      VARCHAR(80) NULL,
    date_paiement  DATE NOT NULL,
    saisi_par      INT NULL,   -- utilisateur qui a enregistré le paiement
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (taxation_id) REFERENCES taxations(id) ON DELETE CASCADE,
    FOREIGN KEY (saisi_par)   REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9) JOURNAL_AUDIT
-- Trace horodatée des actions sensibles (obligatoire dans le cahier
-- des charges) : qui a fait quoi, quand, sur quel élément.
-- ---------------------------------------------------------------------
CREATE TABLE journal_audit (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    action         VARCHAR(100) NOT NULL,   -- ex: 'CONNEXION', 'CREATION_BIEN', 'CALCUL_TAXE'
    details        TEXT NULL,
    adresse_ip     VARCHAR(45) NULL,
    horodatage     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- DONNEES DE DEMONSTRATION
-- =====================================================================

-- Utilisateurs de test (mots de passe : voir README).
-- IMPORTANT : un hachage password_hash() dépend d'un "sel" aléatoire généré au moment de l'exécution :
-- il est donc impossible (et dangereux) d'écrire un vrai hachage à l'avance dans un script SQL statique.
-- On insère ici un mot de passe temporaire lisible, et le script database/init_mots_de_passe.php
-- (fourni à l'étape "authentification") le remplace par un VRAI hachage sécurisé au premier lancement.
INSERT INTO utilisateurs (nom_complet, identifiant, email, mot_de_passe_hash, role) VALUES
('Administrateur Principal', 'admin',      'admin@impotslocaux.sn',      'A_HACHER:Admin@2026',      'administrateur'),
('Agent de Saisie',          'agent',      'agent@impotslocaux.sn',      'A_HACHER:Agent@2026',      'agent'),
('Consultant Lecture Seule', 'consultant', 'consultant@impotslocaux.sn', 'A_HACHER:Lecture@2026',    'consultant');

-- Barème des taux (année d'exercice 2026), sources : Code Général des Impôts sénégalais
INSERT INTO bareme_taux (code, libelle, valeur, unite, annee_exercice) VALUES
('CFPB_TAUX_STANDARD',       'Taux CFPB — bâti résidentiel/commercial', 0.05, 'pourcentage', 2026),
('CFPB_TAUX_INDUSTRIEL',     'Taux CFPB — usines et établissements industriels', 0.075, 'pourcentage', 2026),
('CFPB_ABATTEMENT_CHARGES',  'Abattement pour charges et entretien', 0.40, 'pourcentage', 2026),
('CFPB_ABATTEMENT_RESIDENCE_PRINCIPALE', 'Abattement résidence principale', 1500000, 'fcfa', 2026),
('CFPB_EXONERATION_ANNEES',  'Nombre d\'années d\'exonération après achèvement', 5, 'annees', 2026),
('CFPNB_TAUX',                'Taux CFPNB — terrains non bâtis', 0.05, 'pourcentage', 2026),
('TEOM_TAUX',                 'Taux TEOM sur valeur locative mensuelle', 0.036, 'pourcentage', 2026),
('PATENTE_TAUX_DROIT_PROPORTIONNEL', 'Droit proportionnel patente sur valeur locative', 0.19, 'pourcentage', 2026);

-- Barème du droit fixe de la patente par tranche de chiffre d'affaires (table séparée pour rester lisible)
CREATE TABLE bareme_patente_droit_fixe (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ca_min        DECIMAL(14,2) NOT NULL,
    ca_max        DECIMAL(14,2) NULL,      -- NULL = pas de plafond (tranche la plus haute)
    droit_fixe    DECIMAL(12,2) NOT NULL,
    annee_exercice YEAR NOT NULL
) ENGINE=InnoDB;

INSERT INTO bareme_patente_droit_fixe (ca_min, ca_max, droit_fixe, annee_exercice) VALUES
(0,           25000000,   30000,  2026),
(25000000,    50000000,   60000,  2026),
(50000000,    100000000,  120000, 2026),
(100000000,   500000000,  300000, 2026),
(500000000,   NULL,       600000, 2026);

-- Barème de la vignette par puissance fiscale (véhicules de tourisme)
CREATE TABLE bareme_vignette (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    puissance_min  INT NOT NULL,
    puissance_max  INT NULL,
    montant        DECIMAL(12,2) NOT NULL,
    annee_exercice YEAR NOT NULL
) ENGINE=InnoDB;

INSERT INTO bareme_vignette (puissance_min, puissance_max, montant, annee_exercice) VALUES
(1,  4,  15000,  2026),
(5,  7,  25000,  2026),
(8,  10, 40000,  2026),
(11, 14, 60000,  2026),
(15, NULL, 100000, 2026);

-- Contribuables de démonstration
INSERT INTO contribuables (type, nom_raison_sociale, ninea, telephone, email, adresse, commune) VALUES
('entreprise',        'SARL Baobab Distribution', '004512587',  '+221 77 123 45 67', 'contact@baobab-dist.sn', 'Route de Rufisque, Km 8', 'Dakar'),
('personne_physique',  'Fatou Ndiaye',              NULL,        '+221 76 555 12 34', 'fatou.ndiaye@example.com', 'Cité Keur Gorgui, Villa 42', 'Dakar'),
('entreprise',        'Ets Thiam Frères',          '007845123',  '+221 78 999 88 77', 'ets.thiam@example.com', 'Marché Sandaga, Lot 15', 'Dakar');

-- Biens immobiliers de démonstration
INSERT INTO biens_immobiliers (contribuable_id, designation, nature, usage_bien, commune, valeur_locative_annuelle, valeur_venale, annee_achevement) VALUES
(1, 'Entrepôt Baobab Rufisque', 'bati', 'industriel', 'Rufisque', 8000000, NULL, 2015),
(2, 'Villa Keur Gorgui',         'bati', 'residence_principale', 'Dakar', 3600000, NULL, 2012),
(3, 'Local commercial Sandaga', 'bati', 'commercial', 'Dakar', 2400000, NULL, 2020),
(1, 'Terrain nu Diamniadio',     'non_bati', 'terrain_nu', 'Diamniadio', NULL, 12000000, NULL);

-- Activités patentables de démonstration
INSERT INTO activites_patentables (contribuable_id, libelle_activite, chiffre_affaires_annuel, valeur_locative_locaux) VALUES
(1, 'Distribution de produits alimentaires', 180000000, 8000000),
(3, 'Commerce de textiles',                    45000000, 2400000);

-- Véhicule de démonstration
INSERT INTO vehicules (contribuable_id, immatriculation, puissance_fiscale, categorie) VALUES
(1, 'DK-1234-AB', 9, 'utilitaire');
