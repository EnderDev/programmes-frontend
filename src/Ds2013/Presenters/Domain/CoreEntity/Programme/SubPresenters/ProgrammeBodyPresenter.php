<?php
declare(strict_types = 1);

namespace App\Ds2013\Presenters\Domain\CoreEntity\Programme\SubPresenters;

use App\Ds2013\Presenters\Domain\CoreEntity\Programme\ProgrammePresenterBase;
use App\DsShared\Helpers\LocalisedDaysAndMonthsHelper;
use App\DsShared\Helpers\PlayTranslationsHelper;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\MasterBrand;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use Cake\Chronos\Chronos;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sub-presenter for ProgrammePresenter
 */
class ProgrammeBodyPresenter extends ProgrammePresenterBase
{
    /** @var array */
    protected $options = [
        'show_synopsis' => true,
        'show_duration' => false,
        'wordy_duration' => true,
        'body_suffix' => null,
        'show_child_availability' => false,
        'show_release_date' => false,
        'show_masterbrand' => false,
    ];

    /** @var PlayTranslationsHelper */
    protected $playTranslationsHelper;

    /** @var LocalisedDaysAndMonthsHelper  */
    protected $localisedDaysAndMonthsHelper;


    public function __construct(
        UrlGeneratorInterface $router,
        PlayTranslationsHelper $playTranslationsHelper,
        LocalisedDaysAndMonthsHelper $localisedDaysAndMonthsHelper,
        Programme $programme,
        array $options = []
    ) {
        // Clips show duration by default
        if ($programme instanceof Clip) {
            $this->options['show_duration'] = true;
        }
        parent::__construct($router, $programme, $options);
        $this->playTranslationsHelper = $playTranslationsHelper;
        $this->localisedDaysAndMonthsHelper = $localisedDaysAndMonthsHelper;
    }

    public function getDurationInWords(): string
    {
        if (!$this->programme instanceof ProgrammeItem) {
            throw new InvalidArgumentException("Cannot get duration for non-programmeitem");
        }
        return $this->playTranslationsHelper->secondsToWords($this->programme->getDuration());
    }

    public function getFormattedDuration(): string
    {
        if (!$this->programme instanceof ProgrammeItem) {
            throw new InvalidArgumentException("Cannot get duration for non-programmeitem");
        }

        return $this->playTranslationsHelper->secondsToFormattedDuration($this->programme->getDuration());
    }

    public function getSynopsisTruncationLength()
    {
        if ($this->options['truncation_length']) {
            return $this->options['truncation_length'] * 1.5;
        }
        return null;
    }

    public function hasDefinedPositionUnderParentProgramme(): bool
    {
        $parent = $this->programme->getParent();
        if ($parent instanceof ProgrammeContainer
            && !is_null($parent->getExpectedChildCount())
            && !is_null($this->programme->getPosition())
        ) {
            return true;
        }
        return false;
    }

    public function shouldShowDuration(): bool
    {
        return (
            $this->options['show_duration'] &&
            $this->programme instanceof ProgrammeItem &&
            $this->programme->getDuration()
        );
    }

    public function containerHasAvailableEpisodes()
    {
        if ($this->programme->isTlec() && $this->programme instanceof ProgrammeContainer) {
            return $this->programme->getAvailableEpisodesCount() > 0;
        }
        return false;
    }

    public function hasDisplayDate(): bool
    {
        if ($this->options['show_release_date'] && $this->getDisplayDate()) {
            return true;
        }
        return false;
    }

    public function getDisplayDate(): string
    {
        if ($this->getOption('show_release_date') && $this->programme instanceof ProgrammeItem) {
            if ($this->programme->getReleaseDate()) {
                return $this->localisedDaysAndMonthsHelper->getFormatedPartialDate($this->programme->getReleaseDate());
            }
            if ($this->programme->getFirstBroadcastDate()) {
                return $this->localisedDaysAndMonthsHelper->getFormatedDay($this->programme->getFirstBroadcastDate());
            }
            if ($this->programme->getStreamableFrom()) {
                return $this->localisedDaysAndMonthsHelper->getFormatedDay($this->programme->getStreamableFrom());
            }
        }
        return "";
    }

    public function getDisplayMasterbrand(): ?MasterBrand
    {
        if (!$this->getOption('show_masterbrand')) {
            return null;
        }
        $programmeMasterbrand = $this->programme->getMasterBrand();
        $contextMasterbrand = $this->getOption('context_programme') ? $this->getOption('context_programme')->getMasterbrand() : null;
        if ($programmeMasterbrand != $contextMasterbrand) {
            return $programmeMasterbrand;
        }
        return null;
    }
}
