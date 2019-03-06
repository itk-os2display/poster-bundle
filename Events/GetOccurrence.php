<?php

namespace Os2Display\PosterBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class GetOccurrence
 * @package Os2Display\PosterBundle\Events
 */
class GetOccurrence extends Event
{
    const EVENT = 'os2display.poster.get_occurrence';

    protected $id;
    protected $occurrence;

    /**
     * GetEvent constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getOccurrence() {
        return $this->occurrence;
    }

    public function setOccurrence($occurrence) {
        $this->occurrence = $occurrence;
    }

    public function getId() {
        return $this->id;
    }
}
