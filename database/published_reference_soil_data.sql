-- Published Southern Leyte geotechnical reference records
--
-- Purpose: UI, record-management, point-map, and insufficient-data workflow testing.
-- This file contains no invented SPT N-values and does not invent borehole GPS positions.
-- The coordinates below are public site/cartographic reference coordinates, not the
-- original drill-hole survey coordinates. Therefore, do not use this import to validate
-- interpolation accuracy or for foundation design.
--
-- Primary measurement sources:
-- 1. 2022 Saint Joseph College study based on earlier DPWH-SLDEO investigations:
--    https://www.scribd.com/document/639430339/Untitled
-- 2. 2014 EB Testing Center geotechnical evaluation, Pres. Quezon bridge, Liloan:
--    https://www.scribd.com/document/290054477/Brgy-Quezon-Liloan-Bridge-Final-Geotechnical-Evaluation-Report
--
-- Coordinate references:
-- Maasin City HS: https://wikimapia.org/29162390/Maasin-City-HighSchool-brgy-Combado-Maasin-City
-- Villa Jacinta NVHS: https://mapcarta.com/W916083238
-- Timba barangay: https://www.philatlas.com/visayas/r08/southern-leyte/malitbog/timba.html
-- Pres. Quezon barangay: https://www.philatlas.com/visayas/r08/southern-leyte/liloan/pres-quezon.html
--
-- The 2022 paper prints bearing-capacity units as kN/m3. Bearing pressure is a
-- force-per-area quantity, so the published numeric results are stored in the system's
-- kPa field (equivalent to kN/m2). Confirm against the original signed bore logs before
-- treating the values as engineering data.

USE sbcdb;
START TRANSACTION;

INSERT IGNORE INTO municipalities (municipality_name) VALUES
    ('City of Maasin'),
    ('Macrohon'),
    ('Malitbog'),
    ('Liloan');

SET @maasin_id = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'City of Maasin');
SET @macrohon_id = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Macrohon');
SET @malitbog_id = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Malitbog');
SET @liloan_id = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Liloan');

INSERT IGNORE INTO barangays (municipality_id, barangay_name) VALUES
    (@maasin_id, 'Combado'),
    (@macrohon_id, 'Lower Villa Jacinta'),
    (@malitbog_id, 'Timba'),
    (@liloan_id, 'Pres. Quezon');

SET @combado_id = (SELECT barangay_id FROM barangays WHERE municipality_id = @maasin_id AND barangay_name = 'Combado');
SET @villa_jacinta_id = (SELECT barangay_id FROM barangays WHERE municipality_id = @macrohon_id AND barangay_name = 'Lower Villa Jacinta');
SET @timba_id = (SELECT barangay_id FROM barangays WHERE municipality_id = @malitbog_id AND barangay_name = 'Timba');
SET @pres_quezon_id = (SELECT barangay_id FROM barangays WHERE municipality_id = @liloan_id AND barangay_name = 'Pres. Quezon');

INSERT INTO boreholes (
    borehole_code, municipality_id, barangay_id, borehole_depth_m,
    latitude, longitude, elevation_m
)
SELECT 'PUB-REF-MAASIN-HS', @maasin_id, @combado_id, 10.50,
       10.1322222, 124.8319444, NULL
WHERE NOT EXISTS (SELECT 1 FROM boreholes WHERE borehole_code = 'PUB-REF-MAASIN-HS');

INSERT INTO boreholes (
    borehole_code, municipality_id, barangay_id, borehole_depth_m,
    latitude, longitude, elevation_m
)
SELECT 'PUB-REF-VILLA-JACINTA', @macrohon_id, @villa_jacinta_id, 10.50,
       10.0405800, 124.9792900, NULL
WHERE NOT EXISTS (SELECT 1 FROM boreholes WHERE borehole_code = 'PUB-REF-VILLA-JACINTA');

INSERT INTO boreholes (
    borehole_code, municipality_id, barangay_id, borehole_depth_m,
    latitude, longitude, elevation_m
)
SELECT 'PUB-REF-TIMBA-ES', @malitbog_id, @timba_id, 10.50,
       10.2003000, 124.9804000, NULL
WHERE NOT EXISTS (SELECT 1 FROM boreholes WHERE borehole_code = 'PUB-REF-TIMBA-ES');

INSERT INTO boreholes (
    borehole_code, municipality_id, barangay_id, borehole_depth_m,
    latitude, longitude, elevation_m
)
SELECT 'PUB-REF-LILOAN-BRIDGE', @liloan_id, @pres_quezon_id, 21.00,
       10.0916000, 125.2222000, NULL
WHERE NOT EXISTS (SELECT 1 FROM boreholes WHERE borehole_code = 'PUB-REF-LILOAN-BRIDGE');

SET @maasin_bh = (SELECT borehole_id FROM boreholes WHERE borehole_code = 'PUB-REF-MAASIN-HS');
SET @villa_bh = (SELECT borehole_id FROM boreholes WHERE borehole_code = 'PUB-REF-VILLA-JACINTA');
SET @timba_bh = (SELECT borehole_id FROM boreholes WHERE borehole_code = 'PUB-REF-TIMBA-ES');
SET @liloan_bh = (SELECT borehole_id FROM boreholes WHERE borehole_code = 'PUB-REF-LILOAN-BRIDGE');

-- The first three capacities are published site-level, multi-layer Terzaghi results.
-- They are attached to layer 1 because the current schema has no site-level result table.
INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @maasin_bh, 1, 'Silty and clayey sand with gravel', 'SM / SC / MS / CS / GC',
       'Published site-level profile: medium dense to very dense silty and clayey sand with gravel; groundwater reported at 3.2 m. Capacity is the published multi-layer allowable result.',
       0.00, 9.00, NULL, 327.20
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @maasin_bh AND layer_number = 1);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @maasin_bh, 2, 'Completely weathered limestone', NULL,
       'Published profile places completely weathered limestone at approximately 9.0-10.5 m.',
       9.00, 10.50, NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @maasin_bh AND layer_number = 2);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @villa_bh, 1, 'Stiff elastic silt and mixed sandy soil', 'MH / MS / SM / SC',
       'Published site-level profile: stiff elastic silt and sandy soil; groundwater reported at 4.5 m. Capacity is the published multi-layer allowable result.',
       0.00, 4.50, NULL, 216.10
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @villa_bh AND layer_number = 1);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @villa_bh, 2, 'Completely weathered coral/limestone', NULL,
       'Published profile reports completely weathered coral material from approximately 4.5 m to the bottom of the investigated profile.',
       4.50, 10.50, NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @villa_bh AND layer_number = 2);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @timba_bh, 1, 'Firm silt with sand', 'ML / MI / SM',
       'Published site-level profile: firm silt with sand; groundwater reported at 9.0 m. Capacity is the published multi-layer allowable result.',
       0.00, 9.00, NULL, 309.50
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @timba_bh AND layer_number = 1);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @timba_bh, 2, 'Weathered limestone', NULL,
       'Published profile reports weathered limestone at approximately 9.0 m and below.',
       9.00, 10.50, NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @timba_bh AND layer_number = 2);

-- The bridge report gives a founding-level value rather than a layer-by-layer capacity.
INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @liloan_bh, 1, 'Completely weathered boulders', 'Very poor RQD',
       'Published bridge investigation: two 21 m boreholes, SPT sampling at about 1.5 m intervals, and generally completely weathered boulders with very poor RQD.',
       0.00, 2.50, NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @liloan_bh AND layer_number = 1);

INSERT INTO soil_layers (
    borehole_id, layer_number, soil_type, soil_classification, soil_description,
    depth_from_m, depth_to_m, spt_n_value, bearing_capacity_kpa
)
SELECT @liloan_bh, 2, 'Completely weathered boulders', 'Very poor RQD',
       'Published minimum allowable bearing capacity at the proposed founding level of about 2.5 m. The report states that the soft-rock value is semi-empirical.',
       2.50, 21.00, NULL, 400.00
WHERE NOT EXISTS (SELECT 1 FROM soil_layers WHERE borehole_id = @liloan_bh AND layer_number = 2);

COMMIT;

-- Expected result: 4 independently located reference points.
-- The production interpolation guard requires at least 5 eligible unique points, so
-- the map should report insufficient data instead of drawing an unreliable surface.

