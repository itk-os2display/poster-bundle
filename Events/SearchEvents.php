<?php

namespace Os2Display\PosterBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class SearchEvents
 * @package Os2Display\PosterBundle\Events
 */
class SearchEvents extends Event
{
    const EVENT = 'os2display.poster.search_events';

    /* @var array $query */
    protected $query;
    /* @var array $results */
    protected $results;

    /**
     * SearchEvents constructor.
     * @param array $query
     */
    public function __construct(array $query)
    {
        $this->query = $query;
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param array $query
     */
    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    /**
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * @param array $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
    }
}
