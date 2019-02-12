<?php
declare(strict_types = 1);

namespace App\Ds2013\Presenters\Utilities\SMP;

use App\Controller\Helpers\ProducerVariableHelper;
use App\Ds2013\Presenter;
use App\DsShared\Helpers\SmpPlaylistHelper;
use App\DsShared\Helpers\StreamableHelper;
use App\ValueObject\CosmosInfo;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\Entity\Version;
use BBC\ProgrammesPagesService\Domain\Enumeration\MediaTypeEnum;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SmpPresenter extends Presenter
{
    private static $smpInstanceCounter = 0;

    protected $options = [
        'sizes' => [
            0 => 640,
            695 => 976,
        ],
        'srcsets' => [80, 160, 320, 480, 640, 768, 896, 976, 1008],
        'default_width' => 640,
        'uas' => true,
        'autoplay' => true,
        'audio_to_playspace' => true,
    ];

    /** @var ProgrammeItem */
    private $programmeItem;

    /** @var Version */
    private $streamableVersion;

    /** @var array */
    private $segmentEvents;

    /** @var SmpPlaylistHelper */
    private $smpPlaylistHelper;

    /** @var string */
    private $analyticsCounterName;

    /** @var array */
    private $analyticsLabels;

    /** @var UrlGeneratorInterface */
    private $router;

    /** @var CosmosInfo */
    private $cosmosInfo;

    private $streamableHelper;

    /** @var string */
    private $containerId;

    private $producerVariableHelper;

    public function __construct(
        ProgrammeItem $programmeItem,
        ?Version $streamableVersion,
        array $segmentEvents,
        ?string $analyticsCounterName,
        ?array $analyticsLabels,
        SmpPlaylistHelper $smpPlaylistHelper,
        UrlGeneratorInterface $router,
        CosmosInfo $cosmosInfo,
        StreamableHelper $streamableHelper,
        ProducerVariableHelper $producerVariableHelper,
        array $options = []
    ) {
        parent::__construct($options);
        $this->programmeItem = $programmeItem;
        $this->streamableVersion = $streamableVersion;
        $this->segmentEvents = $segmentEvents;
        $this->smpPlaylistHelper = $smpPlaylistHelper;
        $this->analyticsCounterName = $analyticsCounterName;
        $this->analyticsLabels = $analyticsLabels;
        $this->router = $router;
        $this->cosmosInfo = $cosmosInfo;
        $this->streamableHelper = $streamableHelper;
        self::$smpInstanceCounter++;
        $this->containerId = 'playout-' . (string) $this->programmeItem->getPid() . self::$smpInstanceCounter;
        $this->producerVariableHelper = $producerVariableHelper;
    }

    public function getProgrammeItem(): ProgrammeItem
    {
        return $this->programmeItem;
    }

    public function getContainerId()
    {
        return $this->containerId;
    }

    /**
     * @return string[]
     */
    public function getSmpConfig(): array
    {
        $smpPlaylist = $this->smpPlaylistHelper->getSmpPlaylist(
            $this->programmeItem,
            $this->streamableVersion
        );

        $series = null;
        $episode = null;
        foreach ($this->programmeItem->getAncestry() as $parent){
            if($parent->getType() == 'episode'){
                $episode = (string) $parent->getPid();
            }
            elseif ($parent->getType() == 'series'){
                $series = (string) $parent->getPid();
            }
        }

        $smpConfig = [
            'container' => '#' . $this->getContainerId(),
            'pid' => (string) $this->programmeItem->getPid(),
            'smpSettings' => [
                'autoplay' => $this->options['autoplay'],
                'ui' => [
                    'controls' => [
                        'enabled' => true,
                        'always' => $this->programmeItem->getMediaType() ==  MediaTypeEnum::AUDIO ? true: false,
                    ],
                    'fullscreen' => [
                        'enabled' => $this->programmeItem->getMediaType() ==  MediaTypeEnum::AUDIO ? false: true,
                    ],
                ],
                'playlistObject' => $smpPlaylist,
                'externalEmbedUrl' => $this->router->generate('programme_player', ['pid' => (string) $this->programmeItem->getPid()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'markers' => $this->smpPlaylistHelper->getMarkers($this->segmentEvents, $this->programmeItem),
        ];

        if (!empty($this->analyticsCounterName)) {
            $smpConfig['smpSettings']['counterName'] = $this->analyticsCounterName;
        }

        $brand = $this->programmeItem->getTleo();
        if (!empty($this->analyticsLabels)) {
            $smpConfig['smpSettings']['statsObject'] = [
                'siteId' => $this->analyticsLabels['bbc_site'] ?? '',
                'product' => $this->analyticsLabels['prod_name'],



                //Vulcan stats
                'Content' => (string) $this->programmeItem->getPid(),
                'brandPID' => (string) $brand->getPid(),
                'appName' => $this->analyticsLabels['app_name'],
                'seriesPID' => $series,
                'appType' => 'responsive',
                'clipPID' => (string) $this->programmeItem->getPid(),
                'producer' => $this->producerVariableHelper->calculateProducerVariable($this->programmeItem),
                'parentPIDType' => $this->programmeItem->getType(),
                'episodePID' => $episode,

                'name' => $this->programmeItem->getTitle(),


                'sessionLabels' => [
                    'bbc_site' => $this->analyticsLabels['bbc_site'] ?? '',
                    'event_master_brand' => $this->analyticsLabels['event_master_brand'] ?? '',
                ],
            ];
        }

        return $smpConfig;
    }

    public function getFactoryOptions(): array
    {
        return [
            'uasConfig' => $this->options['uas'] ? $this->getUasConfig() : null,
        ];
    }

    public function shouldStreamViaPlayspace(): bool
    {
        if ($this->options['audio_to_playspace'] && $this->streamableHelper->shouldStreamViaPlayspace($this->programmeItem)) {
            return true;
        }
        return false;
    }

    /**
     * @return string[]
     */
    private function getUasConfig(): array
    {
        $cosmosEnv = $this->cosmosInfo->getAppEnvironment();
        $uasEnv = 'live';
        $password = 'rt5uf8v9aol56';

        if ($cosmosEnv !== 'live') {
            // V2 set "test" environment even for sandbox.
            $uasEnv = 'test';
            $password = 'bapd63mcqopnp';
        }

        return [
            'apiKey' => $password,
            'env' => $uasEnv,
            'pid' => (string) $this->programmeItem->getPid(),
            'versionPid' => (string) $this->streamableVersion->getPid(),
            'resourceDomain' => $this->programmeItem->isTv() ? 'tv' : 'radio',
            'resourceType' => $this->programmeItem->getType(),
        ];
    }
}
