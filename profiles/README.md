# HL7v2 profiles

A profile directory describes the messages that can be validated for one HL7 version or one derived profile (for example IHE PAM FR). It is a **resolved copy** of a schema directory (`schemas/json-<v>/`), produced by `scripts/06-create-json-profile.php`:

- restricted to the supported messages;
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
├── structures/<type>-<event>-<structure>.json   one message structure per message
├── segments.json                       segments and their fields
├── fields.json                         field definitions
├── dataTypes.json                      datatypes and their components
├── hl7tables.json                      table codes (read by the validator)
├── hl7tables-with-description.json     table codes and descriptions (for display)
├── constraints.json                    constraints of a derived profile (derived profiles only)
└── rules.json                          validation rules (maintained by hand)
```

Every file is produced by the pipeline, except `rules.json`. Do not edit the others: change the sources in `schemas/` and run the pipeline again.

The format of `constraints.json` and `rules.json` is not defined yet.

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

## profile.json

```json
{
  "formatVersion": "1",
  "hl7Version": "2.5",
  "name": "IHE PAM FR 2.11.2",
  "base": "json-2.5",
  "generatedFrom": "schemas/json-2.5-IHEPAMFR"
}
```

`base` is `""` for an HL7 profile (not derived).

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
