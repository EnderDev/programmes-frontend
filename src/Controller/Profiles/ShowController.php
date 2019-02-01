<?php
declare(strict_types = 1);

namespace App\Controller\Profiles;

use App\Controller\BaseIsiteController;
use App\Controller\Helpers\IsiteKeyHelper;
use App\Controller\Helpers\StructuredDataHelper;
use App\Ds2013\Presenters\Utilities\Paginator\PaginatorPresenter;
use App\ExternalApi\Isite\Domain\Profile;
use App\ExternalApi\Isite\Service\ProfileService;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Group;
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
        CoreEntitiesService $coreEntitiesService,
        StructuredDataHelper $structuredDataHelper
    ) {
        $this->setIstatsProgsPageType('profiles_index');
        $this->setAtiContentLabels('profile', 'profile');

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

        $this->removeHeadersForPreview($preview);
        $this->initContextAndBranding($isiteObject, $guid);

        $programme = $this->getParentProgramme($this->context);
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

            $schema = $this->getSchema($structuredDataHelper, $isiteObject, $programme);

            return $this->renderWithChrome('profiles/individual.html.twig', [
                'schema' => $schema,
                'guid' => $guid,
                'projectSpace' => $isiteObject->getProjectSpace(),
                'profile' => $isiteObject,
                'programme' => $programme,
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

        $schema = $this->getSchema($structuredDataHelper, $isiteObject, $programme);

        return $this->renderWithChrome('profiles/group.html.twig', [
            'schema' => $schema,
            'guid' => $guid,
            'projectSpace' => $isiteObject->getProjectSpace(),
            'profile' => $isiteObject,
            'paginatorPresenter' => $this->getPaginator($isiteObject->getChildCount()),
            'programme' => $programme,
            'maxSiblings' => self::MAX_LIST_DISPLAYED_ITEMS,
        ]);
    }

    protected function getRouteName()
    {
        return 'programme_profile';
    }

    private function getSchema(StructuredDataHelper $structuredDataHelper, Profile $profile, CoreEntity $programme)
    {
        if ($profile->isIndividual()) {
            $schema = $structuredDataHelper->getSchemaForCharacter($profile, $programme);
            return $schema;
        }
        if ($programme->getType() == 'ProgrammeContainer') {
            $schema = $structuredDataHelper->getSchemaForProgrammeContainer($programme);
        } elseif ($programme->getType() == 'Episode') {
            $schema = $structuredDataHelper->getSchemaForEpisode($programme, true);
        }
        $characters = [];
        foreach ($profile->getChildren() as $family) {
            foreach ($family->getChildren() as $profile) {
                $characters[$profile->getTitle()] =
                    $structuredDataHelper->getSchemaForCharacter($profile, $programme, false);
            };
        }
        $schema['hasPart'] = $characters;
        return $schema;
    }
}
