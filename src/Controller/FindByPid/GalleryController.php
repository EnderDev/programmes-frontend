<?php
declare(strict_types = 1);
namespace App\Controller\FindByPid;

use App\Controller\BaseController;
use App\Controller\Gallery\GalleryView;
use App\Controller\Helpers\StructuredDataHelper;
use BBC\ProgrammesPagesService\Domain\Entity\Gallery;
use BBC\ProgrammesPagesService\Domain\Entity\Image;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Service\ImagesService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GalleryController extends BaseController
{
    public function __invoke(
        Gallery $gallery,
        ImagesService $imagesService,
        ?string $imagePid,
        ProgrammesAggregationService $programmesAggregationService,
        StructuredDataHelper $structuredDataHelper
    ) {
        $this->setIstatsProgsPageType('galleries_show');
        $this->setAtiContentLabels('article-photo-gallery', 'gallery');
        $this->setContextAndPreloadBranding($gallery);
        $siblingLimit = 4;
        $images = $imagesService->findByGroup($gallery);
        $image = $this->getFirstImage($imagePid, $images);
        $programme = $gallery->getParent();
        $brand = null;
        $galleries = null;
        if ($programme) {
            $brand = $programme->getTleo();
            $galleries = $programmesAggregationService->findDescendantGalleries($brand, $siblingLimit);
        }
        $hasImageHighlighted = !empty($imagePid);
        $this->setAtiContentId((string) $gallery->getPid(), 'pips');

        $schema = $this->getSchema($structuredDataHelper, $images, $programme, $gallery);

        return $this->renderWithChrome('find_by_pid/gallery.html.twig', [
            'schema' => $schema,
            'gallery' => $gallery,
            'programme' => $programme,
            'image' => $image,
            'images' => $images,
            'galleries' => $galleries,
            'brand' => $brand,
            'hasImageHighlighted' => $hasImageHighlighted,
        ]);
    }

    public function getFirstImage(?string $imagePid, array $images): ?Image
    {
        if (empty($images)) {
            return null;
        }
        if (!$imagePid) {
            return reset($images);
        }
        $image = null;
        foreach ($images as $eachImage) {
            if (((string) $eachImage->getPid()) === $imagePid) {
                $image = $eachImage;
            }
        }
        if (!$image) {
            throw new NotFoundHttpException('Image not found.');
        }
        return $image;
    }

    public function getSchema(StructuredDataHelper $structuredDataHelper, array $images, Programme $programme, Gallery $gallery)
    {
        $schema = $structuredDataHelper->getSchemaForGallery($gallery, $programme);
        foreach ($images as $image) {
            $schema['hasPart'][] = $structuredDataHelper->getSchemaForImage($image, $programme, $gallery, false);
        }

        return $schema;
    }
}
