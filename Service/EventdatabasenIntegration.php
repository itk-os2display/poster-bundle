<?php

namespace Os2Display\PosterBundle\Service;

use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;
use Os2Display\PosterBundle\Events\SearchByType;
use Os2Display\PosterBundle\Events\SearchEvents;
use Psr\Log\LoggerInterface;

/**
 * Class EventdatabasenIntegration.
 */
class EventdatabasenIntegration
{
    const NAME = 'Eventdatabasen';
    const API_TIMEOUT = 10;

    private $enabled;
    private $url;
    private $cache;
    private $logger;

    /**
     * EventdatabasenIntegration constructor.
     *
     * @param $enabled
     * @param $url
     * @param \Doctrine\Common\Cache\CacheProvider $cache
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct($enabled, $url, CacheProvider $cache, LoggerInterface $logger)
    {
        $this->enabled = $enabled;
        $this->url = $url;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Subscribed events.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            GetEvents::EVENT => 'getEvents',
            GetEvent::EVENT => 'getEvent',
            GetOccurrence::EVENT => 'getOccurrence',
            SearchEvents::EVENT => 'searchEvents',
            SearchByType::EVENT => 'searchByType',
        ];
    }

    /**
     * Get events.
     *
     * @param \Os2Display\PosterBundle\Events\GetEvents $event
     */
    public function getEvents(GetEvents $event)
    {
        if (!$this->enabled) {
            return;
        }

        $query = $event->getQuery();

        if (isset($query['url']) && $query['url'] == '') {
            unset($query['url']);
        }

        if (isset($query['organizer'])) {
            $organizer = $query['organizer'];
            unset($query['organizer']);
            $query['organizer.id'] = $organizer;
        }
        if (isset($query['place'])) {
            $place = $query['place'];
            unset($query['place']);
            $query['occurrences.place.id'] = $place;
        }
        if (isset($query['tag'])) {
            $tag = $query['tag'];
            unset($query['tag']);
            $query['tags'] = [$tag];
        }

        $query['occurrences.startDate'] = ['after' => date('Y-m-d')];
        $query['items_per_page'] = 10;

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                $this->url . '/api/events',
                [
                    'query' => $query,
                    'timeout' => self::API_TIMEOUT,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            $results->number_of_pages = (int) ($results->{'hydra:totalItems'} / $query['items_per_page']);

            $events = $results->{'hydra:member'};

            $event->setEvents($events);
            $event->setMeta([
                'number_of_pages' => $results->number_of_pages,
                'page' => isset($query['page']) ? (int) $query['page'] : 1,
                'total_results' => $results->{'hydra:totalItems'},
                'items_per_page' => $query['items_per_page'],
            ]);
        } catch (GuzzleException $e) {
        }
    }

    /**
     * Get event.
     *
     * @param \Os2Display\PosterBundle\Events\GetEvent $event
     */
    public function getEvent(GetEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $id = $event->getId();

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                $this->url . $id,
                [
                    'timeout' => self::API_TIMEOUT,
                ]
            );

            $results = json_decode($res->getBody()->getContents());


            $result = [
                'id' => $results->{'@id'},
                'title' => $results->name,
                'image' => $results->image,
                'description' => $results->description,
                'occurrences' => $results->occurrences,
            ];

            $event->setEvents($result);
        } catch (GuzzleException $e) {
        }
    }

    /**
     * Get Occurrence.
     *
     * @param \Os2Display\PosterBundle\Events\GetOccurrence $event
     */
    public function getOccurrence(GetOccurrence $event) {
        if (!$this->enabled) {
            return;
        }

        $occurrenceId = $event->getId();

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                $this->url . $occurrenceId,
                [
                    'timeout' => self::API_TIMEOUT,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            $baseUrl = parse_url($results->event->{'url'}, PHP_URL_HOST);

            $eventOccurrence = (object) [
                'eventId' => $results->event->{'@id'},
                'occurrenceId' => $results->{'@id'},
                'ticketPurchaseUrl' => $results->event->{'ticketPurchaseUrl'},
                'excerpt' =>  $results->event->{'excerpt'},
                'name' =>  $results->event->{'name'},
                'url' =>  $results->event->{'url'},
                'baseUrl' => $baseUrl,
                'image' =>  $results->event->{'image'},
                'startDate' =>  $results->{'startDate'},
                'endDate' =>  $results->{'endDate'},
                'ticketPriceRange' =>  $results->{'ticketPriceRange'},
                'eventStatusText' =>  $results->{'eventStatusText'},
            ];

            if (isset($results->place)) {
                $eventOccurrence->place = (object)[
                    'name' => $results->place->name,
                    'streetAddress' => $results->place->streetAddress,
                    'addressLocality' => $results->place->addressLocality,
                    'postalCode' => $results->place->postalCode,
                    'image' => $results->place->image,
                    'telephone' => $results->place->telephone,
                ];
            }

            $event->setOccurrence($eventOccurrence);
        } catch (GuzzleException $e) {
            if ($e->getCode() == 404) {
                $event->setNotFound(true);
            }
        }
    }

    /**
     * Search for events by query.
     *
     * @param \Os2Display\PosterBundle\Events\SearchEvents $event
     *
     * @throws \Exception
     */
    public function searchEvents(SearchEvents $event)
    {
        if (!$this->enabled) {
            return;
        }

        $query = $event->getQuery();

        $params = [
            'timeout' => self::API_TIMEOUT,
            'query' => [
                'items_per_page' => 5,
                'order' => [
                    'startDate' => 'asc'
                ],
                'startDate' => [
                    'after' => (new \DateTime())->format('c'),
                ],
            ]
        ];

        if (isset($query['numberOfResults'])) {
            $params['query']['items_per_page'] = $query['numberOfResults'];
        }

        if (isset($query['organizers'])) {
            $params['query']['event.organizer.id'] = $query['organizers'];
        }
        if (isset($query['places'])) {
            $params['query']['place.id'] = $query['places'];
        }
        if (isset($query['tags'])) {
            $params['query']['event.tags'] = $query['tags'];
        }

        $stack = HandlerStack::create();
        $stack->push(
            Middleware::log(
                $this->logger,
                new MessageFormatter()
            )
        );

        $params['handler'] = $stack;

        $client = new Client();
        $requestResult = $client->request(
            'GET',
            $this->url . '/api/occurrences',
            $params
        );

        $body = json_decode($requestResult->getBody()->getContents());

        $res = [
            'results' => $body->{'hydra:member'} ?? [],
            "pagination" => [
                "more" => isset($body->{'hydra:view'}->{'hydra:next'}),
            ],
        ];

        $res['results'] = array_reduce(
            $res['results'],
            function ($carry, $el) {
                $split = explode('/', $el->event->{'@id'});
                $id = end($split);

                $text = $el->event->name ?? null;

                $image = $el->event->images->large ?? $el->image ?? null;
                $imageSmall = $el->event->images->small ?? null;

                // Get organizer name.
                $client = new Client();
                $requestResult = $client->request(
                    'GET',
                    $this->url . $el->event->organizer
                );
                $organizer = json_decode($requestResult->getBody()->getContents());

                $newObject = (object)[
                    'id' => $id,
                    'text' => $text,
                    'name' => $text,
                    'image' => $image,
                    'imageSmall' => $imageSmall,
                    'organizer' => $organizer->name,
                ];

                $eventOccurrence = (object) [
                    'eventId' => $id,
                    'occurrenceId' => $el->{'@id'},
                    'ticketPurchaseUrl' => $el->event->{'ticketPurchaseUrl'},
                    'excerpt' =>  $el->event->{'excerpt'},
                    'name' =>  $el->event->{'name'},
                    'url' =>  $el->event->{'url'},
                    'image' =>  $image,
                    'startDate' =>  $el->{'startDate'},
                    'endDate' =>  $el->{'endDate'},
                    'ticketPriceRange' =>  $el->{'ticketPriceRange'},
                    'eventStatusText' =>  $el->{'eventStatusText'},
                    'place' => (object)[
                        'name' => $el->place->name,
                        'streetAddress' => $el->place->streetAddress,
                        'addressLocality' => $el->place->addressLocality,
                        'postalCode' => $el->place->postalCode,
                        'image' => $el->place->image,
                        'telephone' => $el->place->telephone,
                    ],
                ];

                $newObject->occurrence = $eventOccurrence;
                $newObject->startDate = $eventOccurrence->startDate;
                $newObject->endDate = $eventOccurrence->endDate;
                $newObject->place = $eventOccurrence->place->name ?? null;

                $carry[] = $newObject;

                return $carry;
            },
            []
        );

        $event->setResults($res);
    }

    /**
     * Search by type.
     *
     * @param \Os2Display\PosterBundle\Events\SearchByType $event
     */
    public function searchByType(SearchByType $event)
    {
        $type = $event->getType();
        $query = $event->getQuery();

        // Special case for Eventdatabase, since you can not search in tags.
        if ($type === 'tags') {
            $tags = $this->getTags();

            $search = $query['name'];

            $filteredTags = array_reduce(
                $tags,
                function ($carry, $tag) use ($search) {
                    if (strpos(strtolower($tag->name), strtolower($search)) !== false) {
                        $carry[] = (object)[
                            'id' => $tag->id,
                            'text' => $tag->name,
                        ];
                    }
                    return $carry;
                },
                []
            );

            $event->setResults([
                'results' => $filteredTags,
                'pagination' => [
                    'more' => false,
                ],
            ]);

            return;
        }

        $client = new Client();

        $params = [
            'timeout' => self::API_TIMEOUT,
            'query' => []
        ];

        if ($query !== null) {
            $params['query'] = $query;
        }

        $res = $client->request(
            'GET',
            $this->url . '/api/'.$type,
            $params
        );

        $content = json_decode($res->getBody()->getContents());

        $results = $content->{'hydra:member'} ?? [];

        $res = [
            'pagination' => [
                'more' => isset($content->{'hydra:view'}->{'hydra:next'}),
            ],
        ];

        $res['results'] = array_reduce(
            $results,
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

        $event->setResults($res);
    }

    /**
     * Get searchable tags.
     *
     * @param bool $clearCache
     *
     * @return array|false|mixed
     */
    private function getTags(bool $clearCache = false)
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

        // Cache for 24 hours.
        $this->cache->save($cacheKey, $tags, 60 * 60 * 24);

        return $tags;
    }

    /**
     * Get content from Eventdatabase.
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

        $params = [
            'timeout' => self::API_TIMEOUT,
        ];

        if ($search !== null) {
            $params['query'] = [
                'name' => $search,
            ];
        }

        $res = $client->request(
            'GET',
            $this->url . '/api/'.$type,
            $params
        );

        $res = json_decode($res->getBody()->getContents());

        $result = array_merge($result, $res->{'hydra:member'} ?? []);

        $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        while ($con) {
            $res = $client->request(
                'GET',
                $this->url.$res->{'hydra:view'}->{'hydra:next'},
                [
                    'timeout' => self::API_TIMEOUT,
                ]
            );

            $res = json_decode($res->getBody()->getContents());

            $result = array_merge($result, $res->{'hydra:member'});

            $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        }

        return $result;
    }
}
