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
php 03-json-schemas-update-from-old-schemas.php \
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

### 05 - Update schemas for IHE PAM FR

Generate IHE PAM FR specific schemas from HL7 2.5 schemas.

```bash
php 05-update-schemas-to-ihe-pam-fr.php \
    --input-dir=<directory> \
    --output-dir=<directory>
```

## Generate profiles

Scripts 06 and 07 use dedicated configuration files instead of command-line parameters.

### 06 - Create JSON profiles

Generate JSON profiles from JSON schemas.

Configuration file:

```text
06-create-json-profile.config.php
```

If missing, it is automatically created from:

```text
06-create-json-profile.config.dist.php
```

Run:

```bash
php 06-create-json-profile.php
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

