USE sbcdb;

-- Run only after verifying the inconsistency query returns zero rows:
-- SELECT b.borehole_id FROM boreholes b JOIN barangays br ON br.barangay_id=b.barangay_id
-- WHERE b.municipality_id IS NULL OR b.municipality_id<>br.municipality_id;
ALTER TABLE barangays
    ADD UNIQUE INDEX unique_barangay_parent (barangay_id, municipality_id);

ALTER TABLE boreholes
    ADD INDEX idx_borehole_barangay_municipality (barangay_id, municipality_id),
    ADD CONSTRAINT fk_borehole_barangay_municipality
        FOREIGN KEY (barangay_id, municipality_id)
        REFERENCES barangays (barangay_id, municipality_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
