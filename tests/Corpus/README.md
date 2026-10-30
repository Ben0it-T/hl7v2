# Test corpus

Forged HL7 v2 messages used as a non-regression corpus for the validation redesign.
No real-world message is used: every message is written for a purpose, with fictitious data.

## Layout

```
tests/Corpus/
├── messages/json-<profile>/<structure>/<id>.hl7    forged message (segments separated by CR)
├── messages/json-<profile>/<structure>/<id>.json   case file
├── fragments/json-<profile>/<datatype>.json        isolated values, injected into a carrier message
├── carriers/json-<profile>.hl7                     minimal valid message for the fragments
└── hl7-examples/                                   local only, never committed
```

`json-<profile>` names the profile directory the message is validated against (`profiles/json-<profile>`), for example `json-2.5` or `json-2.5-IHEPAMFR`.
`<structure>` is the message structure (`ADT_A01`, `ORU_R01`…).

## Case file

```json
{
    "title": "C1: ORU^R01 without ORC (optional first child of ORDER_OBSERVATION)",
    "tests": ["structure: entry segments of a group whose first child is optional"],
    "references": ["HL7 2.5 ch. 7, ORU^R01"],
    "basedOn": "HL7 2.5 ch. 3.5.2",
    "expected": {
        "valid": true,
        "findings": []
    },
    "knownBug": "Defect 1: ..."
}
```

| Key | Required | Meaning |
|---|---|---|
| `title` | yes | What the message is. |
| `tests` | yes | What the case checks, one item per rule. |
| `references` | yes | Specification sections the expected result comes from. |
| `basedOn` | no | Example of the specification the message was modelled on (rewritten, never copied). |
| `expected.valid` | yes | Validity according to the specification (only Errors make a message invalid). |
| `expected.findings` | yes | Expected findings according to the specification (may be empty). |
| `knownBug` | no | How the current validator departs from the expected result. |

A finding has a `severity` (`Error`, `Warning`, `Info`), a `summary`, and as many positions as apply:
`segment` (1-based rank of the segment in the message), `path` (path in the profile, for example `PATIENT_RESULT[2]/ORDER_OBSERVATION`) and `location` (HL7 Location, 1-based, for example `OBR-4.1`).

`expected` always describes what the specification requires, never what the current code does.
When the current validator disagrees, the case documents it in `knownBug`: the test is then marked incomplete instead of failing, and the bug is never frozen as the expected result.

## Fragments

A fragment file lists values for one datatype and the location where each value is injected into the carrier message of the same profile:

```json
{
    "location": "PID-5",
    "cases": [
        { "value": "DOE^JANE^^^^^L", "expected": { "valid": true, "findings": [] } }
    ]
}
```

## Running

```bash
vendor/bin/phpunit tests/Corpus
```
