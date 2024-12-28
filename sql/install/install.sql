/* *************************************************************************
 *  This is the main SQL DataTable Definition for Noteworthy
 *
 *  Note: Be sure to replace the database name, user, and password with
 *        something better than this.
 * ************************************************************************* */
CREATE DATABASE "noteworthy";

CREATE USER nwapi WITH ENCRYPTED PASSWORD 'superSecretPassword!123';

GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO nwapi;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nwapi;

/** ************************************************************************* *
 *  Required and/or Common Functions
 ** ************************************************************************* */
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

CREATE OR REPLACE FUNCTION guid_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."guid" = uuid_generate_v4();
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION guid_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF NEW."guid" <> OLD."guid" THEN
        NEW."guid" = OLD."guid";
    END IF;

    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION date_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION meta_before()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."key" = LOWER(NEW."key");
    IF LENGTH(COALESCE(TRIM(NEW."key"), '')) <= 0 THEN
        NEW."key" = NULL;
    END IF;

    IF COALESCE(TRIM(NEW."value"), '') <> COALESCE(TRIM(OLD."value"), '') THEN
        NEW."is_deleted" = false;
    END IF;

    IF LENGTH(COALESCE(TRIM(NEW."value"), '')) <= 0 THEN
        NEW."is_deleted" = true;
    END IF;

    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION return_original()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    RETURN OLD;
END;
$$;

/** ************************************************************************* *
 *  Create Sequence (Preliminaries)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Country";
CREATE TABLE IF NOT EXISTS "Country" (
    "code"         varchar(2)            NOT NULL    ,
    "name"         varchar(255)          NOT NULL    ,
    "label"        varchar(64)               NULL    ,
    "sort_order"   smallint              NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "is_available" boolean               NOT NULL    DEFAULT false,

    "is_deleted"   boolean               NOT NULL    DEFAULT false,
    "created_at"   timestamp             NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"   timestamp             NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code")
);
CREATE INDEX idx_country_main ON "Country" ("is_available");

INSERT INTO "Country" ("code", "name")
VALUES ('JP', 'Japan'), ('AF', 'Afghanistan'), ('AL', 'Albania'), ('DZ', 'Algeria'), ('AS', 'American Samoa'), ('AD', 'Andorra'),
       ('AO', 'Angola'), ('AI', 'Anguilla'), ('AQ', 'Antarctica'), ('AG', 'Antigua and Barbuda'), ('AR', 'Argentina'), ('AM', 'Armenia'),
       ('AW', 'Aruba'), ('AU', 'Australia'), ('AT', 'Austria'), ('AZ', 'Azerbaijan'), ('BS', 'Bahamas'), ('BH', 'Bahrain'),
       ('BD', 'Bangladesh'), ('BB', 'Barbados'), ('BY', 'Belarus'), ('BE', 'Belgium'), ('BZ', 'Belize'), ('BJ', 'Benin'),
       ('BM', 'Bermuda'), ('BT', 'Bhutan'), ('BO', 'Bolivia'), ('BA', 'Bosnia-Herzegovina'), ('BW', 'Botswana'), ('BV', 'Bouvet Island'),
       ('BR', 'Brazil'), ('IO', 'British Indian Ocean Territory'), ('BN', 'Brunei'), ('BG', 'Bulgaria'), ('BF', 'Burkina Faso'), ('BI', 'Burundi'),
       ('KH', 'Cambodia'), ('CM', 'Cameroon'), ('CA', 'Canada'), ('CV', 'Cape Verde'), ('KY', 'Cayman Islands'), ('CF', 'Central African Republic'),
       ('TD', 'Chad'), ('CL', 'Chile'), ('CN', 'China'), ('CX', 'Christmas Island'), ('CC', 'Cocos Islands'), ('CO', 'Colombia'),
       ('KM', 'Comoros'), ('CG', 'Congo'), ('CD', 'Congo, Democratic Republic'), ('CK', 'Cook Islands'), ('CR', 'Costa Rica'), ('HR', 'Croatia'),
       ('CU', 'Cuba'), ('CY', 'Cyprus'), ('CZ', 'Czech Republic'), ('DK', 'Denmark'), ('DJ', 'Djibouti'), ('DM', 'Dominica'),
       ('DO', 'Dominican Republic'), ('EC', 'Ecuador'), ('EG', 'Egypt'), ('SV', 'El Salvador'), ('GQ', 'Equatorial Guinea'), ('ER', 'Eritrea'),
       ('EE', 'Estonia'), ('ET', 'Ethiopia'), ('EU', 'European Union'), ('FK', 'Falkland Islands'), ('FO', 'Faroe Islands'), ('FJ', 'Fiji'),
       ('FI', 'Finland'), ('FR', 'France'), ('GF', 'French Guiana'), ('TF', 'French Southern Territories'), ('GA', 'Gabon'), ('GM', 'Gambia'),
       ('GE', 'Georgia'), ('DE', 'Germany'), ('GH', 'Ghana'), ('GI', 'Gibraltar'), ('GB', 'Great Britain'), ('GR', 'Greece'),
       ('GL', 'Greenland'), ('GD', 'Grenada'), ('GP', 'Guadeloupe'), ('GU', 'Guam'), ('GT', 'Guatemala'), ('GG', 'Guernsey'),
       ('GN', 'Guinea'), ('GW', 'Guinea Bissau'), ('GY', 'Guyana'), ('HT', 'Haiti'), ('HM', 'Heard Island and McDonald Islands'), ('HN', 'Honduras'),
       ('HK', 'Hong Kong'), ('HU', 'Hungary'), ('IS', 'Iceland'), ('IN', 'India'), ('ID', 'Indonesia'), ('IR', 'Iran'),
       ('IQ', 'Iraq'), ('IE', 'Ireland'), ('IM', 'Isle of Man'), ('IL', 'Israel'), ('IT', 'Italy'), ('CI', 'Ivory Coast'),
       ('JM', 'Jamaica'), ('JE', 'Jersey'), ('JO', 'Jordan'), ('KZ', 'Kazakhstan'), ('KE', 'Kenya'), ('KI', 'Kiribati'),
       ('KP', 'North Korea'), ('KR', 'South Korea'), ('KW', 'Kuwait'), ('KG', 'Kyrgyzstan'), ('LA', 'Laos'), ('LV', 'Latvia'),
       ('LB', 'Lebanon'), ('LS', 'Lesotho'), ('LR', 'Liberia'), ('LY', 'Libya'), ('LI', 'Liechtenstein'), ('LT', 'Lithuania'),
       ('LU', 'Luxembourg'), ('MO', 'Macau'), ('MK', 'Macedonia'), ('MG', 'Madagascar'), ('MW', 'Malawi'), ('MY', 'Malaysia'),
       ('MV', 'Maldives'), ('ML', 'Mali'), ('MT', 'Malta'), ('MH', 'Marshall Islands'), ('MQ', 'Martinique'), ('MR', 'Mauritania'),
       ('MU', 'Mauritius'), ('YT', 'Mayotte'), ('MX', 'Mexico'), ('FM', 'Micronesia'), ('MD', 'Moldova'), ('MC', 'Monaco'),
       ('MN', 'Mongolia'), ('ME', 'Montenegro'), ('MS', 'Montserrat'), ('MA', 'Morocco'), ('MZ', 'Mozambique'), ('MM', 'Myanmar'),
       ('NA', 'Namibia'), ('NR', 'Nauru'), ('NP', 'Nepal'), ('NL', 'Netherlands'), ('AN', 'Netherlands Antilles'), ('NC', 'New Caledonia'),
       ('NZ', 'New Zealand'), ('NI', 'Nicaragua'), ('NE', 'Niger'), ('NG', 'Nigeria'), ('NU', 'Niue'), ('NF', 'Norfolk Island'),
       ('MP', 'Northern Mariana Islands'), ('NO', 'Norway'), ('OM', 'Oman'), ('PK', 'Pakistan'), ('PW', 'Palau'), ('PA', 'Panama'),
       ('PG', 'Papua New Guinea'), ('PY', 'Paraguay'), ('PE', 'Peru'), ('PH', 'Philippines'), ('PN', 'Pitcairn Island'), ('PL', 'Poland'),
       ('PF', 'Polynesia'), ('PT', 'Portugal'), ('PR', 'Puerto Rico'), ('QA', 'Qatar'), ('RE', 'Reunion'), ('RO', 'Romania'),
       ('RU', 'Russia'), ('RW', 'Rwanda'), ('SH', 'Saint Helena'), ('KN', 'Saint Kitts'), ('LC', 'Saint Lucia Castries'), ('PM', 'Saint Pierre and Miquelon'),
       ('VC', 'Saint Vincent'), ('WS', 'Samoa'), ('SM', 'San Marino'), ('ST', 'Sao Tome'), ('SA', 'Saudi Arabia'), ('SN', 'Senegal'),
       ('RS', 'Serbia'), ('SC', 'Seychelles'), ('SL', 'Sierra Leone'), ('SG', 'Singapore'), ('SK', 'Slovakia'), ('SI', 'Slovenia'),
       ('SB', 'Solomon Islands'), ('SO', 'Somalia'), ('ZA', 'South Africa'), ('GS', 'South Georgia'), ('SS', 'South Sudan'), ('ES', 'Spain'),
       ('LK', 'Sri Lanka'), ('SD', 'Sudan'), ('SR', 'Suriname'), ('SJ', 'Svalbard'), ('SZ', 'Swaziland'), ('SE', 'Sweden'),
       ('CH', 'Switzerland'), ('SY', 'Syria'), ('TW', 'Taiwan'), ('TJ', 'Tajikistan'), ('TZ', 'Tanzania'), ('TH', 'Thailand'),
       ('TG', 'Togo'), ('TK', 'Tokelau'), ('TO', 'Tonga'), ('TT', 'Trinidad and Tobago'), ('TN', 'Tunisia'), ('TR', 'Turkey'),
       ('TM', 'Turkmenistan'), ('TC', 'Turks and Caicos Islands'), ('TV', 'Tuvalu'), ('UK', 'United Kingdom'), ('UG', 'Uganda'), ('UA', 'Ukraine'),
       ('AE', 'United Arab Emirates'), ('UY', 'Uruguay'), ('US', 'United States'), ('UM', 'USA Minor Islands'), ('UZ', 'Uzbekistan'), ('VU', 'Vanuatu'),
       ('VA', 'Vatican City'), ('VE', 'Venezuela'), ('VN', 'Vietnam'), ('VG', 'Virgin Islands (British)'), ('VI', 'Virgin Islands (USA)'),
       ('WF', 'Wallis and Futuna Islands'), ('EH', 'Western Sahara'), ('YE', 'Yemen'), ('ZM', 'Zambia'), ('ZW', 'Zimbabwe');

UPDATE "Country"
   SET "label" = CONCAT('lblCountry', "code"),
       "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Country"
   SET "sort_order" = CASE WHEN "code" = 'JP' THEN 0
                           WHEN "code" IN ('CA', 'US') THEN 10
                           ELSE 25 END,
       "is_available" = true
 WHERE "code" IN ('JP', 'US', 'CA', 'MX', 'CO');

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_country_before_update ON "Country";
CREATE TRIGGER trg_country_before_update
  BEFORE UPDATE ON "Country"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_country_before_delete ON "Country";
CREATE OR REPLACE RULE rule_country_before_delete AS
    ON DELETE TO "Country"
    DO INSTEAD
       UPDATE "Country" SET "is_available" = false, "is_deleted" = true WHERE "Country"."code" = OLD."code";

DROP TABLE IF EXISTS "CountryState";
CREATE TABLE IF NOT EXISTS "CountryState" (
    "id"                serial                          ,
    "country_code"      varchar(2)          NOT NULL    ,
    "name"              varchar(256)        NOT NULL    DEFAULT '',
    "short_code"        varchar(8)              NULL    ,
    "label"             varchar(64)             NULL    ,
    "sort_order"        smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),

    "is_deleted"        boolean             NOT NULL    DEFAULT false,
    "created_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("country_code") REFERENCES "Country" ("code")
);
CREATE INDEX idx_state_main ON "CountryState" ("country_code");

INSERT INTO "CountryState" ("name", "country_code", "label", "sort_order")
VALUES ('Hokkaido', 'JP', 'lblHokkaido', 10),
       ('Aomori', 'JP', 'lblAomori', 20),        ('Iwate', 'JP', 'lblIwate', 20),       ('Yamagata', 'JP', 'lblYamagata', 20),
       ('Miyagi', 'JP', 'lblMiyagi', 20),        ('Akita', 'JP', 'lblAkita', 20),       ('Fukushima', 'JP', 'lblFukushima', 20),
       ('Ibaraki', 'JP', 'lblIbaraki', 30),       ('Tochigi', 'JP', 'lblTochigi', 30),    ('Gunma', 'JP', 'lblGunma', 30),
       ('Saitama', 'JP', 'lblSaitama', 30),       ('Chiba', 'JP', 'lblChiba', 30),       ('Tokyo', 'JP', 'lblTokyo', 30),
       ('Kanagawa', 'JP', 'lblKanagawa', 30),
       ('Niigata', 'JP', 'lblNiigata', 40),       ('Toyama', 'JP', 'lblToyama', 40),      ('Ishikawa', 'JP', 'lblIshikawa', 40),
       ('Fukui', 'JP', 'lblFukui', 40),          ('Yamanashi', 'JP', 'lblYamanashi', 40),('Nagano', 'JP', 'lblNagano', 40),
       ('Gifu', 'JP', 'lblGifu', 40),            ('Shizuoka', 'JP', 'lblShizuoka', 40),  ('Aichi', 'JP', 'lblAichi', 40),
       ('Mie', 'JP', 'lblMie', 50),              ('Shiga', 'JP', 'lblShiga', 50),       ('Kyoto', 'JP', 'lblKyoto', 50),
       ('Osaka', 'JP', 'lblOsaka', 50),          ('Hyogo', 'JP', 'lblHyogo', 50),       ('Nara', 'JP', 'lblNara', 50),
       ('Wakayama', 'JP', 'lblWakayama', 50),
       ('Tottori', 'JP', 'lblTottori', 60),       ('Shimane', 'JP', 'lblShimane', 60),    ('Okayama', 'JP', 'lblOkayama', 60),
       ('Hiroshima', 'JP', 'lblHiroshima', 60),     ('Yamaguchi', 'JP', 'lblYamaguchi', 60),
       ('Tokushima', 'JP', 'lblTokushima', 70),     ('Kagawa', 'JP', 'lblKagawa', 70),      ('Ehime', 'JP', 'lblEhime', 70),
       ('Kochi', 'JP', 'lblKochi', 70),
       ('Fukuoka', 'JP', 'lblFukuoka', 80),       ('Saga', 'JP', 'lblSaga', 80),       ('Nagasaki', 'JP', 'lblNagasaki', 80),
       ('Kumamoto', 'JP', 'lblKumamoto', 80),       ('Oita', 'JP', 'lblOita', 80),       ('Miyazaki', 'JP', 'lblMiyazaki', 80),
       ('Kagoshima', 'JP', 'lblKagoshima', 80),
       ('Okinawa', 'JP', 'lblOkinawa', 90);

INSERT INTO "CountryState" ("name", "country_code", "label", "sort_order")
VALUES ('Alberta', 'CA', 'lblAlberta', 30), ('British Columbia', 'CA', 'lblBritCol', 30), ('Manitoba', 'CA', 'lblManitoba', 30),
       ('New Brunswick', 'CA', 'lblNewBrun', 30), ('Newfoundland', 'CA', 'lblNewfoundland', 30), ('Nova Scotia', 'CA', 'lblNovaScotia', 30),
       ('Ontario', 'CA', 'lblOntario', 30), ('Prince Edward Island', 'CA', 'lblPEI', 30), ('Quebec', 'CA', 'lblQuebec', 30),
       ('Saskatchewan', 'CA', 'lblSaskatchewan', 30), ('Northwest Territories', 'CA', 'lblNWTerr', 50), ('Nunavut', 'CA', 'lblNunavut', 50),
       ('Yukon', 'CA', 'lblYukon', 50);

INSERT INTO "CountryState" ("name", "country_code")
VALUES ('Alabama', 'US'), ('Alaska', 'US'), ('Arizona', 'US'), ('Arkansas', 'US'), ('California', 'US'), ('Colorado', 'US'), ('Connecticut', 'US'),
       ('Delaware', 'US'), ('Florida', 'US'), ('Georgia', 'US'), ('Hawaii', 'US'), ('Idaho', 'US'), ('Illinois', 'US'), ('Indiana', 'US'), ('Iowa', 'US'),
       ('Kansas', 'US'), ('Kentucky', 'US'), ('Louisiana', 'US'), ('Maine', 'US'), ('Maryland', 'US'), ('Massachusetts', 'US'), ('Michigan', 'US'),
       ('Minnesota', 'US'), ('Mississippi', 'US'), ('Missouri', 'US'), ('Montana', 'US'), ('Nebraska', 'US'), ('Nevada', 'US'), ('New Hampshire', 'US'),
       ('New Jersey', 'US'), ('New Mexico', 'US'), ('New York', 'US'), ('North Carolina', 'US'), ('North Dakota', 'US'), ('Ohio', 'US'), ('Oklahoma', 'US'),
       ('Oregon', 'US'), ('Pennsylvania', 'US'), ('Rhode Island', 'US'), ('South Carolina', 'US'), ('South Dakota', 'US'), ('Tennessee', 'US'),
       ('Texas', 'US'), ('Utah', 'US'), ('Vermont', 'US'), ('Virginia', 'US'), ('Washington', 'US'), ('West Virginia', 'US'), ('Wisconsin', 'US'),
       ('Wyoming', 'US'), ('District of Columbia', 'US');

INSERT INTO "CountryState" ("name", "country_code", "label", "sort_order")
VALUES ('Aguascalientes', 'MX', 'lblMX001', 50),
       ('Baja California', 'MX', 'lblMX002', 50),
       ('Baja California Sur', 'MX', 'lblMX003', 50),
       ('Campeche', 'MX', 'lblMX004', 50),
       ('Chiapas', 'MX', 'lblMX005', 50),
       ('Ciudad de México', 'MX', 'lblMX006', 50),
       ('Chihuahua', 'MX', 'lblMX007', 50),
       ('Coahuila de Zaragoza', 'MX', 'lblMX008', 50),
       ('Colima', 'MX', 'lblMX009', 50),
       ('Durango', 'MX', 'lblMX010', 50),
       ('Guanajuato', 'MX', 'lblMX011', 50),
       ('Guerrero', 'MX', 'lblMX012', 50),
       ('Hidalgo', 'MX', 'lblMX013', 50),
       ('Jalisco', 'MX', 'lblMX014', 50),
       ('México', 'MX', 'lblMX015', 50),
       ('Michoacán de Ocampo', 'MX', 'lblMX016', 50),
       ('Morelos', 'MX', 'lblMX017', 50),
       ('Nayarit', 'MX', 'lblMX018', 50),
       ('Nuevo León', 'MX', 'lblMX019', 50),
       ('Oaxaca', 'MX', 'lblMX020', 50),
       ('Puebla', 'MX', 'lblMX021', 50),
       ('Querétaro de Arteaga', 'MX', 'lblMX022', 50),
       ('Quintana Roo', 'MX', 'lblMX023', 50),
       ('San Luis Potosí', 'MX', 'lblMX024', 50),
       ('Sinaloa', 'MX', 'lblMX025', 50),
       ('Sonora', 'MX', 'lblMX026', 50),
       ('Tabasco', 'MX', 'lblMX027', 50),
       ('Tamaulipas', 'MX', 'lblMX028', 50),
       ('Tlaxcala', 'MX', 'lblMX029', 50),
       ('Veracruz de Ignacio de la Llave', 'MX', 'lblMX030', 50),
       ('Yucatán', 'MX', 'lblMX031', 50),
       ('Zacatecas', 'MX', 'lblMX032', 50);

INSERT INTO "CountryState" ("country_code", "name", "label", "sort_order")
VALUES ('CO', 'Capital District', 'lblCO000', 0),
       ('CO', 'Amazonas', 'lblCO001', 5),
       ('CO', 'Antioquia', 'lblCO002', 10),
       ('CO', 'Arauca', 'lblCO003', 15),
       ('CO', 'Atlántico', 'lblCO004', 20),
       ('CO', 'Bolívar', 'lblCO005', 25),
       ('CO', 'Boyacá', 'lblCO006', 30),
       ('CO', 'Caldas', 'lblCO007', 35),
       ('CO', 'Caquetá', 'lblCO008', 40),
       ('CO', 'Casanare', 'lblCO009', 45),
       ('CO', 'Cauca', 'lblCO010', 50),
       ('CO', 'Cesar', 'lblCO011', 55),
       ('CO', 'Chocó', 'lblCO012', 60),
       ('CO', 'Córdoba', 'lblCO013', 65),
       ('CO', 'Cundinamarca', 'lblCO014', 70),
       ('CO', 'Guainía', 'lblCO015', 75),
       ('CO', 'Guaviare', 'lblCO016', 80),
       ('CO', 'Huila', 'lblCO017', 85),
       ('CO', 'La Guajira', 'lblCO018', 90),
       ('CO', 'Magdalena', 'lblCO019', 95),
       ('CO', 'Meta', 'lblCO020', 100),
       ('CO', 'Nariño', 'lblCO021', 105),
       ('CO', 'Norte de Santander', 'lblCO022', 110),
       ('CO', 'Putumayo', 'lblCO023', 115),
       ('CO', 'Quindío', 'lblCO024', 120),
       ('CO', 'Risaralda', 'lblCO025', 125),
       ('CO', 'San Andrés y Providencia', 'lblCO026', 130),
       ('CO', 'Santander', 'lblCO027', 135),
       ('CO', 'Sucre', 'lblCO028', 140),
       ('CO', 'Tolima', 'lblCO029', 145),
       ('CO', 'Valle del Cauca', 'lblCO030', 150),
       ('CO', 'Vaupés', 'lblCO031', 155),
       ('CO', 'Vichada', 'lblCO032', 160);

UPDATE "CountryState"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_countrystate_before_update ON "CountryState";
CREATE TRIGGER trg_countrystate_before_update
  BEFORE UPDATE ON "CountryState"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_countrystate_before_delete ON "CountryState";
CREATE OR REPLACE RULE rule_countrystate_before_delete AS
    ON DELETE TO "CountryState"
    DO INSTEAD
       UPDATE "CountryState" SET "is_deleted" = true WHERE "CountryState"."id" = OLD."id";

DROP TABLE IF EXISTS "Language";
CREATE TABLE IF NOT EXISTS "Language" (
    "code"          char(2)             NOT NULL    ,
    "name"          varchar(80)         NOT NULL    ,

    "sort_order"    smallint            NOT NULL    DEFAULT 50      CHECK ("sort_order" BETWEEN 0 AND 999),
    "is_available"  boolean             NOT NULL    DEFAULT false,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code")
);
CREATE INDEX idx_lang_main ON "Language" ("is_available" DESC);

DROP TABLE IF EXISTS "Locale";
CREATE TABLE IF NOT EXISTS "Locale" (
    "code"          varchar(6)          NOT NULL    ,
    "language_code" char(2)             NOT NULL    ,
    "name"          varchar(80)         NOT NULL    DEFAULT '',

    "is_available"  boolean             NOT NULL    DEFAULT true,
    "is_default"    boolean             NOT NULL    DEFAULT false,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("code"),
    FOREIGN KEY ("language_code") REFERENCES "Language" ("code")
);
CREATE INDEX idx_locale_main ON "Locale" ("is_available" DESC);
CREATE INDEX idx_locale_def ON "Locale" ("is_default" DESC);

INSERT INTO "Language" ("code", "name", "is_available", "sort_order")
VALUES ('ar', 'Arabic', false, 50),
       ('be', 'Belarusian', false, 50),
       ('bg', 'Bulgarian', false, 50),
       ('ca', 'Catalan', false, 50),
       ('cs', 'Czech', false, 50),
       ('da', 'Danish', false, 50),
       ('de', 'German', false, 20),
       ('el', 'Greek', false, 50),
       ('en', 'English', true, 10),
       ('es', 'Spanish', false, 20),
       ('et', 'Estonian', false, 50),
       ('eu', 'Basque', false, 50),
       ('fi', 'Finnish', false, 50),
       ('fo', 'Faroese', false, 50),
       ('fr', 'French', false, 20),
       ('gl', 'Galician', false, 50),
       ('gu', 'Gujarati', false, 50),
       ('he', 'Hebrew', false, 50),
       ('hi', 'Hindi', false, 50),
       ('hr', 'Croatian', false, 50),
       ('hu', 'Hungarian', false, 50),
       ('id', 'Indonesian', false, 50),
       ('is', 'Icelandic', false, 50),
       ('it', 'Italian', false, 50),
       ('ja', 'Japanese', false, 20),
       ('ko', 'Korean', false, 30),
       ('lt', 'Lithuanian', false, 50),
       ('lv', 'Latvian', false, 50),
       ('mk', 'Macedonian', false, 50),
       ('mn', 'Mongolia', false, 50),
       ('ms', 'Malay', false, 50),
       ('nb', 'Norwegian (Bokmål)', false, 50),
       ('nl', 'Dutch', false, 50),
       ('no', 'Norwegian', false, 50),
       ('pl', 'Polish', false, 50),
       ('pt', 'Portugese', false, 50),
       ('rm', 'Romansh', false, 50),
       ('ro', 'Romanian', false, 50),
       ('ru', 'Russian', false, 30),
       ('sk', 'Slovak', false, 50),
       ('sl', 'Slovenian', false, 50),
       ('sq', 'Albanian', false, 50),
       ('sr', 'Serbian', false, 50),
       ('sv', 'Swedish', false, 50),
       ('ta', 'Tamil', false, 50),
       ('te', 'Telugu', false, 50),
       ('th', 'Thai', false, 50),
       ('tg', 'Tagalog', false, 50),
       ('tr', 'Turkish', false, 50),
       ('uk', 'Ukrainian', false, 50),
       ('ur', 'Urdu', false, 50),
       ('vi', 'Vietnamese', false, 50),
       ('zh', 'Chinese', false, 50);

INSERT INTO "Locale" ("code", "language_code", "is_available", "name")
VALUES ('ar_AE', 'ar', false, 'United Arab Emirates'),
       ('ar_BH', 'ar', false, 'Bahrain'),
       ('ar_DZ', 'ar', false, 'Algeria'),
       ('ar_EG', 'ar', false, 'Egypt'),
       ('ar_IN', 'ar', false, 'India'),
       ('ar_IQ', 'ar', false, 'Iraq'),
       ('ar_JO', 'ar', false, 'Jordan'),
       ('ar_KW', 'ar', false, 'Kuwait'),
       ('ar_LB', 'ar', false, 'Lebanon'),
       ('ar_LY', 'ar', false, 'Libya'),
       ('ar_MA', 'ar', false, 'Morocco'),
       ('ar_OM', 'ar', false, 'Oman'),
       ('ar_QA', 'ar', false, 'Qatar'),
       ('ar_SA', 'ar', false, 'Saudi Arabia'),
       ('ar_SD', 'ar', false, 'Sudan'),
       ('ar_SY', 'ar', false, 'Syria'),
       ('ar_TN', 'ar', false, 'Tunisia'),
       ('ar_YE', 'ar', false, 'Yemen'),
       ('be_BY', 'be', false, 'Belarus'),
       ('bg_BG', 'bg', false, 'Bulgaria'),
       ('ca_ES', 'ca', false, 'Spain'),
       ('cs_CZ', 'cs', false, 'Czech Republic'),
       ('da_DK', 'da', false, 'Denmark'),
       ('de_AT', 'de', false, 'Austria'),
       ('de_BE', 'de', false, 'Belgium'),
       ('de_CH', 'de', false, 'Switzerland'),
       ('de_DE', 'de', false, 'Germany'),
       ('de_LU', 'de', false, 'Luxembourg'),
       ('el_GR', 'el', false, 'Greece'),
       ('en_AU', 'en', false, 'Australia'),
       ('en_CA', 'en', false, 'Canada'),
       ('en_GB', 'en', true, 'United Kingdom'),
       ('en_IN', 'en', false, 'India'),
       ('en_NZ', 'en', false, 'New Zealand'),
       ('en_PH', 'en', false, 'Philippines'),
       ('en_US', 'en', true, 'United States'),
       ('en_ZA', 'en', false, 'South Africa'),
       ('en_ZW', 'en', false, 'Zimbabwe'),
       ('es_AR', 'es', false, 'Argentina'),
       ('es_BO', 'es', false, 'Bolivia'),
       ('es_CL', 'es', false, 'Chile'),
       ('es_CO', 'es', false, 'Colombia'),
       ('es_CR', 'es', false, 'Costa Rica'),
       ('es_DO', 'es', false, 'Dominican Republic'),
       ('es_EC', 'es', false, 'Ecuador'),
       ('es_ES', 'es', false, 'Spain'),
       ('es_GT', 'es', false, 'Guatemala'),
       ('es_HN', 'es', false, 'Honduras'),
       ('es_MX', 'es', false, 'Mexico'),
       ('es_NI', 'es', false, 'Nicaragua'),
       ('es_PA', 'es', false, 'Panama'),
       ('es_PE', 'es', false, 'Peru'),
       ('es_PR', 'es', false, 'Puerto Rico'),
       ('es_PY', 'es', false, 'Paraguay'),
       ('es_SV', 'es', false, 'El Salvador'),
       ('es_US', 'es', false, 'United States'),
       ('es_UY', 'es', false, 'Uruguay'),
       ('es_VE', 'es', false, 'Venezuela'),
       ('et_EE', 'et', false, 'Estonia'),
       ('eu_ES', 'eu', false, 'Basque'),
       ('fi_FI', 'fi', false, 'Finland'),
       ('fo_FO', 'fo', false, 'Faroe Islands'),
       ('fr_BE', 'fr', false, 'Belgium'),
       ('fr_CA', 'fr', false, 'Canada'),
       ('fr_CH', 'fr', false, 'Switzerland'),
       ('fr_FR', 'fr', false, 'France'),
       ('fr_LU', 'fr', false, 'Luxembourg'),
       ('gl_ES', 'gl', false, 'Spain'),
       ('gu_IN', 'gu', false, 'India'),
       ('he_IL', 'he', false, 'Israel'),
       ('hi_IN', 'hi', false, 'India'),
       ('hr_HR', 'hr', false, 'Croatia'),
       ('hu_HU', 'hu', false, 'Hungary'),
       ('id_ID', 'id', false, 'Indonesia'),
       ('is_IS', 'is', false, 'Iceland'),
       ('it_CH', 'it', false, 'Switzerland'),
       ('it_IT', 'it', false, 'Italy'),
       ('ja_JP', 'ja', false, 'Japan'),
       ('ko_KR', 'ko', false, 'Republic of Korea'),
       ('lt_LT', 'lt', false, 'Lithuania'),
       ('lv_LV', 'lv', false, 'Latvia'),
       ('mk_MK', 'mk', false, 'FYROM'),
       ('mn_MN', 'mn', false, 'Mongolian'),
       ('ms_MY', 'ms', false, 'Malaysia'),
       ('nb_NO', 'nb', false, 'Norway'),
       ('nl_BE', 'nl', false, 'Belgium'),
       ('nl_NL', 'nl', false, 'The Netherlands'),
       ('no_NO', 'no', false, 'Norway'),
       ('pl_PL', 'pl', false, 'Poland'),
       ('pt_BR', 'pt', false, 'Brazil'),
       ('pt_PT', 'pt', false, 'Portugal'),
       ('rm_CH', 'rm', false, 'Switzerland'),
       ('ro_RO', 'ro', false, 'Romania'),
       ('ru_RU', 'ru', false, 'Russia'),
       ('ru_UA', 'ru', false, 'Ukraine'),
       ('sk_SK', 'sk', false, 'Slovakia'),
       ('sl_SI', 'sl', false, 'Slovenia'),
       ('sq_AL', 'sq', false, 'Albania'),
       ('sr_RS', 'sr', false, 'Yugoslavia'),
       ('sv_FI', 'sv', false, 'Finland'),
       ('sv_SE', 'sv', false, 'Sweden'),
       ('ta_IN', 'ta', false, 'India'),
       ('te_IN', 'te', false, 'India'),
       ('th_TH', 'th', false, 'Thailand'),
       ('tr_TR', 'tr', false, 'Turkey'),
       ('uk_UA', 'uk', false, 'Ukraine'),
       ('ur_PK', 'ur', false, 'Pakistan'),
       ('vi_VN', 'vi', false, 'Viet Nam'),
       ('zh_CN', 'zh', false, 'China'),
       ('zh_HK', 'zh', false, 'Hong Kong'),
       ('zh_TW', 'zh', false, 'Taiwan Province of China');

UPDATE "Language"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Locale"
   SET "created_at" = CURRENT_DATE,
       "updated_at" = CURRENT_DATE;

UPDATE "Locale"
   SET "is_default" = true
 WHERE "code" = 'en_US';

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_language_before_update ON "Language";
CREATE TRIGGER trg_language_before_update
  BEFORE UPDATE ON "Language"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_language_before_delete ON "Language";
CREATE OR REPLACE RULE rule_language_before_delete AS
    ON DELETE TO "Language"
    DO INSTEAD
       UPDATE "Language" SET "is_available" = false, "is_deleted" = true WHERE "Language"."code" = OLD."code";

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_locale_before_update ON "Locale";
CREATE TRIGGER trg_locale_before_update
  BEFORE UPDATE ON "Locale"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_locale_before_delete ON "Locale";
CREATE OR REPLACE RULE rule_locale_before_delete AS
    ON DELETE TO "Locale"
    DO INSTEAD
       UPDATE "Locale" SET "is_available" = false, "is_deleted" = true WHERE "Locale"."code" = OLD."code";

/** ************************************************************************* *
 *  Create Sequence (Account)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Account";
CREATE TABLE IF NOT EXISTS "Account" (
    "id"            serial                          ,
    "login"         varchar(160) UNIQUE NOT NULL    ,
    "password"      varchar(192)        NOT NULL    DEFAULT '',

    "display_name"  varchar(120)        NOT NULL    DEFAULT '',
    "last_name"     varchar(50)             NULL    ,
    "first_name"    varchar(50)             NULL    ,

    "email"         varchar(160) UNIQUE     NULL    CHECK ("email" LIKE '%@%.%'),
    "locale_code"   varchar(6)          NOT NULL    DEFAULT 'en_US',
    "timezone"      varchar(64)         NOT NULL    DEFAULT 'UTC',

    "type"          varchar(64)         NOT NULL    DEFAULT 'account.normal',
    "guid"          char(36)     UNIQUE NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("locale_code") REFERENCES "Locale" ("code")
);
CREATE INDEX idx_account_main ON "Account" ("login");
CREATE INDEX idx_account_guid ON "Account" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_account_before_insert ON "Account";
CREATE TRIGGER trg_account_before_insert
  BEFORE INSERT ON "Account"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_account_before_update ON "Account";
CREATE TRIGGER trg_account_before_update
  BEFORE UPDATE ON "Account"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_account_before_delete ON "Account";
CREATE OR REPLACE RULE rule_account_before_delete AS
    ON DELETE TO "Account"
    DO INSTEAD
       UPDATE "Account"
          SET "is_deleted" = true,
              "type" = 'account.expired'
        WHERE "Account"."id" = OLD."id";

DROP TABLE IF EXISTS "AccountMeta";
CREATE TABLE IF NOT EXISTS "AccountMeta" (
    "account_id"    integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("account_id", "key"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_acctmeta_main ON "AccountMeta" ("account_id", "is_deleted");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_accountmeta_before_insert ON "AccountMeta";
CREATE TRIGGER trg_accountmeta_before_insert
  BEFORE INSERT ON "AccountMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_accountmeta_before_update ON "AccountMeta";
CREATE TRIGGER trg_accountmeta_before_update
  BEFORE UPDATE ON "AccountMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_accountmeta_before_delete ON "AccountMeta";
CREATE OR REPLACE RULE rule_accountmeta_before_delete AS
    ON DELETE TO "AccountMeta"
    DO INSTEAD
       UPDATE "AccountMeta" SET "is_deleted" = true WHERE "AccountMeta"."account_id" = OLD."account_id" and "AccountMeta"."key" = OLD."key";

/** ************************************************************************* *
 *  Create Sequence (Tokens)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Tokens";
CREATE TABLE IF NOT EXISTS "Tokens" (
    "id"            serial                          ,
    "guid"          varchar(50)         NOT NULL    ,
    "account_id"    integer             NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_token_acct ON "Tokens" ("account_id");
CREATE INDEX idx_token_guid ON "Tokens" ("guid");

DROP TRIGGER IF EXISTS trg_tokens_before_update ON "Tokens";
CREATE TRIGGER trg_tokens_before_update
  BEFORE UPDATE ON "Tokens"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_tokens_before_delete ON "Tokens";
CREATE OR REPLACE RULE rule_tokens_before_delete AS
    ON DELETE TO "Tokens"
    DO INSTEAD
       UPDATE "Tokens" SET "is_deleted" = true WHERE "Tokens"."id" = OLD."id";

/** ************************************************************************* *
 *  Create Sequence (Tags)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Tag";
CREATE TABLE IF NOT EXISTS "Tag" (
    "id"            serial                          ,
    "account_id"    integer             NOT NULL    ,

    "key"           varchar(256)        NOT NULL    ,
    "name"          varchar(256)        NOT NULL    ,
    "summary"       varchar(2048)           NULL    ,

    "guid"          char(36)     UNIQUE NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_tag_acct ON "Tag" ("account_id");
CREATE INDEX idx_tag_guid ON "Tag" ("guid");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_tag_before_insert ON "Tag";
CREATE TRIGGER trg_tag_before_insert
  BEFORE INSERT ON "Tag"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_tag_before_update ON "Tag";
CREATE TRIGGER trg_tag_before_update
  BEFORE UPDATE ON "Tag"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_tag_before_delete ON "Tag";
CREATE OR REPLACE RULE rule_tag_before_delete AS
    ON DELETE TO "Tag"
    DO INSTEAD
       UPDATE "Tag" SET "is_deleted" = true WHERE "Tag"."id" = OLD."id";

/** ************************************************************************* *
 *  Create Sequence (Note)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "Note";
CREATE TABLE IF NOT EXISTS "Note" (
    "id"            serial                          ,
    "account_id"    integer             NOT NULL    ,
    "type"          varchar(64)         NOT NULL    ,

    "title"         varchar(1024)           NULL    ,
    "content"       text                NOT NULL    ,

    "guid"          char(36)     UNIQUE NOT NULL    ,
    "hash"          char(128)           NOT NULL    ,

    "sort_order"    integer             NOT NULL    DEFAULT 5000    CHECK ("sort_order" BETWEEN 0 AND 9999),
    "thread_id"     integer                 NULL    ,
    "parent_id"     integer                 NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_by"    integer             NOT NULL    ,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id"),
    FOREIGN KEY ("thread_id") REFERENCES "Note" ("id"),
    FOREIGN KEY ("parent_id") REFERENCES "Note" ("id"),
    FOREIGN KEY ("updated_by") REFERENCES "Account" ("id")
);
CREATE INDEX idx_note_main ON "Note" ("account_id");
CREATE INDEX idx_note_thrd ON "Note" ("thread_id");
CREATE INDEX idx_note_link ON "Note" ("parent_id");
CREATE INDEX idx_note_guid ON "Note" ("guid");

/* Before INSERT */
CREATE OR REPLACE FUNCTION note_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."hash" = encode(sha512(CAST(CONCAT(COALESCE(NEW."title", '{title}'), REPLACE(NEW."content", '\', '\\'), COALESCE(NEW."type", '{type}'),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."sort_order", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."account_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."thread_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."parent_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."updated_by", 0)), 8)) AS bytea))
                                           , 'hex');
    NEW."guid" = uuid_generate_v4();

    RETURN NEW;
END;
$$;

/*
\\';
*/

DROP TRIGGER IF EXISTS trg_note_before_insert ON "Note";
CREATE TRIGGER trg_note_before_insert
  BEFORE INSERT ON "Note"
    FOR EACH ROW
  EXECUTE PROCEDURE note_before_insert();

/* Before UPDATE */
CREATE OR REPLACE FUNCTION note_before_update()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    IF NEW."guid" <> OLD."guid" THEN
        NEW."guid" = OLD."guid";
    END IF;

    NEW."hash" = encode(sha512(CAST(CONCAT(COALESCE(NEW."title", '{title}'), REPLACE(NEW."content", '\', '\\'), COALESCE(NEW."type", '{type}'),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."sort_order", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."account_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."thread_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."parent_id", 0)), 8),
                                           RIGHT(CONCAT('00000000', COALESCE(NEW."updated_by", 0)), 8)) AS bytea))
                                           , 'hex');

    IF NEW."hash" <> OLD."hash" THEN
        INSERT INTO "NoteHistory" ("note_id", "type", "title", "content", "hash", "sort_order",
                                   "thread_id", "parent_id", "updated_at", "updated_by")
        SELECT OLD."id" as "note_id", OLD."type", OLD."title", OLD."content", OLD."hash", OLD."sort_order",
               OLD."thread_id", OLD."parent_id", OLD."updated_at", OLD."updated_by";
    END IF;

    NEW."updated_at" = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

/*
\\';
*/

DROP TRIGGER IF EXISTS trg_note_before_update ON "Note";
CREATE TRIGGER trg_note_before_update
  BEFORE UPDATE ON "Note"
    FOR EACH ROW
  EXECUTE PROCEDURE note_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_note_before_delete ON "Note";
CREATE OR REPLACE RULE rule_note_before_delete AS
    ON DELETE TO "Note"
    DO INSTEAD
       UPDATE "Note" SET "is_deleted" = true WHERE "Note"."id" = OLD."id";

DROP TABLE IF EXISTS "NoteMeta";
CREATE TABLE IF NOT EXISTS "NoteMeta" (
    "note_id"       integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("note_id", "key"),
    FOREIGN KEY ("note_id") REFERENCES "Note" ("id")
);
CREATE INDEX idx_nmeta_main ON "NoteMeta" ("note_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_notemeta_before_insert ON "NoteMeta";
CREATE TRIGGER trg_notemeta_before_insert
  BEFORE INSERT ON "NoteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_notemeta_before_update ON "NoteMeta";
CREATE TRIGGER trg_notemeta_before_update
  BEFORE UPDATE ON "NoteMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_notemeta_before_delete ON "NoteMeta";
CREATE OR REPLACE RULE rule_notemeta_before_delete AS
    ON DELETE TO "NoteMeta"
    DO INSTEAD
       UPDATE "NoteMeta" SET "is_deleted" = true WHERE "NoteMeta"."note_id" = OLD."note_id" and "NoteMeta"."key" = OLD."key";

DROP TABLE IF EXISTS "NoteTag";
CREATE TABLE IF NOT EXISTS "NoteTag" (
    "note_id"       integer             NOT NULL    ,
    "tag_id"        integer             NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("note_id", "tag_id"),
    FOREIGN KEY ("note_id") REFERENCES "Note" ("id"),
    FOREIGN KEY ("tag_id") REFERENCES "Tag" ("id")
);
CREATE INDEX idx_ntag_main ON "NoteTag" ("note_id");

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_notetag_before_update ON "NoteTag";
CREATE TRIGGER trg_notetag_before_update
  BEFORE UPDATE ON "NoteTag"
    FOR EACH ROW
  EXECUTE PROCEDURE date_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_notetag_before_delete ON "NoteTag";
CREATE OR REPLACE RULE rule_notetag_before_delete AS
    ON DELETE TO "NoteTag"
    DO INSTEAD
       UPDATE "NoteTag" SET "is_deleted" = true WHERE "NoteTag"."note_id" = OLD."note_id" and "NoteTag"."tag_id" = OLD."tag_id";

/** ************************************************************************* *
 *  Create Sequence (History)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "NoteHistory";
CREATE TABLE IF NOT EXISTS "NoteHistory" (
    "id"            serial                          ,
    "note_id"       integer             NOT NULL    ,
    "type"          varchar(64)         NOT NULL    ,

    "title"         varchar(1024)           NULL    ,
    "content"       text                NOT NULL    ,

    "hash"          char(128)           NOT NULL    ,

    "sort_order"    integer             NOT NULL    DEFAULT 5000    CHECK ("sort_order" BETWEEN 0 AND 9999),
    "thread_id"     integer                 NULL    ,
    "parent_id"     integer                 NULL    ,

    "is_deleted"    boolean             NOT NULL    ,
    "updated_at"    timestamp           NOT NULL    ,
    "updated_by"    integer             NOT NULL    ,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("note_id") REFERENCES "Note" ("id"),
    FOREIGN KEY ("updated_by") REFERENCES "Account" ("id")
);
CREATE INDEX idx_notehist_main ON "NoteHistory" ("note_id");

/* NoteHistory Needs to be Immutable */
DROP RULE IF EXISTS rule_notehistory_before_update ON "NoteHistory";
CREATE OR REPLACE RULE rule_notehistory_before_update AS
    ON UPDATE TO "NoteHistory"
    DO INSTEAD NOTHING;

DROP RULE IF EXISTS rule_notehistory_before_delete ON "NoteHistory";
CREATE OR REPLACE RULE rule_notehistory_before_delete AS
    ON DELETE TO "NoteHistory"
    DO INSTEAD NOTHING;

/** ************************************************************************* *
 *  Create Sequence (Files)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "File";
CREATE TABLE IF NOT EXISTS "File" (
    "id"            serial                          ,
    "account_id"    integer             NOT NULL    ,

    "name"          varchar(64)         NOT NULL    ,
    "public_name"   varchar(256)        NOT NULL    ,
    "location"      varchar(256)        NOT NULL    ,
    "bytes"         integer             NOT NULL    CHECK ("bytes" > 0),
    "url"           varchar(512)        NOT NULL    ,

    "mimetype"      varchar(64)             NULL    ,
    "is_active"     boolean             NOT NULL    DEFAULT true,

    "guid"          char(36)     UNIQUE NOT NULL    ,
    "hash"          char(128)           NOT NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("account_id") REFERENCES "Account" ("id")
);
CREATE INDEX idx_file_acct ON "File" ("account_id");
CREATE INDEX idx_file_guid ON "File" ("guid");
CREATE INDEX idx_file_url ON "File" ("url");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_file_before_insert ON "File";
CREATE TRIGGER trg_file_before_insert
  BEFORE INSERT ON "File"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_insert();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_file_before_update ON "File";
CREATE TRIGGER trg_file_before_update
  BEFORE UPDATE ON "File"
    FOR EACH ROW
  EXECUTE PROCEDURE guid_before_update();

/* Before DELETE */
DROP RULE IF EXISTS rule_file_before_delete ON "File";
CREATE OR REPLACE RULE rule_file_before_delete AS
    ON DELETE TO "File"
    DO INSTEAD
       UPDATE "File" SET "is_deleted" = true WHERE "File"."id" = OLD."id";

DROP TABLE IF EXISTS "FileMeta";
CREATE TABLE IF NOT EXISTS "FileMeta" (
    "file_id"       integer             NOT NULL    ,
    "key"           varchar(64)         NOT NULL    ,
    "value"         varchar(2048)       NOT NULL    DEFAULT '',

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("file_id", "key"),
    FOREIGN KEY ("file_id") REFERENCES "File" ("id")
);
CREATE INDEX idx_fmeta_main ON "FileMeta" ("file_id");

/* Before INSERT */
DROP TRIGGER IF EXISTS trg_filemeta_before_insert ON "FileMeta";
CREATE TRIGGER trg_filemeta_before_insert
  BEFORE INSERT ON "FileMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before UPDATE */
DROP TRIGGER IF EXISTS trg_filemeta_before_update ON "FileMeta";
CREATE TRIGGER trg_filemeta_before_update
  BEFORE UPDATE ON "FileMeta"
    FOR EACH ROW
  EXECUTE PROCEDURE meta_before();

/* Before DELETE */
DROP RULE IF EXISTS rule_filemeta_before_delete ON "FileMeta";
CREATE OR REPLACE RULE rule_filemeta_before_delete AS
    ON DELETE TO "FileMeta"
    DO INSTEAD
       UPDATE "FileMeta" SET "is_deleted" = true WHERE "FileMeta"."file_id" = OLD."file_id" and "FileMeta"."key" = OLD."key";

/** ************************************************************************* *
 *  Create Sequence (Statistics)
 ** ************************************************************************* */
DROP TABLE IF EXISTS "UsageStats";
CREATE TABLE IF NOT EXISTS "UsageStats" (
    "id"            serial                          ,
    "token_id"      integer                 NULL    ,

    "http_code"     smallint            NOT NULL    DEFAULT 200     CHECK ("http_code" BETWEEN 100 AND 999),
    "request_type"  varchar(8)          NOT NULL    DEFAULT 'GET',
    "request_uri"   varchar(512)        NOT NULL    ,
    "referrer"      varchar(1024)           NULL    ,

    "is_api"        boolean             NOT NULL    DEFAULT false,
    "is_file"       boolean             NOT NULL    DEFAULT false,

    "event_at"      timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "event_on"      varchar(10)         NOT NULL    ,
    "from_ip"       varchar(64)         NOT NULL    ,

    "agent"         varchar(2048)           NULL    ,
    "platform"      varchar(64)             NULL    ,
    "browser"       varchar(64)         NOT NULL    DEFAULT 'unknown',
    "version"       varchar(64)             NULL    ,

    "seconds"       decimal(16,8)       NOT NULL    DEFAULT 0       CHECK ("seconds" >= 0),
    "sqlops"        smallint            NOT NULL    DEFAULT 0       CHECK ("sqlops" >= 0),
    "message"       varchar(512)            NULL    ,

    "is_deleted"    boolean             NOT NULL    DEFAULT false,
    "created_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    "updated_at"    timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id"),
    FOREIGN KEY ("token_id") REFERENCES "Tokens" ("id")
);

/* Before INSERT */
CREATE OR REPLACE FUNCTION usagestats_before_insert()
  RETURNS TRIGGER
  LANGUAGE PLPGSQL
  AS
$$
BEGIN
    NEW."request_uri" = LOWER(NEW."request_uri");
    NEW."referrer" = CASE WHEN LENGTH(COALESCE(NEW."referrer", '')) > 3 THEN LOWER(NEW."referrer") ELSE NULL END;
    NEW."event_on" = CURRENT_DATE::varchar(10);

    IF NEW."request_uri" LIKE '%/api/%' THEN
        NEW."is_api" = true;
    END IF;

    IF NEW."is_api" = false AND (NEW."request_uri" LIKE '%/file/%' OR NEW."request_uri" LIKE '%/files/%') THEN
        NEW."is_file" = true;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_usagestat_before_insert ON "UsageStats";
CREATE TRIGGER trg_usagestat_before_insert
  BEFORE INSERT ON "UsageStats"
    FOR EACH ROW
  EXECUTE PROCEDURE usagestats_before_insert();
