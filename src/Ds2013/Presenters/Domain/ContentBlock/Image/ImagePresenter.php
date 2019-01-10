<?php
declare(strict_types = 1);

namespace App\Ds2013\Presenters\Domain\ContentBlock\Image;

use App\Ds2013\Presenters\Domain\ContentBlock\ContentBlockPresenter;
use App\ExternalApi\Isite\Domain\ContentBlock\Image;

class ImagePresenter extends ContentBlockPresenter
{
    public function __construct(Image $imageBlock, bool $inPrimaryColumn, bool $isPrimaryColumnFullWith, array $options = [])
    {
        parent::__construct($imageBlock, $inPrimaryColumn, $isPrimaryColumnFullWith, $options);
    }

    /**
     * @return string Return the right slot size for big screens (desktop)
     */
    public function getFullSizeImageSlot()
    {
        if ($this->isPrimaryColumnFullWith()) {
            return '944px';
        }
        if ($this->isInPrimaryColumn()) {
            return '530px'; // max size it can reach
        }
        // secondary column
        return '397px'; // max size it can reach
    }
}
