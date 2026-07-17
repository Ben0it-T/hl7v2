<?php
declare(strict_types=1);

namespace HL7v2\Serializer;

use HL7v2\Model\Message;
use HL7v2\Model\Segment;
use HL7v2\Model\Field;
use HL7v2\Model\FieldRepeat;
use HL7v2\Model\Component;
use HL7v2\Model\SubComponent;

class HL7StructuralXmlSerializer
{
    /**
     * Convert message to structural XML representation.
     *
     * @param Message $message
     * @param bool $includeNamespace Export HL7 XML namespace.
     *
     * @return string
     */
    public function serialize(Message $message, bool $includeNamespace = true): string
    {
        $dom = new \DOMDocument(version: '1.0', encoding: 'UTF-8');

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->xmlStandalone = false;

        if ($includeNamespace) {
            $root = $dom->createElementNS('urn:hl7-org:v2xml', $message->getStructure());
        } else {
            $root = $dom->createElement($message->getStructure());
        }

        $dom->appendChild($root);

        foreach ($message->getSegments() as $segment) {
            $this->appendSegment($dom, $root, $segment);
        }

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \LogicException(
                'Unable to generate XML.'
            );
        }

        return $xml;
    }

    /**
     * Append segment XML node.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param Segment $segment
     *
     * @return void
     */
    private function appendSegment(\DOMDocument $dom, \DOMElement $parent, Segment $segment): void
    {
        $segmentElement = $dom->createElement(
            $segment->getName()
        );

        $parent->appendChild($segmentElement);

        foreach ($segment->getFields() as $fieldIndex => $field) {
            $this->appendField(
                $dom,
                $segmentElement,
                $segment->getName(),
                $fieldIndex + 1,
                $field
            );
        }
    }

    /**
     * Append field XML node(s).
     *
     * One XML field element is generated for each HL7 field repetition.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param string $segmentName
     * @param int $fieldPosition
     * @param Field $field
     *
     * @return void
     */
    private function appendField(\DOMDocument $dom, \DOMElement $parent, string $segmentName, int $fieldPosition, Field $field): void
    {
        $fieldName = sprintf('%s.%d', $segmentName, $fieldPosition);

        // MSH-1 and MSH-2
        if ($fieldName === 'MSH.1' || $fieldName === 'MSH.2') {
            $fieldElement = $dom->createElement($fieldName);

            $repeat = $field->getRepeat(0);
            $value = '';

            if ($repeat !== null) {
                $component = $repeat->getComponent(1);

                if ($component !== null) {
                    $subComponent = $component->getSubComponent(1);

                    if ($subComponent !== null) {
                        $value = $subComponent->getValue();
                    }
                }
            }

            if ($value !== '') {
                $fieldElement->appendChild(
                    $dom->createTextNode($value)
                );
            }

            $parent->appendChild($fieldElement);

            return;
        }

        foreach ($field->getRepeats() as $repeat) {
            $fieldElement = $dom->createElement($fieldName);
            $parent->appendChild($fieldElement);

            $this->appendFieldRepeat($dom, $fieldElement, $fieldName, $repeat);
        }
    }

    /**
     * Append field repetition XML node content.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param string $fieldName
     * @param FieldRepeat $repeat
     *
     * @return void
     */
    private function appendFieldRepeat(\DOMDocument $dom, \DOMElement $parent, string $fieldName, FieldRepeat $repeat): void
    {
        foreach ($repeat->getComponents() as $componentPosition => $component) {
            $this->appendComponent($dom, $parent, $fieldName, $componentPosition + 1, $component);
        }
    }

    /**
     * Append component XML node.
     *
     * Empty components are not exported.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param string $fieldName
     * @param int $componentPosition
     * @param Component $component
     *
     * @return void
     */
    private function appendComponent(\DOMDocument $dom, \DOMElement $parent, string $fieldName, int $componentPosition, Component $component): void
    {
        $componentName = sprintf('%s.%d', $fieldName, $componentPosition);
        $subComponents = $component->getSubComponents();

        // Empty component
        if (count($subComponents) === 1 && $subComponents[0]->getValue() === '') {
            return;
        }

        $componentElement = $dom->createElement($componentName);
        $parent->appendChild($componentElement);

        // Simple component
        if (count($subComponents) === 1) {
            $value = $subComponents[0]->getValue();
            if ($value !== '') {
                $componentElement->appendChild($dom->createTextNode($value));
            }

            return;
        }

        // Composite component
        $hasValue = false;
        foreach ($subComponents as $subComponent) {
            if ($subComponent->getValue() !== '') {
                $hasValue = true;
                break;
            }
        }

        if (!$hasValue) {
            return;
        }

        foreach ($subComponents as $subComponentPosition => $subComponent) {
            $this->appendSubComponent($dom, $componentElement, $componentName, $subComponentPosition + 1, $subComponent);
        }
    }

    /**
     * Append sub-component XML node.
     *
     * Empty sub-components are not exported.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param string $componentName
     * @param int $subComponentPosition
     * @param SubComponent $subComponent
     *
     * @return void
     */
    private function appendSubComponent(\DOMDocument $dom, \DOMElement $parent, string $componentName, int $subComponentPosition, SubComponent $subComponent): void {
        $subComponentName = sprintf('%s.%d', $componentName, $subComponentPosition);
        $subComponentElement = $dom->createElement($subComponentName);

        if ($subComponent->getValue() !== '') {
            $subComponentElement->appendChild(
                $dom->createTextNode($subComponent->getValue())
            );
        }

        $parent->appendChild($subComponentElement);
    }

}
