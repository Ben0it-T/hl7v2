<?php
declare(strict_types=1);

namespace HL7v2\Model;

use HL7v2\Model\Segment;

/**
 * Represents a parsed HL7 v2 message.
 *
 * Message
 * └── Segment
 *      └── Field
 *           └── FieldRepeat
 *                └── Component
 *                     └── SubComponent
 */
class Message
{
    /**
     * @var Segment[]
     */
    private array $segments = [];

    // Separators and encoding characters
    private string $segmentSeparator      = "\r"; // 0x0D (CR) chr(13)
    private string $fieldSeparator        = '|';
    private string $fieldRepeatSeparator  = '~';
    private string $componentSeparator    = '^';
    private string $subComponentSeparator = '&';
    private string $escapeChar            = '\\';

    // Message metadata
    private string $messageCode              = '';
    private string $triggerEvent             = '';
    private string $structure                = '';
    private string $versionId                = '';
    private string $internationalizationCode = '';
    private string $internationalVersionId   = '';


    /**
     * Set field separator (MSH-1) and encoding characters (MSH-2)
     * from control string
     *
     * @param string $fieldSep
     * @param string $componentSep
     * @param string $fieldRepeatSep
     * @param string $escapeChar
     * @param string $subComponentSep
     */
    public function setSeparators(
        string $fieldSep,
        string $componentSep,
        string $fieldRepeatSep,
        string $escapeChar,
        string $subComponentSep
    ): void {
        $this->fieldSeparator        = $fieldSep;
        $this->componentSeparator    = $componentSep;
        $this->fieldRepeatSeparator  = $fieldRepeatSep;
        $this->escapeChar            = $escapeChar;
        $this->subComponentSeparator = $subComponentSep;
    }

    /**
     * Get segment separator
     *
     */
    public function getSegmentSeparator(): string { return $this->segmentSeparator; }

    /**
     * Get field separator
     *
     */
    public function getFieldSeparator(): string { return $this->fieldSeparator; }

    /**
     * Get encoding characters
     *
     */
    public function getComponentSeparator(): string { return $this->componentSeparator; }
    public function getFieldRepeatSeparator(): string { return $this->fieldRepeatSeparator; }
    public function getSubComponentSeparator(): string { return $this->subComponentSeparator; }
    public function getEscapeChar(): string { return $this->escapeChar; }


    /**
     * Set message metadata extracted from MSH-9 and MSH-12.
     *
     * @param string $messageCode              MSH-9.1 Message Code
     * @param string $triggerEvent             MSH-9.2 Trigger Event
     * @param string $structure                MSH-9.3 Message Structure
     * @param string $versionId                MSH-12.1 Version ID
     * @param string $internationalizationCode MSH-12.2 Internationalization Code
     * @param string $internationalVersionId   MSH-12.3 International Version ID
     */
    public function setMetadata(
        string $messageCode,
        string $triggerEvent,
        string $structure,
        string $versionId,
        string $internationalizationCode,
        string $internationalVersionId)
    : void {
        $this->messageCode  = $messageCode;
        $this->triggerEvent = $triggerEvent;
        $this->structure    = $structure;
        $this->versionId    = $versionId;
        $this->internationalizationCode = $internationalizationCode;
        $this->internationalVersionId   = $internationalVersionId;
    }

    /**
     * Get message metadata
     *
     */
    public function getMessageCode(): string { return $this->messageCode; }
    public function getTriggerEvent(): string { return $this->triggerEvent; }
    public function getStructure(): string { return $this->structure; }
    public function getVersionId(): string { return $this->versionId; }
    public function getInternationalizationCode(): string { return $this->internationalizationCode; }
    public function getInternationalVersionId(): string { return $this->internationalVersionId; }

    /**
     * Add segment
     *
     * @param Segment $segment
     * @return void
     */
    public function addSegment(Segment $segment): void
    {
        $this->segments[] = $segment;
    }

    /**
     * Get segment
     *
     * 0-based position
     *
     * @return Segment|null
     */
    public function getSegment(int $index): ?Segment
    {
        return $this->segments[$index] ?? null;
    }

    /**
     * Get segments
     *
     * @return Segment[]
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

}
