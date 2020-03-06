<?php

namespace Os2Display\PosterBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;

/**
 * Class EventdatabasenIntegration.
 */
class EventdatabasenIntegration
{
    const NAME = 'Eventdatabasen';

    private $enabled;
    private $url;

    /**
     * EventdatabasenIntegration constructor.
     */
    public function __construct($enabled, $url)
    {
        $this->enabled = $enabled;
        $this->url = $url;
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

        $query['occurrences.startDate'] = ['after' => date('Y-m-d')];
        $query['items_per_page'] = 10;

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                $this->url . '/api/events',
                [
                    'query' => $query,
                    'timeout' => 2,
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
                    'timeout' => 2,
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
                    'timeout' => 2,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            $baseUrl = parse_url($results->event->{'url'}, PHP_URL_HOST);

            $eventOccurrence = (object) [
                'eventId' => $results->event->{'@id'},
                'occurrenceId' => $results->{'@id'},
                'ticketPurchaseUrl' => $results->event->{'ticketPurchaseUrl'},
                'excerpt' =>  $results->event->{'excerpt'},
                'description' =>  strip_tags($results->event->{'description'}),
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
                    'description' => strip_tags($results->place->description),
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
}
