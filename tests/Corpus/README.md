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
    "knownBug": "Only the first child opens a group; ..."
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
    "references": ["HL7 2.5 ch. 2.A.88 XPN", "HL7 2.5 table 0200"],
    "cases": [
        {
            "value": "DOE^JANE^^^^^L",
            "tests": ["XPN: family name, given name, name type L"],
            "expected": { "valid": true, "findings": [] }
        }
    ]
}
```

`location` is a field (`SEG-n`, first occurrence of the segment): each `value` replaces that field of the carrier message, which is valid on its own.
`tests`, `expected` and `knownBug` have the same meaning as in a case file; `references` applies to every value of the file.

## Comparisons

A comparison case is a pair of messages and a case file: `comparisons/<id>/left.hl7`, `right.hl7` and `case.json`.

```json
{
    "title": "PID-5.2 changed",
    "tests": ["comparison: CHANGED at component level"],
    "references": ["README, Compare two messages"],
    "ignore": [],
    "expected": {
        "identical": false,
        "differences": [
            { "location": "PID[1]-5[1].2.1", "type": "CHANGED", "left": "JANE", "right": "JOAN" }
        ]
    }
}
```

`ignore` lists the ignore rules passed to `compare()`. `expected` states whether the messages are identical and lists the differences, in the Location notation (1-based, a segment ranked among the segments of the same name); `"expected": { "error": true }` means the ignore rules must be refused. `knownBug` has the same meaning as in a case file.
Until the Comparator uses the Location notation, the test compares the outcome only (identical, number of differences, error); the differences are recorded in the snapshots.

## Snapshots

Each suite also compares the outputs of the current validator with snapshots, so that any change of behaviour during the redesign is seen and reviewed.
Snapshots record what the code does; `expected` and `knownBug` record what the specification requires.

| Cases | Directory | Outputs |
|---|---|---|
| Messages | `snapshots/messages/<id>/` | `test-report.json`, `validation-report.json`, `profiled-message.json`, `message.hl7`, `structural.xml`, `datatype.xml` |
| Fragments | `snapshots/fragments/json-<profile>/<datatype>/<n>/` | `test-report.json`, restricted to the entries of the injected field |
| Comparisons | `snapshots/comparisons/<id>/` | `differences.json` (compared paths and differences) |
| HL7 examples | `hl7-examples/snapshots/<id>/` (local) | same as messages |

An output that throws an exception is recorded as `EXCEPTION <class>: <message>`.
A missing snapshot makes the test fail. After reviewing a change, write the new snapshots with `UPDATE_SNAPSHOTS=1`, and commit them with the change.

## Running

```bash
vendor/bin/phpunit tests/Corpus
UPDATE_SNAPSHOTS=1 vendor/bin/phpunit tests/Corpus
```
