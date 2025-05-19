<?php

namespace App\Service;

use Detection\MobileDetect;

class DeviceDetectorService
{
    private $mobileDetect;

    public function __construct(MobileDetect $mobileDetect)
    {
        $this->mobileDetect = $mobileDetect;
    }

    public function isMobile(): bool
    {
        return $this->mobileDetect->isMobile() && !$this->mobileDetect->isTablet();
    }

    public function isTablet(): bool
    {
        return $this->mobileDetect->isTablet();
    }

    public function isDesktop(): bool
    {
        return !$this->mobileDetect->isMobile() && !$this->mobileDetect->isTablet();
    }
}