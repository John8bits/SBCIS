-- Add non-destructive record lifecycle and administrator audit history.
-- Apply once after the base schema and administrator table exist.

ALTER TABLE boreholes
    ADD COLUMN archived_at DATETIME NULL AFTER lock_version,
    ADD COLUMN archived_by INT UNSIGNED NULL AFTER archived_at,
    ADD INDEX idx_boreholes_archive (archived_at, borehole_id),
    ADD CONSTRAINT fk_borehole_archived_by
        FOREIGN KEY (archived_by) REFERENCES admins(admin_id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE audit_log (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(100) NULL,
    details_json MEDIUMTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_created (created_at, audit_id),
    INDEX idx_audit_entity (entity_type, entity_id)
);

CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_geotechnical_map_data AS
SELECT b.borehole_id, b.borehole_code, m.municipality_name, br.barangay_name,
    b.latitude, b.longitude, b.elevation_m, b.borehole_depth_m,
    sl.soil_layer_id, sl.layer_number, sl.soil_type, sl.soil_classification,
    sl.soil_description, sl.depth_from_m, sl.depth_to_m, sl.spt_n_value, sl.bearing_capacity_kpa
FROM boreholes b
LEFT JOIN municipalities m ON b.municipality_id=m.municipality_id
LEFT JOIN barangays br ON b.barangay_id=br.barangay_id
LEFT JOIN soil_layers sl ON b.borehole_id=sl.borehole_id
WHERE b.archived_at IS NULL;
