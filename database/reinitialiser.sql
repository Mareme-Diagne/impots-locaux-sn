-- =====================================================================
-- Vide les données de test SANS toucher à la structure des tables.
-- Usage : phpMyAdmin > onglet SQL > coller ce script > Exécuter.
--
-- NOTE : on utilise DELETE FROM plutôt que TRUNCATE, car MySQL refuse
-- de TRUNCATE une table référencée par une clé étrangère même quand
-- FOREIGN_KEY_CHECKS est désactivé (contrairement à DELETE, qui lui
-- respecte bien ce réglage).
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM journal_audit;
DELETE FROM paiements;
DELETE FROM taxations;
DELETE FROM vehicules;
DELETE FROM activites_patentables;
DELETE FROM biens_immobiliers;
DELETE FROM contribuables;
-- On NE vide PAS utilisateurs et bareme_taux / bareme_patente_droit_fixe / bareme_vignette :
-- ce sont des données de configuration, pas des données de test.

-- Remet les compteurs auto-incrémentés à 1, pour que les nouveaux IDs
-- redémarrent proprement plutôt que de continuer à partir d'un grand nombre.
ALTER TABLE journal_audit AUTO_INCREMENT = 1;
ALTER TABLE paiements AUTO_INCREMENT = 1;
ALTER TABLE taxations AUTO_INCREMENT = 1;
ALTER TABLE vehicules AUTO_INCREMENT = 1;
ALTER TABLE activites_patentables AUTO_INCREMENT = 1;
ALTER TABLE biens_immobiliers AUTO_INCREMENT = 1;
ALTER TABLE contribuables AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Réinsertion des données de démonstration (identiques à schema.sql)
INSERT INTO contribuables (type, nom_raison_sociale, ninea, telephone, email, adresse, commune) VALUES
('entreprise',        'SARL Baobab Distribution', '004512587',  '+221 77 123 45 67', 'contact@baobab-dist.sn', 'Route de Rufisque, Km 8', 'Dakar'),
('personne_physique',  'Fatou Ndiaye',              NULL,        '+221 76 555 12 34', 'fatou.ndiaye@example.com', 'Cité Keur Gorgui, Villa 42', 'Dakar'),
('entreprise',        'Ets Thiam Frères',          '007845123',  '+221 78 999 88 77', 'ets.thiam@example.com', 'Marché Sandaga, Lot 15', 'Dakar');

INSERT INTO biens_immobiliers (contribuable_id, designation, nature, usage_bien, commune, valeur_locative_annuelle, valeur_venale, annee_achevement) VALUES
(1, 'Entrepôt Baobab Rufisque', 'bati', 'industriel', 'Rufisque', 8000000, NULL, 2015),
(2, 'Villa Keur Gorgui',         'bati', 'residence_principale', 'Dakar', 3600000, NULL, 2012),
(3, 'Local commercial Sandaga', 'bati', 'commercial', 'Dakar', 2400000, NULL, 2020),
(1, 'Terrain nu Diamniadio',     'non_bati', 'terrain_nu', 'Diamniadio', NULL, 12000000, NULL);

INSERT INTO activites_patentables (contribuable_id, libelle_activite, chiffre_affaires_annuel, valeur_locative_locaux) VALUES
(1, 'Distribution de produits alimentaires', 180000000, 8000000),
(3, 'Commerce de textiles',                    45000000, 2400000);

INSERT INTO vehicules (contribuable_id, immatriculation, puissance_fiscale, categorie) VALUES
(1, 'DK-1234-AB', 9, 'utilitaire');