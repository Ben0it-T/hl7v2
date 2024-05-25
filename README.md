# hl7v2

PHP class to parse HL7 v2.x messages and compare/validate against a profile.

## Usage

```
MSH|^~\&|SendingApp|SendingFacility|ReceivingApp|ReceivingFacility|20240524103000||ADT^A31^ADT_A05|12345|D|2.5^FRA^2.11||||||8859/1
EVN||20240524103000
PID|1||123456789^^^AssigningAuthority^PI~170017510112313^^^ASIP-SANTE-INS-NIR&1.2.250.1.213.1.4.10&ISO^INS||Lastname^Firstname^^^^^D~Lastname^Firstname^Firstname^^^^L||19700101|M|||3 avenue Montaigne^1er étage^PARIS 08^^75008^FRA^H^^FRANCE^^^^20230101^20260101~^^PARIS 01^75101^75001^FRA^BDL^^75101||^PRN^PH^^^^^^^^^0102030405~^PRN^CP^^^^^^^^^0602030405~^WPN^FX^^^^^^^^^0302030405~^NET^Internet^mail.address@hdomaine.net~^WPN^PH^^^^^^^^^0202030405||FR|M|||||||PARIS 01|Y|1|FRA||||N||PROV~FICT
PD1||U||||||||||N
PV1|1|N
```

### Parse HL7 message

```php
use HL7\Message;

$msg = new Message();
$msg->parseMessage($msgStr);

echo "msg Type: " . $msg->messageType . PHP_EOL;
echo "msg Trigger Event: " . $msg->messageTriggerEvent . PHP_EOL;
echo "msg Struct ID: " . $msg->messageStructID . PHP_EOL;
echo "msg Version ID: " . $msg->messageVersionID . PHP_EOL;


// Get a simple array representation of the message
$msgArray = $msg->getMsgParse();
print_r($msgArray);

// Array
// (
//   [0] => Array
//     (
//       [MSH] => Array
//         (
//           [1] => Array
//             (
//               [0] => Array
//                 (
//                   [1] => Array
//                     (
//                       [1] => |
//                     )
//                 )
//             )
//           [2] => Array
//             (
//               [0] => Array
//                 (
//                   [1] => Array
//                       (
//                         [1] => ^~\&
//                       )
//                 )
//             )
//           [3] => Array
//             (
//               [0] => Array
//                 (
//                   [1] => Array
//                     (
//                       [1] => SendingApp
//                     )
//                 )
//             )
//           ...


// Get an array representation of the message according to profile
$msgData = $msg->getMsgData();
print_r($msgData);

// Array
// (
//   [Type] => group
//   [Name] => ADT_A05
//   [LongName] => ADT_A05
//   [segments] => Array
//     (
//       [0] => Array
//         (
//           [Type] => segment
//           [Name] => MSH
//           [LongName] => Message Header
//           [hasError] => 
//           [comments] => Segment 'MSH' cardinality is [1..1]. Found 1 time(s).
//           [fields] => Array
//             (
//               [1] => Array
//                 (
//                   [0] => Array
//                       (
//                         [Type] => field
//                         [Name] => MSH.1
//                         [LocName] => MSH-1
//                         [LongName] => Field Separator
//                         [Datatype] => ST
//                         [hasError] => 
//                         [comments] => Field 'Field Separator' cardinality is [1..1]. Found 1 time(s). 
//                         [value] => |
//                       )
//                 )
//               [2] => Array
//                 (
//                     [0] => Array
//                       (
//                         [Type] => field
//                         [Name] => MSH.2
//                         [LocName] => MSH-2
//                         [LongName] => Encoding Characters
//                         [Datatype] => ST
//                         [hasError] => 
//                         [comments] => Field 'Encoding Characters' cardinality is [1..1]. Found 1 time(s). 
//                         [value] => ^~\&
//                       )
//                 )
//               [3] => Array
//                 (
//                   [0] => Array
//                     (
//                       [Type] => field
//                       [Name] => MSH.3
//                       [LocName] => MSH-3
//                       [LongName] => Sending Application
//                       [Datatype] => HD
//                       [hasError] => 
//                       [comments] => Field 'Sending Application' cardinality is [1..1]. Found 1 time(s). 
//                       [value] => SendingApp
//                       [components] => Array
//                         (
//                           [1] => Array
//                             (
//                               [Type] => component
//                               [Name] => HD.1
//                               [LocName] => MSH-3.1
//                               [LongName] => Namespace ID
//                               [Datatype] => IS
//                               [hasError] => 
//                               [comments] => 
//                               [value] => SendingApp
//                             )
//                         )
//                     )
//                 )
//               ...


// Get XML representation of the message according to profile 
$msgXML = $msg->getMsgXML();
print_r(htmlentities($msgXML));

// <?xml version="1.0" encoding="UTF-8" standalone="no"?>
// <ADT_A05 xmlns="urn:hl7-org:v2xml">
//   <MSH>
//     <MSH.1>|</MSH.1>
//     <MSH.2>^~\&amp;</MSH.2>
//     <MSH.3>
//       <HD.1>SendingApp</HD.1>
//     </MSH.3>
//     <MSH.4>
//       <HD.1>SendingFacility</HD.1>
//     </MSH.4>
//     <MSH.5>
//       <HD.1>ReceivingApp</HD.1>
//     </MSH.5>
//     <MSH.6>
//       <HD.1>ReceivingFacility</HD.1>
//     </MSH.6>
//     <MSH.7>
//       <TS.1>20240524103000</TS.1>
//     </MSH.7>
//     <MSH.9>
//       <MSG.1>ADT</MSG.1>
//       <MSG.2>A31</MSG.2>
//       <MSG.3>ADT_A05</MSG.3>
//     </MSH.9>
//     <MSH.10>12345</MSH.10>
//     <MSH.11>
//       <PT.1>D</PT.1>
//     </MSH.11>
//     ...
 
```

### Validation and test reports

```php
use HL7\Message;

$msg = new Message();
$msg->parseMessage($msgStr);

// Validation report
$validationReport = $msg->getValidationReport();

// Test report
$testReport = $msg->getTestReport();
```

