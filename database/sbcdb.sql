
USE sbcdb;

CREATE TABLE municipalities (
    municipality_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE barangays (
    barangay_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality_id INT UNSIGNED NOT NULL,
    barangay_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_barangay_municipality
        FOREIGN KEY (municipality_id)
        REFERENCES municipalities(municipality_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT unique_barangay
        UNIQUE (municipality_id, barangay_name)
);

CREATE TABLE boreholes (
    borehole_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borehole_code VARCHAR(50) NOT NULL UNIQUE,
    municipality_id INT UNSIGNED NULL,
    barangay_id INT UNSIGNED NULL,
    borehole_depth_m DECIMAL(8,2) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    elevation_m DECIMAL(8,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_borehole_municipality
        FOREIGN KEY (municipality_id)
        REFERENCES municipalities(municipality_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_borehole_barangay
        FOREIGN KEY (barangay_id)
        REFERENCES barangays(barangay_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT chk_borehole_depth
        CHECK (borehole_depth_m > 0),

    CONSTRAINT chk_latitude
        CHECK (latitude BETWEEN -90 AND 90),

    CONSTRAINT chk_longitude
        CHECK (longitude BETWEEN -180 AND 180)
);

CREATE TABLE soil_layers (
    soil_layer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borehole_id INT UNSIGNED NOT NULL,
    layer_number INT UNSIGNED NOT NULL,
    soil_type VARCHAR(100) NOT NULL,
    soil_classification VARCHAR(100) NULL,
    soil_description TEXT NULL,
    depth_from_m DECIMAL(8,2) NOT NULL,
    depth_to_m DECIMAL(8,2) NOT NULL,
    spt_n_value INT UNSIGNED NULL,
    bearing_capacity_kpa DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_soil_layer_borehole
        FOREIGN KEY (borehole_id)
        REFERENCES boreholes(borehole_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT unique_borehole_layer
        UNIQUE (borehole_id, layer_number),

    CONSTRAINT chk_layer_depth
        CHECK (
            depth_from_m >= 0
            AND depth_to_m > depth_from_m
        ),

    CONSTRAINT chk_bearing_capacity
        CHECK (
            bearing_capacity_kpa IS NULL
            OR bearing_capacity_kpa >= 0
        )
);

CREATE INDEX idx_borehole_coordinates
ON boreholes(latitude, longitude);

CREATE INDEX idx_borehole_municipality
ON boreholes(municipality_id);

CREATE INDEX idx_borehole_barangay
ON boreholes(barangay_id);

CREATE INDEX idx_soil_borehole
ON soil_layers(borehole_id);

CREATE INDEX idx_bearing_capacity
ON soil_layers(bearing_capacity_kpa);

CREATE INDEX idx_soil_type
ON soil_layers(soil_type);

CREATE VIEW v_geotechnical_map_data AS
SELECT
    b.borehole_id,
    b.borehole_code,
    m.municipality_name,
    br.barangay_name,
    b.latitude,
    b.longitude,
    b.elevation_m,
    b.borehole_depth_m,
    sl.soil_layer_id,
    sl.layer_number,
    sl.soil_type,
    sl.soil_classification,
    sl.soil_description,
    sl.depth_from_m,
    sl.depth_to_m,
    sl.spt_n_value,
    sl.bearing_capacity_kpa

FROM boreholes b

LEFT JOIN municipalities m
    ON b.municipality_id = m.municipality_id

LEFT JOIN barangays br
    ON b.barangay_id = br.barangay_id

LEFT JOIN soil_layers sl
    ON b.borehole_id = sl.borehole_id;


CREATE TABLE admins (
    admin_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (email, password)
VALUES
(
    'shawngaldo@gmail.com',
    '$2y$10$XEVTVz.b46jiwBdfdIkOkej1DxC.UCvTm5RCh.bVrCcVzyE6HCQDe'
),
(
    'jbitss@gmail.com',
    '$2y$10$JEpD/ne4IfQl2wK6fVb.Pe/f/LDYq1jmxM4NMdPdF.1lNLqHvQ3vu'
);