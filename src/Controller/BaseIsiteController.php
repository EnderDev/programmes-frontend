<?php

namespace App\Controller;

use App\Controller\Helpers\IsiteKeyHelper;
use App\Ds2013\Presenters\Utilities\Paginator\PaginatorPresenter;
use App\ExternalApi\Isite\Domain\BaseIsiteObject;
use App\ExternalApi\Isite\Domain\IsiteImage;
use App\ExternalApi\Isite\Service\IsiteService;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\ExternalApi\Isite\IsiteResult;
use BBC\ProgrammesPagesService\Domain\Entity\Group;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseIsiteController extends BaseController
{
    protected const MAX_LIST_DISPLAYED_ITEMS = 48;

    /** @var IsiteKeyHelper */
    protected $isiteKeyHelper;

    /** @var CoreEntitiesService */
    protected $coreEntitiesService;

    /** @var IsiteService */
    protected $isiteService;

    /** @var string */
    protected $key;

    /** @var string */
    protected $slug;

    abstract protected function getRouteName();

    protected function getRedirectFromGuidToKeyIfNeeded(bool $preview): ?Response
    {
        if ($this->isiteKeyHelper->isKeyAGuid($this->key)) {
            $key = $this->isiteKeyHelper->convertGuidToKey($this->key);
            return $this->redirectWith($key, $this->slug, $preview, $this->getRouteName());
        }

        return null;
    }

    protected function getRedirectToSlugIfNeeded(BaseIsiteObject $isiteObject, bool $preview): ?Response
    {
        if ($this->slug !== $isiteObject->getSlug()) {
            return $this->redirectWith(
                $isiteObject->getKey(),
                $isiteObject->getSlug(),
                $preview,
                $this->getRouteName()
            );
        }

        return null;
    }

    protected function getBaseIsiteObject(string $guid, bool $preview): BaseIsiteObject
    {
        /** @var IsiteResult $isiteResult */
        $isiteResult = $this->isiteService->getByContentId($guid, $preview)->wait(true);
        $isiteObjects = $isiteResult->getDomainModels();
        if (!$isiteObjects) {
            throw $this->createNotFoundException('No resource found for guid');
        }

        return reset($isiteObjects);
    }

    protected function getPreview(): bool
    {
        return $this->request()->query->has('preview') && $this->request()->query->get('preview');
    }

    protected function removeHttpHeadersForPreview(bool $preview): void
    {
        if ($preview) {
            $this->metaNoIndex = true;
            $this->response()->headers->remove('X-Frame-Options');
        }
    }

    protected function redirectWith(string $key, string $slug, bool $preview, string $routeName): RedirectResponse
    {
        $params = ['key' => $key, 'slug' => $slug];

        if ($preview) {
            $params['preview'] = 'true';
        }

        return $this->cachedRedirectToRoute($routeName, $params, 301);
    }

    protected function getPaginator(int $totalItems): ?PaginatorPresenter
    {
        if ($totalItems <= self::MAX_LIST_DISPLAYED_ITEMS) {
            return null;
        }

        return new PaginatorPresenter($this->getPage(), self::MAX_LIST_DISPLAYED_ITEMS, $totalItems);
    }
    
    protected function getParentProgramme($context)
    {
        if ($context instanceof Group) {
            return $context->getParent();
        }

        return $context;
    }

    protected function initContextAndBranding(BaseIsiteObject $isiteObject, string $guid): void
    {
        $this->initContext($isiteObject);
        $this->initIstatsLabels($isiteObject);
        $this->setAtiContentId($guid, 'isite2');
        $this->initBranding($isiteObject);
    }

    private function initContext(BaseIsiteObject $isiteObject): void
    {
        $context = null;
        if (!empty($isiteObject->getParentPid())) {
            $context = $this->coreEntitiesService->findByPidFull($isiteObject->getParentPid());
            if ($context && ($isiteObject->getProjectSpace() !== $context->getOption('project_space'))) {
                throw $this->createNotFoundException('Project space in Article or Profile not matching');
            }
        }
        $this->setContext($context);

        $shortSynopsis = trim($isiteObject->getShortSynopsis());
        if (!empty($shortSynopsis)) {
            $this->overridenDescription = $shortSynopsis;
        }

        $imagePid = $isiteObject->getImage();
        if ($imagePid) {
            $this->overridenImage = new IsiteImage(new Pid($imagePid));
        }
    }

    private function initIstatsLabels(BaseIsiteObject $isiteObject): void
    {
        if ($isiteObject->getBbcSite()) {
            $this->setIstatsExtraLabels(['bbc_site' => $isiteObject->getBbcSite()]);
        }
    }

    private function initBranding(BaseIsiteObject $isiteObject): void
    {
        if ('' !== $isiteObject->getBrandingId()) {
            $this->setBrandingId($isiteObject->getBrandingId());
        }
    }
}
