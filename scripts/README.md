# HL7v2 scripts

Generate HL7 v2.x JSON schemas and message profiles from HL7 v2.x XSD schemas.

Source HL7 Version 2.x XSD schemas: [HL7 Version 2 Product Suite](https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185)

## Prerequisites

Download:

- HL7 v2.x XSD schemas
- HL7 Appendix A content

from the HL7 Version 2 Product Suite.

## Generate JSON schemas

Scripts 01 to 05 use command-line parameters.

### 01 - Clean XSD schemas

Normalize and format HL7 XSD schemas.

```bash
php 01-clean-xsd-schemas.php \
    --input-dir=<directory>
```

### 02 - Create JSON schemas

Generate JSON schemas from HL7 XSD schemas.

```bash
php 02-xsd-schemas-to-json-schemas.php \
    --input-dir=<directory> \
    --output-dir=<directory>
```

### 03 - Update JSON schemas from previous HL7 versions

Apply corrections and enrichments using older HL7 schemas.
_Only from HL7 v2.3.1, v2.4, v2.5, v2.5.1 messaging schemas to Sun_HL7v2xsd._

```bash
php 03-json-schemas-update-from-hl7-org-to-sun.php \
    --source-dir=<directory> \
    --target-dir=<directory>
```

### 04 - Update JSON schemas from Appendix A

Import message and event metadata from Appendix A.

```bash
php 04-json-schemas-update-from-appendix-a.php \
    --appendix-dir=<directory> \
    --json-dir=<directory>
```

### 05 - Generate derived schemas

Generate a derived schema (e.g. IHE PAM FR) from a base schema (e.g. HL7 2.5).

The profile directory holds the sources of the derived schema, maintained by hand: `messageType.json`, `eventDesc.json`, `extensions.json` (Z segments), `constraints.json` (differences with the base schema, each with its source) and `deviations.json` (accepted deviations).

The script writes `segments.json`, `fields.json`, `dataTypes.json` and `structures/` in the profile directory, and stops on any invalid source entry.

```bash
php 05-derive-schemas.php \
    --base-dir=<directory> \
    --profile-dir=<directory>
```

## Generate profiles

Script 07 uses a dedicated configuration file instead of command-line parameters.

### 06 - Create JSON profiles

Generate a JSON profile directory from a JSON schema directory. The format is described in `profiles/README.md`.

The profile is restricted to the selected messages and to the segments, fields and datatypes they use. Every table of `hl7tables.json` is kept. A table cited by a field or a component but absent from `hl7tables.json` is reported as a warning.

The script replaces the generated files of the profile directory and keeps `rules.json`.

```bash
php 06-create-json-profile.php \
    --schema-dir=<directory> \
    --profile-dir=<directory> \
    [--types=<type,...>] \
    [--ignore-events=<event,...>]
```

Without `--types`, every message type of `messageType.json` is generated.

Examples:

```bash
php 06-create-json-profile.php \
    --schema-dir=../schemas/json-2.5 \
    --profile-dir=../profiles-next/json-2.5 \
    --types=ACK,ADT,SIU

php 06-create-json-profile.php \
    --schema-dir=../schemas/json-2.5-IHEPAMFR \
    --profile-dir=../profiles-next/json-2.5-IHEPAMFR
```

### 07 - Create XML profiles

Generate XML HL7v2xConformanceProfile profiles from JSON schemas.

Configuration file:

```text
07-create-xml-profile.config.php
```

If missing, it is automatically created from:

```text
07-create-xml-profile.config.dist.php
```

Run:

```bash
php 07-create-xml-profile.php
```

## IHE PAM FR conditional predicates

For scripts 06 and 07, enable:

```php
'fieldsConstraints' => true,
```

to apply IHE PAM FR conditional predicates to generated profiles.

