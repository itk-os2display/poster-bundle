<?php

namespace Os2Display\PosterBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class GetEvents
 * @package Os2Display\CoreBundle\Events
 */
class GetEvents extends Event
{
    const EVENT = 'os2display.poster.get_events';

    protected $query;
    protected $events;

    /**
     * GetEvents constructor.
     *
     * @param array $query
     *   Array of query parameters.
     */
    public function __construct(array $query)
    {
        $this->query = $query;
    }

    public function getEvents() {
        return $this->events;
    }

    public function setEvents(array $events) {
        $this->events = $events;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setQuery(array $query) {
        $this->query = $query;
    }
}
