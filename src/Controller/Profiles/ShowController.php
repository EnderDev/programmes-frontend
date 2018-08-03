<?php
declare(strict_types = 1);

namespace App\Controller\Profiles;

use App\Controller\BaseController;
use App\Controller\Helpers\IsiteKeyHelper;
use App\Ds2013\Presenters\Utilities\Paginator\PaginatorPresenter;
use App\ExternalApi\Isite\Domain\Profile;
use App\ExternalApi\Isite\IsiteResult;
use App\ExternalApi\Isite\Service\IsiteService;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use Symfony\Component\HttpFoundation\Request;

class ShowController extends BaseController
{
    public function __invoke(string $key, string $slug, Request $request, IsiteService $isiteService, IsiteKeyHelper $isiteKeyHelper, CoreEntitiesService $coreEntitiesService)
    {
        $preview = false;
        if ($request->query->has('preview') && $request->query->get('preview')) {
            $preview = true;
        }

        if ($isiteKeyHelper->isKeyAGuid($key)) {
            return $this->redirectWith($isiteKeyHelper->convertGuidToKey($key), $slug, $preview);
        }

        $guid = $isiteKeyHelper->convertKeyToGuid($key);

        /** @var IsiteResult $isiteResult */
        $isiteResult = $isiteService->getByContentId($guid, $preview)->wait(true);
        /** @var Profile $profile */
        $profiles = $isiteResult->getDomainModels();
        if (!$profiles) {
            throw $this->createNotFoundException('No profiles found for guid');
        }

        $profile = reset($profiles);

        if ($slug != $profile->getSlug()) {
            return $this->redirectWith($profile->getKey(), $profile->getSlug(), $preview);
        }

        $context = null;
        if ('' !== $profile->getParentPid()) {
            $context = $coreEntitiesService->findByPidFull(new Pid($profile->getParentPid()));

            if ($profile->getProjectSpace() !== $context->getOption('project_space')) {
                throw $this->createNotFoundException('Project space Profile-Programme not matching');
            }
        }

        $this->setContext($context);

        if ('' !== $profile->getBrandingId()) {
            $this->setBrandingId($profile->getBrandingId());
        }

        if ($profile->isIndividual()) {
            $extraProfiles = $profile->getParents();
            $siblingPromise = $isiteService->setChildProfilesOn($extraProfiles, $profile->getProjectSpace());
            $this->resolvePromises([$siblingPromise]);
            return $this->renderWithChrome('profiles/individual.html.twig', ['profile' => $profile, 'programme' => $context]);
        }

        $response = $isiteService->setChildProfilesOn([$profile], $profile->getProjectSpace(), $this->getPage())->wait(true);
        $extraProfiles = array_merge($profile->getChildren(), $profile->getParents()); // We want to fetch the children of the main profiles children and parents.
        $grandChildPromise = $isiteService->setChildProfilesOn($extraProfiles, $profile->getProjectSpace());
        $this->resolvePromises([$grandChildPromise]);

        $paginator = $this->getPaginator($response[0]);

        return $this->renderWithChrome('profiles/group.html.twig', ['profile' => $profile, 'paginatorPresenter' => $paginator, 'programme' => $context]);
    }

    private function redirectWith(string $key, string $slug, bool $preview)
    {
        $params = ['key' => $key, 'slug' => $slug];

        if ($preview) {
            $params['preview'] = 'true';
        }

        return $this->cachedRedirectToRoute('programme_profile', $params, 301);
    }

    private function getPaginator(IsiteResult $iSiteResult): ?PaginatorPresenter
    {
        if ($iSiteResult->getTotal() <= 48) {
            return null;
        }

        return new PaginatorPresenter($this->getPage(), 48, $iSiteResult->getTotal());
    }
}