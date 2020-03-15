<?php

namespace Os2Display\PosterBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class SearchByType
 * @package Os2Display\PosterBundle\Events
 */
class SearchByType extends Event
{
    const EVENT = 'os2display.poster.search_by_type';

    /* @var string $type */
    protected $type;
    /* @var array $query */
    protected $query;
    /* @var array $results */
    protected $results;

    /**
     * SearchByType constructor.
     *
     * @param string $type
     * @param array $query
     */
    public function __construct(string $type, array $query)
    {
        $this->query = $query;
        $this->type = $type;
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

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
