<?php
declare (strict_types = 1);

namespace App\ValueObject;

use App\Cosmos\Dials;
use BBC\ProgrammesPagesService\Domain\Entity\Service;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use BBC\ProgrammesPagesService\Domain\Enumeration\NetworkMediumEnum;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;

class AtiAnalyticsLabels
{
    /** @var mixed */
    private $context;

    /** @var string */
    private $pageType;

    /** @var string */
    private $appEnvironment;

    /** @var array */
    private $extraLabels;

    /** @var mixed */
    private $dials;

    /** @var string */
    private $contentId;

    public function __construct($context, string $progsPageType, CosmosInfo $cosmosInfo, array $extraLabels, Dials $dials, string $contentId = null)
    {
        $this->context = $context;
        $this->pageType = $progsPageType;
        $this->appEnvironment = $cosmosInfo->getAppEnvironment();
        $this->extraLabels = $extraLabels;
        $this->dials = $dials;
        $this->contentId = $contentId;
    }

    public function orbLabels()
    {

        $labels = [
            'destination' => $this->getDestination(),
            'producer' => $this->calculateProducerVariable(),
            'contentType' => $this->pageType,
            'contentId' => $this->contentId,
            'additionalProperties' => [
                ['name' => 'app_name', 'value' => 'programmes'],
                ['name' => 'custom_var_2', 'value' => $this->context->getTleo()->getTitle()],
                ['name' => 'custom_var_3', 'value' => $this->getConcatenatedAncestryTitles($this->context)],
                ['name' => 'custom_var_4', 'value' => $this->context->getMasterbrand()->getMid()],
            ],
        ];

        $labels = array_merge($labels, $this->extraLabels);

        return $labels;
    }

    private function getDestination(): string
    {
        $destination =  'programmes_ps';

        if (method_exists($this->context, 'getNetwork') && $this->context->getNetwork()
            && (
                in_array((string) $this->context->getNetwork()->getNid(), ['bbc_world_service', 'bbc_world_service_tv', 'bbc_learning_english'])
                || $this->context->getNetwork()->isWorldServiceInternational()
            )
        ) {
            $destination = 'ws_programmes';
        }

//        if (in_array($this->appEnvironment, ['int', 'stage', 'sandbox', 'test'])) {
        if ($this->dials->get('ati-bucket') === 'test') {
            $destination .= '_test';
        }
//        }

        return $destination;
    }

    private function calculateProducerVariable() : string
    {
        $producersMap = [
            'bbc_afrique_radio' => 'AFRIQUE',
            'bbc_afrique_tv' => 'AFRIQUE',
            'bbc_amharic_radio' => 'AMHARIC',
            'bbc_arabic_radio' => 'ARABIC',
            'bbc_arabic_tv' => 'ARABIC',
            'bbc_arts' => 'BBC_ARTS',
            'bbc_brasil' => 'BRASIL',
            'bbc_burmese_radio' => 'BURMESE',
            'bbc_burmese_tv' => 'BURMESE',
            'bbc_cantonese_radio' => 'CHINESE',
            'bbc_cymru' => 'WALES',
            'bbc_dari_radio' => 'PERSIAN',
            'bbc_gahuza_radio' => 'GAHUZA',
            'bbc_gujarati_tv' => 'GUJARATI',
            'bbc_hausa_radio' => 'HAUSA',
            'bbc_hausa_tv' => 'HAUSA',
            'bbc_hindi_radio' => 'HINDI',
            'bbc_hindi_tv' => 'HINDI',
            'bbc_igbo_radio' => 'IGBO',
            'bbc_igbo_tv' => 'IGBO',
            'bbc_indonesian_radio' => 'INDONESIAN',
            'bbc_korean_radio' => 'KOREAN',
            'bbc_korean_tv' => 'KOREAN',
            'bbc_kyrgyz_radio' => 'KYRGYZ',
            'bbc_kyrgyz_tv' => 'KYRGYZ',
            'bbc_marathi_tv' => 'MARATHI',
            'bbc_nepali_radio' => 'NEPALI',
            'bbc_news' => 'NEWS',
            'bbc_news24' => 'NEWS',
            'bbc_oromo_radio' => 'AFAAN_OROMOO',
            'bbc_pashto_radio' => 'PASHTO',
            'bbc_pashto_tv' => 'PASHTO',
            'bbc_persian_radio' => 'PERSIAN',
            'bbc_persian_tv' => 'PERSIAN',
            'bbc_pidgin_radio' => 'PIDGIN',
            'bbc_pidgin_tv' => 'PIDGIN',
            'bbc_punjabi_tv' => 'PUNJABI',
            'bbc_russian_radio' => 'RUSSIAN',
            'bbc_russian_tv' => 'RUSSIAN',
            'bbc_sinhala_radio' => 'SINHALA',
            'bbc_somali_radio' => 'SOMALI',
            'bbc_somali_tv' => 'SOMALI',
            'bbc_sport' => 'SPORT',
            'bbc_swahili_radio' => 'SWAHILI',
            'bbc_swahili_tv' => 'SWAHILI',
            'bbc_tamil_radio' => 'TAMIL',
            'bbc_tamil_tv' => 'TAMIL',
            'bbc_telugu_tv' => 'TELUGU',
            'bbc_thai' => 'THAI',
            'bbc_tigrinya_radio' => 'TIGRINYA',
            'bbc_ukrainian_tv' => 'UKRAINIAN',
            'bbc_urdu_radio' => 'URDU',
            'bbc_urdu_tv' => 'URDU',
            'bbc_uzbek_radio' => 'UZBEK',
            'bbc_uzbek_tv' => 'UZBEK',
            'bbc_wales' => 'WALES',
            'bbc_weather' => 'WEATHER',
            'bbc_world_news' => 'BBC_WORLD_NEWS',
            'bbc_world_service' => 'WORLD_SERVICE_ENGLISH',
            'bbc_yoruba_radio' => 'YORUBA',
            'bbc_yoruba_tv' => 'YORUBA',
            'cbbc' => 'CBBC',
            'cbeebies' => 'CBEEBIES',
            'cbeebies_radio' => 'CBEEBIES',
        ];

        $network = null;
        $id = null;
        if ($this->context instanceof Service) {
            $network = $this->context->getNetwork();
            $id = $this->context->getNetwork() ? (string) $this->context->getNetwork()->getNid() : null;
        } elseif ($this->context instanceof CoreEntity) {
            $network = $this->context->getNetwork();
            $id = $this->context->getMasterBrand() ? (string) $this->context->getMasterBrand()->getMid() : null;
        }

        if (array_key_exists($id, $producersMap)) {
            return $producersMap[$id];
        }

        if ($network && $network->getMedium() === NetworkMediumEnum::RADIO) {
            return 'SOUNDS';
        }

        if ($network && $network->getMedium() === NetworkMediumEnum::TV) {
            return 'IPLAYER';
        }

        return 'BBC';
    }

    /**
     * This function uses recursion to climb up the parents tree and return a concatenation of the titles
     */
    private function getConcatenatedAncestryTitles(? Programme $context) : string
    {
        $titlesConcatenation = implode(', ', array_map(function ($ancestry) {
            return $ancestry->getTitle();
        }, array_reverse($context->getAncestry())));
        return $titlesConcatenation;
    }
}
