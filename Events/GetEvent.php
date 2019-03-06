<?php

namespace Os2Display\PosterBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class GetEvent
 * @package Os2Display\CoreBundle\Events
 */
class GetEvent extends Event
{
    const EVENT = 'os2display.poster.get_event';

    protected $id;
    protected $event;

    /**
     * GetEvent constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getEvent() {
        return $this->event;
    }

    public function setEvents($event) {
        $this->event = $event;
    }

    public function getId() {
        return $this->id;
    }
}
