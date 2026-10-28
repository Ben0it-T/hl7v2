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

Generate XML profiles (`HL7v2xConformanceProfile`) from a JSON profile directory created by 06: one file per message. The script replaces the XML files of the output directory. The `source` keys are not written.

The 2002 profile schema does not allow some JSON values: usage B is written O, usage W is written X, an element `[0..0]` is written `Min="0" Max="1"`, and an empty `Length` or `ItemNo` is not written.

With `--xsd`, each XML profile is validated against the profile schema (`HL7MessageProfileSchema.xsd`, not versioned) before it is written.

```bash
php 07-create-xml-profile.php \
    --profile-dir=<directory> \
    --xml-dir=<directory> \
    [--xsd=<file>]
```

Example:

```bash
php 07-create-xml-profile.php \
    --profile-dir=../profiles-next/json-2.5-IHEPAMFR \
    --xml-dir=../profiles-next/xml-2.5-IHEPAMFR \
    --xsd=../schemas/hl7v2xsd/profile/HL7MessageProfileSchema.xsd
```
