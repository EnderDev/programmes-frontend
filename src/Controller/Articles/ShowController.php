<?php
declare(strict_types = 1);

namespace App\Controller\Articles;

use App\Controller\BaseIsiteController;
use App\Controller\Helpers\IsiteKeyHelper;
use App\Controller\Helpers\StructuredDataHelper;
use App\Ds2013\Presenters\Utilities\Paginator\PaginatorPresenter;
use App\ExternalApi\Isite\Domain\Article;
use App\ExternalApi\Isite\IsiteResult;
use App\ExternalApi\Isite\Service\ArticleService;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Group;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use App\Exception\HasContactFormException;
use Symfony\Component\HttpFoundation\Request;

class ShowController extends BaseIsiteController
{
    public function __invoke(
        string $key,
        string $slug,
        Request $request,
        ArticleService $isiteService,
        IsiteKeyHelper $isiteKeyHelper,
        CoreEntitiesService $coreEntitiesService,
        StructuredDataHelper $structuredDataHelper
    ) {
        $this->setIstatsProgsPageType('article_show');
        $this->setAtiContentLabels('article-show-related', 'article');

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
        try {
            $isiteObject = $this->getBaseIsiteObject($guid, $preview);
        } catch (HasContactFormException $e) {
            if (!$this->slug) {
                $route = 'article_with_contact_form_noslug';
                $params = ['key' => $this->key];
            } else {
                $route = 'article_with_contact_form';
                $params = ['key' => $this->key, 'slug' => $this->slug];
            }

            return $this->cachedRedirectToRoute($route, $params, 302, 3600);
        }

        if ($redirect = $this->getRedirectToSlugIfNeeded($isiteObject, $preview)) {
            return $redirect;
        }

        $this->removeHeadersForPreview($preview);
        $this->initContextAndBranding($isiteObject, $guid);
        $programme = $this->getParentProgramme($this->context);
        $parents = $isiteObject->getParents();
        $siblingPromise = $isiteService->setChildrenOn($parents, $isiteObject->getProjectSpace()); //if more than 48, extras are removed
        $childPromise = $isiteService->setChildrenOn([$isiteObject], $isiteObject->getProjectSpace(), $this->getPage());
        $response = $this->resolvePromises(['children' => $childPromise, 'siblings' => $siblingPromise]);

        $schema = $this->getSchema($structuredDataHelper, $isiteObject, $programme);

        $children = reset($response['children']);
        $paginatorPresenter = null;
        if ($children) {
            $paginatorPresenter = $this->getPaginator($children->getTotal());
        }
        return $this->renderWithChrome(
            'articles/show.html.twig',
            [
                'schema' => $schema,
                'guid' => $guid,
                'projectSpace' => $isiteObject->getProjectSpace(),
                'programme' => $programme,
                'article' => $isiteObject,
                'paginatorPresenter' => $paginatorPresenter,
            ]
        );
    }

    protected function getRouteName()
    {
        return 'programme_article';
    }

    private function getSchema(StructuredDataHelper $structuredDataHelper, Article $article, CoreEntity $programme)
    {
        $schema = $structuredDataHelper->getSchemaForArticle($article, $programme);
        return $schema;
    }
}
