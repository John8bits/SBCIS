# Published Southern Leyte soil reference data

The ready-to-import file is [`database/published_reference_soil_data.sql`](../database/published_reference_soil_data.sql).

It supplies four independently located, published geotechnical reference records:

| Code | Location | Published allowable capacity | Coordinate quality |
|---|---|---:|---|
| `PUB-REF-MAASIN-HS` | Maasin City High School, Combado | 327.2 kPa* | mapped school location |
| `PUB-REF-VILLA-JACINTA` | Villa Jacinta NVHS, Macrohon | 216.1 kPa* | mapped school location |
| `PUB-REF-TIMBA-ES` | Timba Elementary School, Malitbog | 309.5 kPa* | barangay reference coordinate |
| `PUB-REF-LILOAN-BRIDGE` | Proposed bridge, Pres. Quezon, Liloan | 400 kPa at about 2.5 m | barangay reference coordinate |

`*` The 2022 paper prints `kN/m3`, although bearing pressure is a force-per-area
quantity. The import preserves the published numeric values in the application's kPa
field. Confirm the units against the original signed investigation records before any
engineering use.

## What this data can test

- municipality, barangay, borehole, and soil-layer record screens;
- search, filtering, exports, and point-marker rendering;
- the interpolation guard's `insufficient_data` state;
- display of real published soil descriptions and allowable-capacity values.

## What it cannot validate

It cannot validate spatial interpolation or map accuracy. The available public copies do
not expose the original surveyed coordinates for the individual boreholes or complete
machine-readable layer-by-layer SPT results. The three school results are also site-level,
multi-layer calculations rather than a separate result for each imported layer.

The application requires at least five unique eligible points, while this defensible public
set has four. A fifth invented point or a small coordinate offset would make a colored
surface appear, but it would be misleading. For a valid accuracy test, import the original
borehole logs with WGS84 coordinates, depth intervals, measured SPT N-values, and the
bearing-capacity method/result. Hold out some measured locations from interpolation and
compare predictions with those measurements (for example using MAE and RMSE).

## Sources

- [2022 Southern Leyte bearing-capacity study](https://www.scribd.com/document/639430339/Untitled)
- [2014 Pres. Quezon, Liloan bridge geotechnical evaluation](https://www.scribd.com/document/290054477/Brgy-Quezon-Liloan-Bridge-Final-Geotechnical-Evaluation-Report)
- [Maasin City High School coordinate reference](https://wikimapia.org/29162390/Maasin-City-HighSchool-brgy-Combado-Maasin-City)
- [Villa Jacinta NVHS coordinate reference](https://mapcarta.com/W916083238)
- [Timba barangay coordinate reference](https://www.philatlas.com/visayas/r08/southern-leyte/malitbog/timba.html)
- [Pres. Quezon barangay coordinate reference](https://www.philatlas.com/visayas/r08/southern-leyte/liloan/pres-quezon.html)

