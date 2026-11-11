CREATE EXTENSION IF NOT EXISTS postgis;

CREATE TABLE land_types (
    code smallint PRIMARY KEY,
    name text NOT NULL,
    agricultural boolean NOT NULL
);

CREATE TABLE land_uses (
    code smallint PRIMARY KEY,
    name text NOT NULL
);

CREATE TABLE protections (
    code smallint PRIMARY KEY,
    name text NOT NULL
);

CREATE TABLE bpej_prices (
    code text PRIMARY KEY,
    price_per_m2 numeric(8, 2) NOT NULL
);

CREATE TABLE municipalities (
    code integer PRIMARY KEY,
    name text NOT NULL
);

CREATE TABLE cadastral_areas (
    code integer PRIMARY KEY,
    name text NOT NULL,
    search_name text NOT NULL,
    municipality_code integer NOT NULL REFERENCES municipalities (code),
    geom geometry(MultiPolygon, 3857) NOT NULL,
    label_point geometry(Point, 3857) NOT NULL
);
CREATE INDEX cadastral_areas_geom_idx ON cadastral_areas USING gist (geom);
CREATE INDEX cadastral_areas_search_idx ON cadastral_areas (search_name text_pattern_ops);

CREATE TABLE parcels (
    id bigint PRIMARY KEY,
    cadastral_area_code integer NOT NULL REFERENCES cadastral_areas (code),
    stem integer NOT NULL,
    sub integer,
    building_plot boolean NOT NULL,
    area integer NOT NULL,
    land_type smallint NOT NULL,
    land_use smallint,
    protections smallint[] NOT NULL DEFAULT '{}',
    bpej jsonb NOT NULL DEFAULT '[]',
    valid_from date,
    geom geometry(MultiPolygon, 3857) NOT NULL
);
CREATE INDEX parcels_geom_idx ON parcels USING gist (geom);
CREATE INDEX parcels_number_idx ON parcels (cadastral_area_code, stem, sub);
CREATE INDEX parcels_area_idx ON parcels (area);

CREATE TABLE district (
    id smallint PRIMARY KEY,
    name text NOT NULL,
    geom geometry(MultiPolygon, 3857) NOT NULL
);

CREATE TABLE meta (
    key text PRIMARY KEY,
    value text NOT NULL
);

