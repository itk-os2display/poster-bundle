<?php

namespace Os2Display\PosterBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PosterService
{
    public function getEvents($query)
    {
        // @TODO: Move to EventdatabasenIntegration.

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                "https://api.detskeriaarhus.dk/api/events",
                [
                    'query' => $query,
                    'timeout' => 2,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            $events = $results->{'hydra:member'};

            return $events;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public function getEvent($id)
    {
        // @TODO: Move to EventdatabasenIntegration.

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                "https://api.detskeriaarhus.dk/api/events/".$id,
                [
                    'timeout' => 2,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            foreach ($results->occurences as &$occurrence) {
                $occurrence->id = $occurrence->{'@id'};
            }

            $event = [
                'id' => $results->{'@id'},
                'title' => $results->name,
                'image' => $results->image,
                'description' => $results->description,
                'occurrences' => $results->occurrences,
            ];

            return $event;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public function getOccurrence($id) {
        // @TODO: Move to EventdatabasenIntegration.

        try {
            $client = new Client();
            $res = $client->request(
                'GET',
                "https://api.detskeriaarhus.dk/api/occurrences/".$id,
                [
                    'timeout' => 2,
                ]
            );

            $results = json_decode($res->getBody()->getContents());

            return $results;
        } catch (GuzzleException $e) {
            return [];
        }
    }
}
