<?php

namespace Os2Display\PosterBundle\Controller;

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

    /**
     * Search for type.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param $type
     * @param $search
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function searchAction(Request $request)
    {
        $search = $request->query->get('search');
        $type = $request->query->get('type');
        $page = $request->query->get('page') ?? null;

        if (empty($search) || empty($type)) {
            return new JsonResponse([]);
        }

        return new JsonResponse(
            $this->get('os2display.poster.service')->search($type, $search, $page)
        );
    }
}
