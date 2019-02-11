<?php

namespace App\DsShared\Factory;

use App\DsShared\Helpers\BroadcastNetworksHelper;
use App\DsShared\Helpers\FixIsiteMarkupHelper;
use App\DsShared\Helpers\GuidanceWarningHelper;
use App\DsShared\Helpers\LiveBroadcastHelper;
use App\DsShared\Helpers\LocalisedDaysAndMonthsHelper;
use App\DsShared\Helpers\PlayTranslationsHelper;
use App\DsShared\Helpers\SmpPlaylistHelper;
use App\DsShared\Helpers\StreamableHelper;
use App\DsShared\Helpers\TitleLogicHelper;
use App\Controller\Helpers\ProducerVariableHelper;
use App\Translate\TranslateProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HelperFactory
{
    /** @var TranslateProvider */
    private $translateProvider;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var array */
    private $helpers = [];

    /**
     * This helper factory caches all created objects. DO NOT CREATE HELPERS THAT REQUIRE DATA TO BE CONSTRUCTED.
     * Services are fine, but not per-page data.
     * All functions in this factory should take no parameters. Think of these as a less braindead version
     * of PHP traits.
     */
    public function __construct(TranslateProvider $translateProvider, UrlGeneratorInterface $router)
    {
        $this->translateProvider = $translateProvider;
        $this->router = $router;
    }

    public function getBroadcastNetworksHelper()
    {
        return $this->getHelper(BroadcastNetworksHelper::class, $this->translateProvider);
    }

    public function getFixIsiteMarkupHelper()
    {
        return $this->getHelper(FixIsiteMarkupHelper::class);
    }

    public function getGuidanceWarningHelper(): GuidanceWarningHelper
    {
        return $this->getHelper(GuidanceWarningHelper::class);
    }

    public function getLiveBroadcastHelper(): LiveBroadcastHelper
    {
        return $this->getHelper(LiveBroadcastHelper::class, $this->router);
    }

    public function getLocalisedDaysAndMonthsHelper(): LocalisedDaysAndMonthsHelper
    {
        return $this->getHelper(LocalisedDaysAndMonthsHelper::class, $this->translateProvider);
    }

    public function getPlayTranslationsHelper(): PlayTranslationsHelper
    {
        return $this->getHelper(PlayTranslationsHelper::class, $this->translateProvider);
    }

    public function getSmpPlaylistHelper(): SmpPlaylistHelper
    {
        return $this->getHelper(SmpPlaylistHelper::class, $this->getGuidanceWarningHelper());
    }

    public function getStreamUrlHelper(): StreamableHelper
    {
        return $this->getHelper(StreamableHelper::class);
    }

    public function getTitleLogicHelper(): TitleLogicHelper
    {
        return $this->getHelper(TitleLogicHelper::class);
    }

    public function getProducerVariableHelper(): ProducerVariableHelper
    {
        return $this->getHelper(ProducerVariableHelper::class);
    }

    private function getHelper(string $className, ...$injectables)
    {
        if (!isset($this->helpers[$className])) {
            $this->helpers[$className] = new $className(...$injectables);
        }
        return $this->helpers[$className];
    }
}
