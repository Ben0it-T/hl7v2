# HL7v2 scripts

Generate HL7 v2.x JSON schemas and message profiles from HL7 v2.x XSD schemas.

```
schemas/hl7v2xsd/<v> ─┐
schemas/hl7v2org/<v> ─┼─ 01 ─ 02 ─ 03 ─ 04 ─► schemas/json-<v> ─ 06 ─► profiles-next/json-<v> ─ 07 ─► profiles-next/xml-<v>
schemas/hl7v2std/<v> ─┘

schemas/json-2.5 ─ 05 ─► schemas/json-2.5-IHEPAMFR ─ 06 ─► profiles-next/json-2.5-IHEPAMFR ─ 07 ─► profiles-next/xml-2.5-IHEPAMFR
```

Run the scripts from the `scripts/` directory. **Always run the complete chain from 02**: 04 replaces a value only when the standard extract gives one, it cannot erase a value kept from a previous run.

> **Transition.** The profiles are generated in `profiles-next/` (not versioned). The `profiles/json-<v>/` directories keep the previous format until the new validator replaces the current one; they are no longer generated.

## Sources

### Reference sources (not versioned)

The HL7 documents and schemas are **not versioned** and stay outside the repository. Obtain them from the [HL7 Version 2 Product Suite](https://www.hl7.org/implement/standards/product_brief.cfm?product_id=185).

For each version `<v>` (2.3, 2.3.1, 2.4, 2.5):

| Directory                   | Content |
|-----------------------------|---------|
| `schemas/hl7v2org/<v>/`     | HL7 v2.x XSD schemas published by HL7. None for 2.3. |
| `schemas/hl7v2xsd/<v>/`     | Sun HL7 v2.x XSD schemas |
| `schemas/hl7v2std/<v>/`     | **Standard extract**: CSV tables built by hand from the normative HL7 PDFs (appendices and chapters) and cross-checked: `data-element-names.csv`, `datatypes.csv`, `segments.csv`. |
| `schemas/hl7v2xsd/profile/` | `HL7MessageProfileSchema.xsd`: HL7 v2.x conformance profile schema (2002), used by 07 to validate the XML profiles |

### Hand-maintained sources (versioned)

For each schema directory `schemas/json-<v>/` (HL7 versions and derived profiles):

| File               | Content |
|--------------------|---------|
| `profile.json`     | Name, HL7 version and base profile (`""` for an HL7 version), copied into the profile manifest by 06 |
| `messageType.json` | Message type → trigger event → message structure. In a derived schema, the value names the structure file of the message (`ADT-A04-ADT_A01`). |
| `eventDesc.json`   | Message type → trigger event → description, with exactly the messages of `messageType.json` |
| `hl7tables.json`   | HL7 tables with their codes and descriptions, maintained by hand from several cross-checked sources. 06 produces the two table files of a profile directory from it. |

HL7 versions only:

| File           | Content |
|----------------|---------|
| `patches.json` | Schema patches (optional): corrections the reference sources cannot produce (for example removing a table). A patch is a partial schema (`dataTypes`, `fields`, `segments`) merged key by key; lists are replaced as a whole and a `null` value removes the key. Applied by 04. |

Derived schemas only:

| File               | Content |
|--------------------|---------|
| `extensions.json`  | Segments and fields added by the specification (Z segments) |
| `constraints.json` | Differences with the base schema, each with its source: datatypes, segments, fields, groups of segments to insert, structure operations and field definitions per message |
| `deviations.json`  | Accepted deviations: differences with the base schema that are not restrictions, kept because the specification requires them |

## Generate JSON schemas

### 01 - Clean XSD schemas

Normalize and indent the XSD schemas. **Files are rewritten in place.**

```bash
php 01-xsd-schemas-cleaner.php --input-dir=../schemas/hl7v2org/<v>
php 01-xsd-schemas-cleaner.php --input-dir=../schemas/hl7v2xsd/<v>
```

### 02 - Create JSON schemas

Convert both XSD sets to JSON schemas (`dataTypes.json`, `fields.json`, `segments.json`, `structures/`).

```bash
php 02-xsd-schemas-to-json-schemas.php --input-dir=../schemas/hl7v2org/<v> --output-dir=../schemas/json-<v>-org
php 02-xsd-schemas-to-json-schemas.php --input-dir=../schemas/hl7v2xsd/<v> --output-dir=../schemas/json-<v>-sun
```

### 03 - Update Sun JSON schemas from HL7 JSON schemas

Apply the cardinalities and non-empty attributes of the HL7 schemas (`-org`) to the Sun schemas (`-sun`). **Target files are rewritten in place.**
Skip this step for 2.3: there is no HL7 XSD source for this version.

```bash
php 03-json-schemas-update-from-hl7-org-to-sun.php \
    --source-dir=../schemas/json-<v>-org \
    --target-dir=../schemas/json-<v>-sun
```

### 04 - Update from the standard extract, apply patches and publish

`schemas/json-<v>/` must already exist and contain `messageType.json` and `eventDesc.json`.

```bash
php 04-json-schemas-update-from-standard.php \
    --standard-dir=../schemas/hl7v2std/<v> \
    --json-dir=../schemas/json-<v>-sun \
    --publish-dir=../schemas/json-<v>
```

The script:

1. updates fields, segments and datatypes metadata from the standard extract. Values are taken as they are. Rules:
   - a `varies` datatype is kept as `varies`;
   - table 0 (or no table) means no table: an existing table is kept (use `patches.json` to remove it);
   - the usage sets the cardinality it implies: R → `minOccurs` 1; O, B → `minOccurs` 0 (HL7 v2.5, 2.5.3.4 and 2.12.6.4); X, W → `[0..0]`.
2. with `--publish-dir`:
   - applies `schemas/json-<v>/patches.json`, if any;
   - replaces `dataTypes.json`, `fields.json`, `segments.json` and `structures/` of `schemas/json-<v>/`. All other files are left untouched.

All checks run before anything is written. Without `--publish-dir`, only `--json-dir` is updated.

Review the result with `git diff ../schemas/json-<v>`.

### 05 - Generate derived schemas

Generate a derived schema (e.g. IHE PAM FR) from a base schema (e.g. HL7 2.5).

The profile directory holds the sources of the derived schema. The script writes `segments.json`, `fields.json`, `dataTypes.json` and `structures/` in the profile directory, and stops on any invalid source entry: unknown key or document, inconsistent usage and cardinality, path that does not select exactly one element, field defined twice for a message.

```bash
php 05-derive-schemas.php \
    --base-dir=../schemas/json-2.5 \
    --profile-dir=../schemas/json-2.5-IHEPAMFR
```

## Generate profiles

### 06 - Create JSON profiles

Generate a JSON profile directory from a JSON schema directory. The format is described in `profiles/README.md`.

The profile is restricted to the selected messages and to the segments, fields and datatypes they use. Every table of `hl7tables.json` is kept. A table cited by a field or a component but absent from `hl7tables.json` is reported as a warning, and so is a usage that is not consistent with its cardinality (for example O with a minimum of 1).

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
    --profile-dir=../profiles-next/json-2.5

php 06-create-json-profile.php \
    --schema-dir=../schemas/json-2.5-IHEPAMFR \
    --profile-dir=../profiles-next/json-2.5-IHEPAMFR
```

### 07 - Create XML profiles

Generate XML profiles (`HL7v2xConformanceProfile`) from a JSON profile directory created by 06: one file per message. The script replaces the XML files of the output directory. The `source` keys are not written.

The 2002 profile schema does not allow some JSON values: usage B is written O, usage W is written X, an element `[0..0]` is written `Min="0" Max="1"`, and an empty `Length` or `ItemNo` is not written.

With `--xsd`, each XML profile is validated against the profile schema before it is written.

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

## Complete run

For each HL7 version `<v>` (skip 03 and the `hl7v2org` lines for 2.3):

```bash
php 01-xsd-schemas-cleaner.php --input-dir=../schemas/hl7v2org/<v>
php 01-xsd-schemas-cleaner.php --input-dir=../schemas/hl7v2xsd/<v>
php 02-xsd-schemas-to-json-schemas.php --input-dir=../schemas/hl7v2org/<v> --output-dir=../schemas/json-<v>-org
php 02-xsd-schemas-to-json-schemas.php --input-dir=../schemas/hl7v2xsd/<v> --output-dir=../schemas/json-<v>-sun
php 03-json-schemas-update-from-hl7-org-to-sun.php --source-dir=../schemas/json-<v>-org --target-dir=../schemas/json-<v>-sun
php 04-json-schemas-update-from-standard.php --standard-dir=../schemas/hl7v2std/<v> --json-dir=../schemas/json-<v>-sun --publish-dir=../schemas/json-<v>
php 06-create-json-profile.php --schema-dir=../schemas/json-<v> --profile-dir=../profiles-next/json-<v>
php 07-create-xml-profile.php --profile-dir=../profiles-next/json-<v> --xml-dir=../profiles-next/xml-<v> --xsd=../schemas/hl7v2xsd/profile/HL7MessageProfileSchema.xsd
```

Then, after 2.5, the derived schema IHE PAM FR:

```bash
php 05-derive-schemas.php --base-dir=../schemas/json-2.5 --profile-dir=../schemas/json-2.5-IHEPAMFR
php 06-create-json-profile.php --schema-dir=../schemas/json-2.5-IHEPAMFR --profile-dir=../profiles-next/json-2.5-IHEPAMFR
php 07-create-xml-profile.php --profile-dir=../profiles-next/json-2.5-IHEPAMFR --xml-dir=../profiles-next/xml-2.5-IHEPAMFR --xsd=../schemas/hl7v2xsd/profile/HL7MessageProfileSchema.xsd
```
