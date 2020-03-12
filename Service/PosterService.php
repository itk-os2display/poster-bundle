<?php

namespace Os2Display\PosterBundle\Service;

use GuzzleHttp\Client;
use Os2Display\CoreBundle\Events\CronEvent;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;
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
     *
     */
    public function updatePosterSlides()
    {
        $slides = $this->entityManager->getRepository(Slide::class)
            ->findBySlideType('poster-base');

        $cache = [];

        /* @var $slide Slide */
        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            if ((!isset($options['do_not_update']) || $options['do_not_update'] == false) &&
                isset($options['data']['occurrenceId'])) {
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
                    $options['data'] = $updatedOccurrence;
                    $cache[$cacheKey] = $updatedOccurrence;
                } else {
                    // If the occurrence does not exist, unpublish the slide,
                    // and stop updating the data.
                    $options['do_not_update'] = true;
                    $slide->setPublished(false);
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
     * Get searchable places.
     *
     * @param bool $clearCache
     *
     * @return array|false|mixed
     */
    public function getPlaces(bool $clearCache = false)
    {
        $cacheKey = 'poster.places';

        if (!$clearCache) {
            if ($this->cache->contains($cacheKey)) {
                return $this->cache->fetch($cacheKey);
            }
        }

        $res = $this->getContent('places');

        $places = array_reduce($res, function ($carry, $place) {
            $carry[] = (object) [
                'id' => $place->id,
                'name' => $place->name,
            ];
            return $carry;
        }, []);

        $this->cache->save($cacheKey, $places);

        return $places;
    }

    /**
     * Get searchable tags.
     *
     * @param bool $clearCache
     *
     * @return array|false|mixed
     */
    public function getTags(bool $clearCache = false)
    {
        $cacheKey = 'poster.tags';

        if (!$clearCache) {
            if ($this->cache->contains($cacheKey)) {
                return $this->cache->fetch($cacheKey);
            }
        }

        $res = $this->getContent('tags');

        $tags = array_reduce($res, function ($carry, $tag) {
            $split = explode('/', $tag->{'@id'});
            $id = end($split);

            $carry[] = (object) [
                'id' => $id,
                'name' => $tag->name,
            ];
            return $carry;
        }, []);

        $this->cache->save($cacheKey, $tags);

        return $tags;
    }

    /**
     * Get searchable organizers.
     *
     * @param bool $clearCache
     *
     * @return array|false|mixed
     */
    public function getOrganizers(bool $clearCache = false)
    {
        $cacheKey = 'poster.organizers';

        if (!$clearCache) {
            if ($this->cache->contains($cacheKey)) {
                return $this->cache->fetch($cacheKey);
            }
        }

        $res = $this->getContent('organizers');

        $organizers = array_reduce($res, function ($carry, $organizer) {
            $carry[] = (object) [
                'id' => $organizer->id,
                'name' => $organizer->name,
            ];
            return $carry;
        }, []);

        $this->cache->save($cacheKey, $organizers);

        return $organizers;
    }

    /**
     * Get content from Eventdatabase.
     *
     * @TODO: Change this to the event structure.
     *
     * @param string $type
     * @param null $search
     *
     * @return array
     */
    private function getContent(string $type, $search = null)
    {
        $client = new Client();

        $result = [];

        $params = ['timeout' => 2];

        if ($search !== null) {
            $params['query'] = [
                'name' => $search,
            ];
        }

        $res = $client->request(
            'GET',
            'https://api.detskeriaarhus.dk/api/'.$type,
            $params
        );

        $res = json_decode($res->getBody()->getContents());

        $result = array_merge($result, $res->{'hydra:member'} ?? []);

        $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        while ($con) {
            $res = $client->request(
                'GET',
                'https://api.detskeriaarhus.dk'.$res->{'hydra:view'}->{'hydra:next'},
                ['timeout' => 2]
            );

            $res = json_decode($res->getBody()->getContents());

            $result = array_merge($result, $res->{'hydra:member'});

            $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        }

        return $result;
    }
}
