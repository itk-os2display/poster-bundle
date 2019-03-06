<?php

namespace Os2Display\PosterBundle\Service;

use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PosterService
{
    private $providers;
    private $cronInterval;
    private $dispatcher;

    /**
     * PosterService constructor.
     *
     * @param $cronInterval
     * @param $providers
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct($cronInterval, $providers, EventDispatcherInterface $dispatcher)
    {
        $this->cronInterval = $cronInterval;
        $this->providers = $providers;
        $this->dispatcher = $dispatcher;
    }

    public function getEvents($query)
    {
        $event = new GetEvents($query);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getEvents();
    }

    public function getEvent($id)
    {
        $event = new GetEvent($id);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getEvent();
    }

    public function getOccurrence($occurrenceId)
    {
        $event = new GetOccurrence($occurrenceId);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getOccurrence();
    }
}
