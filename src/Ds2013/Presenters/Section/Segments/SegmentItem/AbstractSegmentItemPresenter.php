<?php
declare(strict_types=1);

namespace App\Ds2013\Presenters\Section\Segments\SegmentItem;

use App\Ds2013\Presenter;

abstract class AbstractSegmentItemPresenter extends Presenter
{
    protected $options = [
        'moreless_class' => '',
        'h_tag' => 'h3',
    ];

    public function __construct(array $options = [])
    {
        parent::__construct($options);
    }

    abstract public function getTitle(): ?string;
}
