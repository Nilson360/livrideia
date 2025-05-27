<?php

namespace App\EventSubscriber;

// src/EventSubscriber/DeviceDetectorSubscriber.php
namespace App\EventSubscriber;

use App\Service\DeviceDetectorService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class DeviceDetectorSubscriber implements EventSubscriberInterface
{
    private $deviceDetector;
    private $twig;

    public function __construct(DeviceDetectorService $deviceDetector, Environment $twig)
    {
        $this->deviceDetector = $deviceDetector;
        $this->twig = $twig;
    }

    public function onKernelController(ControllerEvent $event)
    {
        // Ajouter les variables globales à Twig
        $this->twig->addGlobal('isMobile', $this->deviceDetector->isMobile());
        $this->twig->addGlobal('isTablet', $this->deviceDetector->isTablet());
        $this->twig->addGlobal('isDesktop', $this->deviceDetector->isDesktop());
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}