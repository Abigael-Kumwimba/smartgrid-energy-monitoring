USE smartgrid_energy;

ALTER TABLE consommations
    ADD COLUMN IF NOT EXISTS frequence FLOAT DEFAULT NULL AFTER energie,
    ADD COLUMN IF NOT EXISTS facteur_puissance FLOAT DEFAULT NULL AFTER frequence,
    ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT NULL AFTER facteur_puissance;

ALTER TABLE factures
    ADD COLUMN IF NOT EXISTS date_paiement TIMESTAMP NULL DEFAULT NULL AFTER date_facture;

