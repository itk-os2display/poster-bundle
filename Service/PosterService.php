<?php

namespace Os2Display\PosterBundle\Service;

use GuzzleHttp\Client;
use Os2Display\CoreBundle\Events\CronEvent;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\SearchEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Os2Display\CoreBundle\Entity\Slide;
use Doctrine\Common\Cache\CacheProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        $event = new SearchEvents($occurrenceId);
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

        $tags = array_reduce(
            $res,
            function ($carry, $tag) {
                $split = explode('/', $tag->{'@id'});
                $id = end($split);

                $carry[] = (object)[
                    'id' => $id,
                    'name' => $tag->name,
                ];

                return $carry;
            },
            []
        );

        // Cache for 1 hour.
        $this->cache->save($cacheKey, $tags, 60 * 60);

        return $tags;
    }

    /**
     * Search by type.
     *
     * @param $type
     * @param null $query
     *
     * @return array|mixed|\Psr\Http\Message\ResponseInterface
     */
    public function search($type, $query = [])
    {
        // Special case for Eventdatabase, since you can not search in tags.
        if ($type === 'tags') {
            $tags = $this->getTags();

            $search = $query['name'];

            $filteredTags = array_reduce(
                $tags,
                function ($carry, $tag) use ($search) {
                    if (strpos(strtolower($tag->name), $search) !== false) {
                        $carry[] = (object)[
                            'id' => $tag->id,
                            'text' => $tag->name,
                        ];
                    }

                    return $carry;
                },
                []
            );

            return [
                'results' => $filteredTags,
                'pagination' => [
                    'more' => false,
                ],
            ];
        }

        $client = new Client();

        $params = ['timeout' => 2, 'query' => []];

        if ($query !== null) {
            $params['query'] = $query;
        }

        $res = $client->request(
            'GET',
            'https://api.detskeriaarhus.dk/api/'.$type,
            $params
        );

        $res = json_decode($res->getBody()->getContents());

        $res = [
            'results' => $res->{'hydra:member'} ?? [],
            "pagination" => [
                "more" => isset($res->{'hydra:view'}->{'hydra:next'}),
            ],
        ];

        $res['results'] = array_reduce(
            $res['results'],
            function ($carry, $el) use ($type) {
                $id = $el->id ?? null;

                if ($id === null) {
                    $split = explode('/', $el->{'@id'});
                    $id = end($split);
                }

                $text = $el->name ?? null;

                $newObject = (object)[
                    'id' => $id,
                    'text' => $text,
                ];

                $carry[] = $newObject;

                return $carry;
            },
            []
        );

        return $res;
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
     * @throws \Exception
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
