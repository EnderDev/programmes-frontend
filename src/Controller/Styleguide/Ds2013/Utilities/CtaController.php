<?php
declare(strict_types = 1);

namespace App\Controller\Styleguide\Ds2013\Utilities;

use App\Builders\ClipBuilder;
use App\Builders\CollapsedBroadcastBuilder;
use App\Builders\CollectionBuilder;
use App\Builders\EpisodeBuilder;
use App\Builders\GalleryBuilder;
use App\Builders\MasterBrandBuilder;
use App\Builders\ServiceBuilder;
use App\Controller\Styleguide\Ds2013\StyleGuideBaseController;
use App\Ds2013\Presenters\Utilities\Cta\LiveCtaPresenter;
use App\DsShared\Factory\HelperFactory;
use BBC\ProgrammesPagesService\Domain\Enumeration\MediaTypeEnum;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Domain\ValueObject\Sid;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CtaController extends StyleGuideBaseController
{
    public function __invoke(
        Request $request,
        CoreEntitiesService $coreEntitiesService,
        HelperFactory $helperFactory,
        UrlGeneratorInterface $router
    ) {
        parent::__construct();
        if ($request->query->has('branding_context')) {
            $coreEntity = $coreEntitiesService->findByPidFull(new Pid($request->query->get('branding_context')));
            $this->setContextAndPreloadBranding($coreEntity);
        }

        $tvMasterbrand = MasterBrandBuilder::anyTVMasterBrand()->build();
        $radioMasterbrand = MasterBrandBuilder::anyRadioMasterBrand()->build();
        $groups = [];
        $clips = [];
        $clips['Audio'] = ClipBuilder::any()->with(['mediaType' => MediaTypeEnum::AUDIO])->build();
        $clips['Video'] = ClipBuilder::any()->with(['mediaType' => MediaTypeEnum::VIDEO])->build();
        $clips['Unknown (No Network)'] = ClipBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN])->build();
        $clips['Unknown (TV Network)'] = ClipBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN, 'masterBrand' => $tvMasterbrand])->build();
        $clips['Unknown (Radio Network)'] = ClipBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN, 'masterBrand' => $radioMasterbrand])->build();
        $episodes = [];
        $episodes['Audio'] = EpisodeBuilder::any()->with(['mediaType' => MediaTypeEnum::AUDIO])->build();
        $episodes['Video'] = EpisodeBuilder::any()->with(['mediaType' => MediaTypeEnum::VIDEO])->build();
        $episodes['Unknown (No Network)'] = EpisodeBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN])->build();
        $episodes['Unknown (TV Network)'] = EpisodeBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN, 'masterBrand' => $tvMasterbrand])->build();
        $episodes['Unknown (Radio Network)'] = EpisodeBuilder::any()->with(['mediaType' => MediaTypeEnum::UNKNOWN, 'masterBrand' => $radioMasterbrand])->build();
        $groups['Gallery'] = GalleryBuilder::any()->build();
        $groups['Collection'] = CollectionBuilder::any()->build();

        $gallery = $coreEntitiesService->findByPidFull(new Pid('p019kj0y'));
        $collection = $coreEntitiesService->findByPidFull(new Pid('p03f6313'));

        $radioServices = [ServiceBuilder::any()->with(['sid' => new Sid('bbc_radio_one')])->build()];
        $tvServices = [ServiceBuilder::any()->with(['sid' => new Sid('bbc_one_london')])->build()];
        $radioCollapsedBroadcast = CollapsedBroadcastBuilder::anyLive()->with(['isBlanked' => false, 'services' => $radioServices, 'programmeItem' => $episodes['Audio']])->build();
        $tvCollapsedBroadcast = CollapsedBroadcastBuilder::anyLive()->with(['isBlanked' => false, 'services' => $tvServices, 'programmeItem' => $episodes['Video']])->build();

        $radioCollapsedBroadcastPresenter = new LiveCtaPresenter(
            $radioCollapsedBroadcast,
            null,
            $helperFactory->getPlayTranslationsHelper(),
            $router,
            $helperFactory->getStreamUrlHelper(),
            $helperFactory->getLiveBroadcastHelper(),
            []
        );

        $tvCollapsedBroadcastPresenter = new LiveCtaPresenter(
            $tvCollapsedBroadcast,
            null,
            $helperFactory->getPlayTranslationsHelper(),
            $router,
            $helperFactory->getStreamUrlHelper(),
            $helperFactory->getLiveBroadcastHelper(),
            []
        );

        return $this->renderWithChrome(
            'styleguide/ds2013/Utilities/cta.html.twig',
            [
                'radioCollapsedBroadcastPresenter' => $radioCollapsedBroadcastPresenter,
                'tvCollapsedBroadcastPresenter' => $tvCollapsedBroadcastPresenter,
                'clips' => $clips,
                'episodes' => $episodes,
                'groups' => $groups,
                'gallery' => $gallery,
                'collection' => $collection,
            ]
        );
    }
}
