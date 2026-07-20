<?php
declare(strict_types=1);

namespace HL7v2\Serializer;

class HL7DatatypeXmlSerializer
{
    /**
     * Convert profiled message to HL7 datatype-aware XML representation.
     *
     * @param array<string, mixed> $profiledMessage Root profiled message group.
     * @param bool $includeNamespace Export HL7 XML namespace.
     *
     * @return string
     */
    public function serialize(array $profiledMessage, bool $includeNamespace = true): string
    {
        $dom = new \DOMDocument(version: '1.0', encoding: 'UTF-8');

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->xmlStandalone = false;

        if ($includeNamespace) {
            $root = $dom->createElementNS('urn:hl7-org:v2xml', $profiledMessage['Name']);
        } else {
            $root = $dom->createElement($profiledMessage['Name']);
        }

        $rootName = $profiledMessage['Name'];
        $dom->appendChild($root);

        $this->appendGroup($dom, $root, $profiledMessage, $rootName);

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \LogicException(
                'Unable to generate XML.'
            );
        }

        return $xml;
    }

    /**
     * Append group XML node.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<string, mixed> $group
     * @param string $rootName
     *
     * @return void
     */
    private function appendGroup(\DOMDocument $dom, \DOMElement $parent, array $group, string $rootName): void
    {
        foreach ($group['segments'] as $element) {

            if ($element['Type'] === 'segment') {
                $this->appendSegment($dom, $parent, $element);
                continue;
            }

            if ($element['Type'] === 'group') {
                $groupElement = $dom->createElement($rootName . '.' . $element['Name']);
                $parent->appendChild($groupElement);

                $this->appendGroup($dom, $groupElement, $element, $rootName);
            }
        }
    }

    /**
     * Append segment XML node.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<string, mixed> $segment
     *
     * @return void
     */
    private function appendSegment(\DOMDocument $dom, \DOMElement $parent, array $segment): void
    {
        $segmentElement = $dom->createElement($segment['Name']);
        $parent->appendChild($segmentElement);

        foreach ($segment['fields'] as $field) {
            $this->appendField($dom, $segmentElement, $field);
        }
    }

    /**
     * Append field XML node(s).
     *
     * One XML field element is generated for each HL7 field repetition.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<int, array<string, mixed>> $field
     *
     * @return void
     */
    private function appendField(\DOMDocument $dom, \DOMElement $parent, array $field): void
    {
        foreach ($field as $fieldRepeat) {
            $this->appendFieldRepeat($dom, $parent, $fieldRepeat);
        }
    }

    /**
     * Append field repetition XML node.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<string, mixed> $fieldRepeat
     *
     * @return void
     */
    private function appendFieldRepeat(\DOMDocument $dom, \DOMElement $parent, array $fieldRepeat): void
    {
        // Composite datatype
        if (isset($fieldRepeat['components'])) {
            $fieldElement = $dom->createElement($fieldRepeat['Name']);
            $parent->appendChild($fieldElement);

            foreach ($fieldRepeat['components'] as $component) {
                $this->appendComponent($dom, $fieldElement, $component);
            }

            return;
        }

        // Simple datatype
        $value = $fieldRepeat['value'] ?? '';

        if ($value === '') {
            return;
        }

        $fieldElement = $dom->createElement($fieldRepeat['Name']);
        $fieldElement->appendChild($dom->createTextNode($value));
        $parent->appendChild($fieldElement);
    }

    /**
     * Append component XML node.
     *
     * Simple empty components are not exported.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<string, mixed> $component
     *
     * @return void
     */
    private function appendComponent(\DOMDocument $dom, \DOMElement $parent, array $component): void
    {
        // Composite component
        if (isset($component['subcomponents'])) {
            $componentElement = $dom->createElement($component['Name']);
            $parent->appendChild($componentElement);

            foreach ($component['subcomponents'] as $subComponent) {
                $this->appendSubComponent($dom, $componentElement, $subComponent);
            }

            return;
        }

        // Simple component
        $value = $component['value'] ?? '';

        if ($value === '') {
            return;
        }

        $componentElement = $dom->createElement($component['Name']);
        $componentElement->appendChild($dom->createTextNode($value));
        $parent->appendChild($componentElement);
    }

    /**
     * Append sub-component XML node.
     *
     * Empty sub-components are not exported.
     *
     * @param \DOMDocument $dom
     * @param \DOMElement $parent
     * @param array<string, mixed> $subComponent
     *
     * @return void
     */
    private function appendSubComponent(\DOMDocument $dom, \DOMElement $parent, array $subComponent): void
    {
        $value = $subComponent['value'] ?? '';

        if ($value === '') {
            return;
        }

        $subComponentElement = $dom->createElement($subComponent['Name']);
        $subComponentElement->appendChild($dom->createTextNode($value));
        $parent->appendChild($subComponentElement);
    }

}
