<?php

namespace Os2Display\PosterBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Os2Display\PosterBundle\Events\GetEvents;
use Os2Display\PosterBundle\Events\GetEvent;
use Os2Display\PosterBundle\Events\GetOccurrence;

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

    public function getEvents(GetEvents $event)
    {
        if (!$this->enabled) {
            return;
        }

        $query = $event->getQuery();
        $query['isPublished'] = true;

        if ($query['url'] == '') {
            unset($query['url']);
        }

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

            $events = $results->{'hydra:member'};

            $event->setEvents($events);
        } catch (GuzzleException $e) {
        }
    }

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
                $this->url . '/api/events/'.$id,
                [
                    'timeout' => 2,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            foreach ($results->occurences as &$occurrence) {
                $occurrence->id = $occurrence->{'@id'};
            }

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
        }
    }
}
