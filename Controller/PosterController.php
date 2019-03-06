<?php

namespace Os2Display\PosterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PosterController extends Controller
{
    /**
     * Test function.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function testAction()
    {
        return new JsonResponse();
    }

    /**
     * @TODO
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function eventsAction(Request $request)
    {
        $query = $request->query->all();
        return new JsonResponse($this->get('os2display.poster.service')->getEvents($query));
    }

    /**
     * @TODO
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function eventAction(Request $request, $id)
    {
        return new JsonResponse($this->get('os2display.poster.service')->getEvent($id));
    }

    /**
     * @TODO
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function occurrenceAction(Request $request, $id)
    {
        return new JsonResponse($this->get('os2display.poster.service')->getOccurrence($id));
    }
}
