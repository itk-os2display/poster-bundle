<?php

namespace Os2Display\PosterBundle\Controller;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PosterController.
 */
class PosterController extends Controller
{
    /**
     * Get events.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function eventsAction(Request $request)
    {
        $query = $request->query->all();

        return new JsonResponse($this->get('os2display.poster.service')->getEvents($query));
    }

    /**
     * Get an event by id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function eventAction(Request $request, $id)
    {
        return new JsonResponse($this->get('os2display.poster.service')->getEvent($id));
    }

    /**
     * Get an occurrence by id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function occurrenceAction(Request $request)
    {
        $occurrenceId = $request->query->get('occurrenceId');

        return new JsonResponse($this->get('os2display.poster.service')->getOccurrence($occurrenceId));
    }

    public function optionsAction(Request $request)
    {
        $search = $request->query->get('search');

        return new JsonResponse(
            [
                'places' => $this->getContent('places', $search),
                'organizers' => $this->getContent('organizers', $search),
                'tags' => array_reduce($this->getContent('tags', ''), function ($carry, $el) use ($search) {
                    if (strpos(strtolower($el->name), strtolower($search)) !== false) {
                        $carry[] = $el;
                    }
                    return $carry;
                }, []),
            ]
        );
    }

    private function getContent(string $type, $search)
    {
        $client = new Client();

        $result = [];

        $res = $client->request(
            'GET',
            'https://api.detskeriaarhus.dk/api/'.$type,
            [
                'query' => [
                    'name' => $search,
                ],
                'timeout' => 2,
            ]
        );

        $res = json_decode($res->getBody()->getContents());

        $result = array_merge($result, $res->{'hydra:member'} ?? []);

        $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        while ($con) {
            $res = $client->request(
                'GET',
                'https://api.detskeriaarhus.dk'.$res->{'hydra:view'}->{'hydra:next'},
                [
                    'timeout' => 2,
                ]
            );

            $res = json_decode($res->getBody()->getContents());

            $result = array_merge($result, $res->{'hydra:member'});

            $con = $res->{'hydra:view'}->{'hydra:next'} ?? false;
        }

        return $result;
    }

}
