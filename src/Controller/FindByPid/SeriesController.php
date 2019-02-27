<?php
declare(strict_types = 1);
namespace App\Controller\FindByPid;

use App\Controller\BaseController;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;

class SeriesController extends BaseController
{
    public function __invoke(ProgrammeContainer $programme)
    {
        $this->setIstatsProgsPageType('programmes_series');
        $this->setContextAndPreloadBranding($programme);
        $this->setAtiContentLabels('foo', 'bar');

        return $this->renderWithChrome('find_by_pid/example_entity.html.twig', [
            'programme' => $programme,
        ]);
    }
}
