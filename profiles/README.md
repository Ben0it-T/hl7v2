# HL7v2 profiles

A profile directory describes the messages that can be validated for one HL7 version or one derived profile (for example IHE PAM FR). It is a **resolved copy** of a schema directory (`schemas/json-<v>/`), produced by `scripts/06-create-json-profile.php`:

- restricted to the selected messages, and to the segments and fields they use;
- with every datatype of the schema (a `varies` field, OBX-5, takes the datatype named by OBX-2);
- usages computed;
- table names normalized (`0010`);
- keys in camelCase.

A profile directory is self-contained: it never refers to another profile directory.

> **Transition.** The format described here is generated in `profiles-next/json-<v>/` (not versioned). The current `profiles/json-<v>/` directories keep the previous format until the new validator replaces the current one.

## Files

```
json-<v>/
├── profile.json                        manifest
├── messageType.json                    message type → trigger event → message structure
├── eventDesc.json                      message type → trigger event → description
├── structures/<type>-<event>-<structure>.json   one message structure per message
├── segments.json                       segments and their fields
├── fields.json                         field definitions
├── dataTypes.json                      datatypes and their components
├── hl7tables.json                      table codes (read by the validator)
├── hl7tables-with-description.json     table codes and descriptions (for display)
└── rules.json                          validation rules (maintained by hand)
```

Every file is produced by the pipeline, except `rules.json`. Do not edit the others: change the sources in `schemas/` and run the pipeline again.

The format of `rules.json` is described [below](#rulesjson).

A derived profile is resolved like any other profile: the constraints of its specification are already applied to its files.

## Values

All values are **strings**. An absent value is `""` (not defined by the standard): the key is always present, never `null`.

| Key | Format | Example |
|---|---|---|
| `usage` | `R`, `RE`, `O`, `C`, `CE`, `X`, `B`, `W` | `"R"` |
| `min` | `0` or a positive integer, without leading zero | `"0"` |
| `max` | a positive integer, `*` (unbounded), or `0` | `"*"` |
| `dataType` | datatype name | `"CX"` |
| `maxLength` | a positive integer, or `""` | `"250"` |
| `table` | 4 digits for an HL7 table, an identifier for another table, or `""` | `"0203"` |
| `item` | 5 digits, or `""` | `"00106"` |
| `longName` | text | `"Patient Identifier List"` |
| `chapter` | section of the standard, or `""` | `"3.4.2.3"` |

The position of a field or a component is given by its identifier: `PID.3` is the third field of PID, `CX.1` the first component of CX. Lists are always in position order.

In a derived profile, a value changed or added by its specification has a `source` key: the document and section that require it. It is informational and does not change validation.

## profile.json

```json
{
  "formatVersion": "1",
  "hl7Version": "2.5",
  "name": "IHE PAM FR 2.11.2",
  "base": "json-2.5",
  "generatedFrom": "schemas/json-2.5-IHEPAMFR",
  "documents": {
    "PAMFR": "IHE PAM FR 2.11.2 (IHE France, PAM National Extension)"
  }
}
```

`name`, `hl7Version` and `base` come from `schemas/json-<v>/profile.json`. `base` is `""` for an HL7 profile (not derived). `documents` lists the documents cited by the `source` keys (`{}` for an HL7 profile).

## messageType.json

```json
{
  "ADT": {
    "A01": "ADT_A01",
    "A04": "ADT_A01"
  }
}
```

The structure of a message is in `structures/<type>-<event>-<structure>.json`, for example `structures/ADT-A04-ADT_A01.json`.

## eventDesc.json

```json
{
  "ADT": {
    "A01": "Admit/visit notification"
  }
}
```

It has exactly the messages of `messageType.json`.

## structures/

One file per message. Each group is a key; the root group is named after the message structure.

```json
{
  "ADT_A01": {
    "elements": [
      { "segment": "MSH", "usage": "R", "min": "1", "max": "1" },
      { "group": "PROCEDURE", "usage": "O", "min": "0", "max": "*" }
    ]
  },
  "PROCEDURE": {
    "elements": [
      { "segment": "PR1", "usage": "R", "min": "1", "max": "1" },
      { "segment": "ROL", "usage": "O", "min": "0", "max": "*" }
    ]
  }
}
```

A choice group has `"choice": true`: exactly one of its elements is expected. Without `choice`, elements form a sequence.

A segment removed from a message by a derived profile does not appear in the message structure at all. A segment that remains in the structure but must not be sent has `"usage": "X"`.

A segment element may carry `fields`: the field definitions that differ for this message from `segments.json`. To get the definition of a field in a message, take the value of the structure element when present, otherwise the value of `segments.json`.

```json
{ "segment": "PID", "usage": "R", "min": "1", "max": "1",
  "fields": {
    "PID.7": { "usage": "RE", "min": "0", "max": "1", "source": "PAMFR 6.6.4" }
  }
}
```

## segments.json

```json
{
  "PID": {
    "longName": "Patient Identification",
    "chapter": "3.4.2",
    "fields": [
      { "field": "PID.1", "usage": "O", "min": "0", "max": "1" },
      { "field": "PID.3", "usage": "R", "min": "1", "max": "*" }
    ]
  }
}
```

## fields.json

```json
{
  "PID.3": {
    "item": "00106",
    "dataType": "CX",
    "maxLength": "250",
    "table": "",
    "longName": "Patient Identifier List",
    "chapter": "3.4.2.3"
  }
}
```

## dataTypes.json

A composite datatype lists its components. A primitive datatype has `"primitive": true`.

```json
{
  "CX": {
    "components": [
      { "component": "CX.1", "dataType": "ST", "usage": "R", "min": "1", "max": "1",
        "maxLength": "15", "table": "", "longName": "ID Number" },
      { "component": "CX.4", "dataType": "HD", "usage": "O", "min": "0", "max": "1",
        "maxLength": "227", "table": "0363", "longName": "Assigning Authority" }
    ]
  },
  "ST": {
    "primitive": true
  }
}
```

The sub-components of a component are the components of its datatype (`CX.4` → `HD`).

## hl7tables.json and hl7tables-with-description.json

```json
{
  "0001": { "id": "0001", "name": "Administrative Sex", "type": "User", "codes": ["A", "F", "M"] }
}
```

```json
{
  "0001": { "id": "0001", "name": "Administrative Sex", "type": "User",
    "codes": [ { "code": "A", "description": "Ambiguous" } ] }
}
```

Both files are produced from the hand-maintained `schemas/json-<v>/hl7tables.json`.

## rules.json

The validation rules of the profile: the severity of each finding type, the tables closed by the profile, and the tables that accept local codes. It is maintained by hand: `scripts/06-create-json-profile.php` keeps it as it is. The validator applies no rule of its own: every rule comes from this file, which must be complete.

```json
{
  "source": "HL7 v2: usage, cardinality, data types, lengths and tables",
  "comment": "Usage RE and CE behave as O and C. Values outside a User table give a Warning: HL7 only suggests the values of a User table.",
  "severities": {
    "requiredAbsent": "Error",
    "zSegmentNotDefined": "Warning",
    "backwardPresent": "Info"
  },
  "closedTables": [],
  "localCodeTables": ["0003", "0076", "0354"]
}
```

(`severities` is shortened here: it must list every finding type.)

| Key | Required | Content |
|---|---|---|
| `source` | no | Where the rules come from (text) |
| `comment` | no | Free text |
| `severities` | yes | The severity of each finding type: `Error`, `Warning` or `Info`. Every finding type below must be present, and no other key. Only Errors make a message invalid. |
| `closedTables` | yes | Ids of the tables closed by the profile (list, may be empty): a value outside one of them is a `tableClosed` finding, whatever the type of the table (HL7 or User). |
| `localCodeTables` | yes | Ids of the tables that accept local codes (list, may be empty): a value made of `Z` then upper-case letters, digits or `_` (`Z01`) is accepted in them, except in a CNE. |

An unknown key, a missing or unknown finding type, an unknown severity or a malformed list of tables is refused when the profile directory is opened.

Finding types, with their severity in the profiles of this repository (the same in every profile):

| Finding type | Severity | Meaning |
|---|---|---|
| `requiredAbsent` | Error | Required element (R) absent |
| `notAllowedPresent` | Error | Element not allowed (X) present |
| `cardinality` | Error | Minimum or maximum cardinality not respected |
| `segmentNotExpected` | Error | Segment known by the profile, but not expected at its position |
| `segmentNotDefined` | Error | Segment unknown to the profile (not a Z segment) |
| `zSegmentNotDefined` | Warning | Z segment unknown to the profile |
| `elementInExcess` | Error | Field, component or sub-component in excess |
| `escapeMalformed` | Error | Escape sequence malformed (unclosed, unknown code, invalid data) |
| `escapeNotAllowed` | Error | Escape sequence not allowed in the data type (`\H\` in an NM) |
| `variesUndetermined` | Warning | Data type of a varies element (OBX-5) undetermined: OBX-2 absent or unknown |
| `withdrawnPresent` | Warning | Withdrawn element (W) present |
| `backwardPresent` | Info | Backward compatible element (B) present |
| `conditionalPresent` | Info | Conditional element (C, CE) present, condition not evaluated |
| `datatype` | Error | Value not valid for its data type |
| `length` | Error | Value longer than its maximum length |
| `tableHL7` | Error | Value not in an HL7 table |
| `tableUser` | Warning | Value not in a User table, or in the table of a CWE (local values allowed) |
| `tableClosed` | Error | Value not in a table closed by the profile, or in the table of a CNE (no local value) |
| `tableUnavailable` | Info | Table absent from the profile, or without codes: no check done |
| `noDatatypeValidator` | Info | No validator for the data type: no check done |
| `parseIssue` | Error | Problem met while parsing (character set, invalid bytes) |

Closed tables: none in the HL7 profiles; 50 in IHE PAM FR, the tables whose values are given by PAM FR or DTFR (DTFR: no list can be extended without a new revision), except the lists declared non-limitative by PAM FR. Local codes: tables 0003 (event type), 0076 (message type) and 0354 (message structure), in every profile.
