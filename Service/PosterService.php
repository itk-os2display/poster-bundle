<?php

namespace Os2Display\PosterBundle\Service;

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

        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            if ((!isset($options['do_not_update']) || $options['do_not_update'] == false) &&
                isset($options['data']['occurrenceId'])) {
                $cacheKey = sha1($options['data']['occurrenceId']);

                if (isset($cache[$cacheKey])) {
                    $slide->setOptions($cache[$cacheKey]);
                    continue;
                }

                $updatedOccurrence = $this->getOccurrence(
                    $options['data']['occurrenceId']
                );

                if ($updatedOccurrence == false) {
                    $options['do_not_update'] = true;
                    $slide->setOptions($options);
                    continue;
                }

                if (!is_null($updatedOccurrence)) {
                    $options['data'] = $updatedOccurrence;
                    $slide->setOptions($options);

                    $cache[$cacheKey] = $updatedOccurrence;
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get events from providers.
     *
     * @param $query
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
}
