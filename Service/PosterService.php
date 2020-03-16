<?php

namespace Os2Display\PosterBundle\Service;

use Os2Display\CoreBundle\Events\CronEvent;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;
use Os2Display\PosterBundle\Events\SearchByType;
use Os2Display\PosterBundle\Events\SearchEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Os2Display\CoreBundle\Entity\Slide;
use Doctrine\Common\Cache\CacheProvider;

class PosterService
{
    private $providers;
    private $cronInterval;
    private $dispatcher;
    private $cache;

    /**
     * PosterService constructor.
     *
     * @param $cronInterval
     * @param $providers
     * @param EventDispatcherInterface $dispatcher
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Doctrine\Common\Cache\CacheProvider $cache
     */
    public function __construct(
        $cronInterval,
        $providers,
        EventDispatcherInterface $dispatcher,
        EntityManagerInterface $entityManager,
        CacheProvider $cache
    ) {
        $this->cronInterval = $cronInterval;
        $this->providers = $providers;
        $this->dispatcher = $dispatcher;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    /**
     * onCron listener.
     *
     * @param \Os2Display\CoreBundle\Events\CronEvent $event
     */
    public function onCron(CronEvent $event)
    {
        $lastCron = $this->cache->fetch('last_cron');
        $timestamp = \time();

        if (false === $lastCron || $timestamp > $lastCron + $this->cronInterval) {
            $this->updatePosterSlides();
            $this->cache->save('last_cron', $timestamp);
        }
    }

    /**
     * Update the slides.
     */
    public function updatePosterSlides()
    {
        $slides = $this->entityManager->getRepository(Slide::class)
            ->findBySlideType('poster-base');

        $cache = [];

        /* @var $slide Slide */
        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            if (!isset($options['do_not_update']) || $options['do_not_update'] == false) {
                // Subscription type.
                if (isset($options['type']) && $options['type'] === 'subscription') {
                    $subscription = $options['subscription'];

                    $query = [];

                    if (isset($subscription['selectedPlaces'])) {
                        $query['places'] = array_reduce($subscription['selectedPlaces'], function ($carry, $place) {
                            if (isset($place['id'])) {
                                $carry[] = $place['id'];
                            }
                            return $carry;
                        }, []);
                    }
                    if (isset($subscription['selectedTags'])) {
                        $query['tags'] = array_reduce($subscription['selectedTags'], function ($carry, $tag) {
                            if (isset($tag['id'])) {
                                $carry[] = $tag['id'];
                            }
                            return $carry;
                        }, []);
                    }
                    if (isset($subscription['selectedOrganizers'])) {
                        $query['organizers'] = array_reduce($subscription['selectedOrganizers'], function ($carry, $organizer) {
                            if (isset($organizer['id'])) {
                                $carry[] = $organizer['id'];
                            }
                            return $carry;
                        }, []);
                    }

                    $results = $this->searchEvents($query);

                    $occurrences = array_map(function ($el) {
                        return $el->occurrence;
                    }, $results['results'] ?? []);

                    $slide->setExternalData(['results' => $occurrences]);

                    if (isset($occurrences[0])) {
                        $options['data'] = $occurrences[0];
                    }
                }
                // Single type (or implicitly by type not being set).
                else if (isset($options['data']['occurrenceId'])) {
                    $cacheKey = sha1($options['data']['occurrenceId']);

                    if (isset($cache[$cacheKey])) {
                        $slide->setOptions($cache[$cacheKey]);
                        continue;
                    }

                    $updatedEvent = $this->getEvent($options['data']['eventId']);

                    $updatedOccurrence = $this->getOccurrence(
                        $options['data']['occurrenceId']
                    );

                    // If the occurrence does not exist:
                    if ($updatedOccurrence == false && $updatedEvent !== null) {
                        // See if other occurrences exist
                        // for the event, and pick the closest.
                        $oldStartDate = strtotime($options['data']['startDate']);

                        if (count($updatedEvent['occurrences']) > 0) {
                            // Find closest occurrence to current, and replace with this.
                            foreach ($updatedEvent['occurrences'] as $occurrence) {
                                $interval[] = abs($oldStartDate - strtotime($occurrence->startDate));
                            }
                            asort($interval);
                            $closestKey = key($interval);
                            $closestOccurrence = $updatedEvent['occurrences'][$closestKey];

                            if ($closestOccurrence) {
                                $updatedOccurrence = $this->getOccurrence($closestOccurrence->{'@id'});
                            }
                        }
                    }

                    if (isset($updatedOccurrence)) {
                        $slide->setExternalData(['results' => [
                            $updatedOccurrence
                        ]]);
                        $options['data'] = $updatedOccurrence;
                        $cache[$cacheKey] = $updatedOccurrence;
                    } else {
                        // If the occurrence does not exist, unpublish the slide,
                        // and stop updating the data.
                        $options['do_not_update'] = true;
                        $slide->setPublished(false);
                    }
                }

                $slide->setOptions($options);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get events from providers.
     *
     * @param $query
     *
     * @return mixed
     */
    public function getEvents($query)
    {
        $event = new GetEvents($query);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return [
            'events' => $event->getEvents(),
            'meta' => $event->getMeta(),
        ];
    }

    /**
     * Get event from providers.
     *
     * @param $id
     *
     * @return mixed
     */
    public function getEvent($id)
    {
        $event = new GetEvent($id);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getEvent();
    }

    /**
     * Get occurrence from providers.
     *
     * @param $occurrenceId
     *
     * @return mixed
     */
    public function getOccurrence($occurrenceId)
    {
        $event = new GetOccurrence($occurrenceId);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        if ($event->getNotFound()) {
            return false;
        }

        return $event->getOccurrence();
    }

    /**
     * Search by type.
     *
     * @param $type
     * @param array $query
     *
     * @return array
     */
    public function search($type, $query = [])
    {
        $event = new SearchByType($type, $query);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getResults();
    }

    /**
     * Search for events by query.
     *
     * query: {
     *   places: [Array_of_IDs],
     *   organizers: [Array_of_IDs],
     *   tags: [Array_of_Names]
     * }
     *
     * @param array $query
     *
     * @return array
     */
    public function searchEvents(array $query)
    {
        $event = new SearchEvents($query);
        $this->dispatcher->dispatch(
            $event::EVENT,
            $event
        );

        return $event->getResults();
    }
}
