# HL7v2

PHP library to parse, validate, compare and serialize HL7 v2.x messages.

## Features

- Parse HL7 v2.x messages
- Validate messages against HL7 profiles
- Support HL7 standard and custom tables
- Compare two HL7 messages
- Ignore selected fields, repetitions, components or sub-components during comparison
- Export messages to HL7 text format
- Export messages to structural XML
- Export messages to datatype-aware XML
- Expose validation results and profiled messages

## Usage

```
MSH|^~\&|SendingApp|SendingFacility|ReceivingApp|ReceivingFacility|20240524103000||ADT^A31^ADT_A05|12345|D|2.5^FRA^2.11||||||8859/1|FR||2.11^IHE_FRANCE-2.11-PAM
EVN||20240524103000
PID|1||123456789^^^AssigningAuthority^PI~170017510112313^^^ASIP-SANTE-INS-NIR&1.2.250.1.213.1.4.10&ISO^INS||Lastname^Firstname^^^^^D~Lastname^Firstname^Firstname^^^^L||19700101|M|||3 avenue Montaigne^1er étage^PARIS 08^^75008^FRA^H^^FRANCE^^^^20230101^20260101~^^PARIS 01^75101^75001^FRA^BDL^^75101||^PRN^PH^^^^^^^^^0102030405~^PRN^CP^^^^^^^^^0602030405~^WPN^FX^^^^^^^^^0302030405~^NET^Internet^mail.address@domaine.net~^WPN^PH^^^^^^^^^0202030405||FR|M|||||||PARIS 01|Y|1|FRA||||N||PROV~FICT
PD1||U||||||||||N
PV1|1|N
```

### Parse a message

```php
use HL7v2\HL7Message;

$hl7 = new HL7Message();

$hl7->parse($raw);
```

Parsed message object model:

```
Message
 └─ Segment
     └─ Field
         └─ FieldRepeat
             └─ Component
                 └─ SubComponent
```

Access parsed message information:

```php
$message = $hl7->getMessage();

echo $message->getMessageCode();    // ADT
echo $message->getTriggerEvent();   // A31
echo $message->getStructure();      // ADT_A05
echo $message->getVersionId();      // 2.5

echo $message
    ->getSegment(0)
    ->getField(9)
    ->getRepeat(0)
    ->getComponent(3)
    ->getSubComponent(1)
    ->getValue();   // ADT_A05
```

### Validate a message

Validate using a profile directory:

```php
$result = $hl7->validate(
    __DIR__ . '/profiles'
);
```

Or validate using already loaded objects:

```php
$result = $hl7->validateWith(
    $profile,
    $tables
);
```

Check validation result:

```php
$validationReport = $result->getValidationReport();
$testReport = $result->getTestReport();

if ($result->hasErrors()) {
    echo $result->getErrorCount();
}
```

Validation produces a profiled message.

```php
$profiledMessage = $hl7->getProfiledMessage();
```

The profiled message contains:

```
Group
 └─ Segment
     └─ Field
         └─ Component
             └─ SubComponent
```

with profile metadata:

```
Name
LongName
Datatype
Comments
Validation errors
```

### Compare two messages

```php
$hl7a = new HL7Message();
$hl7a->parse($rawA);

$hl7b = new HL7Message();
$hl7b->parse($rawB);

$result = $hl7a->compare($hl7b);

if ($result->isIdentical()) {
    echo 'Messages are identical';
}
```

Ignore selected fields, repetitions, components or sub-components:

```php
$result = $hl7a->compare(
    $hl7b,
    [
        'MSH-7',
        'MSH-10',
        'PID-3.4.1',
    ]
);
```

Comparison returns a ComparisonResult object containing all detected differences.

```php
foreach ($result->getDifferences() as $difference) {
    printf(
        "%s [%s] left='%s' right='%s'\n",
        $difference->getPath(),
        $difference->getType()->name,
        $difference->getLeft(),
        $difference->getRight()
    );
}
```

### Ignore rule syntax

| Rule              | Description                                                      |
| ----------------- | ---------------------------------------------------------------- |
| `MSH-7`           | Ignore field MSH-7                                               |
| `PID-3`           | Ignore field PID-3 and all repetitions/components/sub-components |
| `PID-3.4`         | Ignore component PID-3.4 and all sub-components                  |
| `PID-3.4.1`       | Ignore sub-component PID-3.4.1                                   |
| `PID[*]-3[*].4`   | Ignore component PID-3.4 and all its sub-components for all segment indexes and repetitions |
| `PID[2]-3[0].4.1` | Ignore only a specific path                                      |


### Serialize to structural XML

```php
echo $hl7->toStructuralXml();
```

Example:

```xml
<PID>
    <PID.5>
        <PID.5.1>Lastname</PID.5.1>
        <PID.5.2>Firstname</PID.5.2>
        <PID.5.3>Firstname</PID.5.3>
        <PID.5.7>L</PID.5.7>
    </PID.5>
</PID>

```

The structural XML mirrors the physical HL7 structure:

```
Segment
 └─ Field
     └─ Component
         └─ SubComponent
```

### Serialize to datatype-aware XML

The message must be validated first.

```php
$hl7->validate(__DIR__ . '/profiles');

echo $hl7->toDatatypeXml();
```

Example:

```xml
<PID.5>
    <XPN.1>
        <FN.1>Lastname</FN.1>
    </XPN.1>
    <XPN.2>Firstname</XPN.2>
    <XPN.3>Firstname</XPN.3>
    <XPN.7>L</XPN.7>
</PID.5>
```

The datatype-aware XML uses HL7 datatypes from the profile and preserves segment groups.

### Error handling

Methods may throw `HL7Exception` when:

- parsing invalid HL7 content
- validating before parsing a message
- comparing messages before parsing them
- exporting a message that has not been parsed
- exporting datatype-aware XML before validation
- loading invalid profile definitions

### Debug logging

```php
use HL7v2\Log\LoggerFactory;

$logger = LoggerFactory::createOutputLogger();

$hl7 = new HL7Message($logger);

$hl7->setDebug(true);
```
