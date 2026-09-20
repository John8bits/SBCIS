-- Apply once to existing SBCIS databases. MySQL 8.0+.
ALTER TABLE boreholes
    ADD COLUMN lock_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at,
    ADD INDEX idx_borehole_recent (created_at, borehole_id);

ALTER TABLE soil_layers
    ADD INDEX idx_soil_layer_recent (created_at, soil_layer_id);

-- unique_borehole_layer already supports lookups by borehole_id; the separate
-- single-column index duplicates that prefix and adds write/storage overhead.
ALTER TABLE soil_layers DROP INDEX idx_soil_borehole;

CREATE TABLE system_revisions (
    revision_name VARCHAR(50) PRIMARY KEY,
    revision_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO system_revisions (revision_name, revision_value) VALUES ('interpolation_source', 0);

DELIMITER $$
CREATE TRIGGER trg_boreholes_interpolation_insert AFTER INSERT ON boreholes FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
CREATE TRIGGER trg_boreholes_interpolation_update AFTER UPDATE ON boreholes FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
CREATE TRIGGER trg_boreholes_interpolation_delete AFTER DELETE ON boreholes FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
CREATE TRIGGER trg_layers_interpolation_insert AFTER INSERT ON soil_layers FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
CREATE TRIGGER trg_layers_interpolation_update AFTER UPDATE ON soil_layers FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
CREATE TRIGGER trg_layers_interpolation_delete AFTER DELETE ON soil_layers FOR EACH ROW
BEGIN UPDATE system_revisions SET revision_value=revision_value+1 WHERE revision_name='interpolation_source'; END$$
DELIMITER ;
