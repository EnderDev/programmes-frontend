<?php
declare(strict_types = 1);

namespace App\Controller\Profiles;

use App\Controller\BaseIsiteController;
use App\Controller\Helpers\IsiteKeyHelper;
use App\ExternalApi\Isite\Domain\Profile;
use App\ExternalApi\Isite\Service\ProfileService;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use GuzzleHttp\Promise\FulfilledPromise;
use Symfony\Component\HttpFoundation\Request;

class ShowController extends BaseIsiteController
{
    public function __invoke(
        string $key,
        string $slug,
        Request $request,
        ProfileService $isiteService,
        IsiteKeyHelper $isiteKeyHelper,
        CoreEntitiesService $coreEntitiesService
    ) {
        $this->setIstatsProgsPageType('profiles_index');

        $this->key = $key;
        $this->slug = $slug;
        $this->isiteKeyHelper = $isiteKeyHelper;
        $this->coreEntitiesService = $coreEntitiesService;
        $this->isiteService = $isiteService;

        $preview = $this->getPreview();
        if ($redirect = $this->getRedirectFromGuidToKeyIfNeeded($preview)) {
            return $redirect;
        }

        $guid = $this->isiteKeyHelper->convertKeyToGuid($this->key);

        /** @var Profile $isiteObject */
        $isiteObject = $this->getBaseIsiteObject($guid, $preview);

        if ($redirect = $this->getRedirectToSlugIfNeeded($isiteObject, $preview)) {
            return $redirect;
        }

        $this->removeHttpHeadersForPreview($preview);
        $this->initContextAndBranding($isiteObject, $guid);

        // Calculate siblings display
        $siblingsPromise = new FulfilledPromise(null);
        if ($isiteObject->getParents()) {
            $siblingsPromise = $isiteService->setGroupChildrenOn(
                $isiteObject->getParents(),
                self::MAX_LIST_DISPLAYED_ITEMS
            );
        }

        if ($isiteObject->isIndividual()) {
            $this->resolvePromises(['siblings' => $siblingsPromise]);

            return $this->renderWithChrome('profiles/individual.html.twig', [
                'guid' => $guid,
                'projectSpace' => $isiteObject->getProjectSpace(),
                'profile' => $isiteObject,
                'programme' => $this->getParentProgramme($this->context),
                'maxSiblings' => self::MAX_LIST_DISPLAYED_ITEMS,
            ]);
        }

        // Get the children of the current profile synchronously, as we may need their children also
        $isiteService
            ->setChildrenOn([$isiteObject], $isiteObject->getProjectSpace(), $this->getPage())
            ->wait(true);

        // This will fetch the grandchildren of the current profile given the children fetched
        // in the above query
        $childProfilesThatAreGroups = [];
        foreach ($isiteObject->getChildren() as $childProfile) {
            if ($childProfile->isGroup()) {
                $childProfilesThatAreGroups[] = $childProfile;
            }
        }
        $grandChildrenPromise = $isiteService->setChildrenOn(
            $childProfilesThatAreGroups,
            $isiteObject->getProjectSpace()
        );
        $this->resolvePromises([$grandChildrenPromise, $siblingsPromise]);

        return $this->renderWithChrome('profiles/group.html.twig', [
            'guid' => $guid,
            'projectSpace' => $isiteObject->getProjectSpace(),
            'profile' => $isiteObject,
            'paginatorPresenter' => $this->getPaginator($isiteObject->getChildCount()),
            'programme' => $this->getParentProgramme($this->context),
            'maxSiblings' => self::MAX_LIST_DISPLAYED_ITEMS,
        ]);
    }

    protected function getRouteName()
    {
        return 'programme_profile';
    }
}
