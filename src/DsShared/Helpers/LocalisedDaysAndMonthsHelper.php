<?php
declare(strict_types = 1);

namespace App\DsShared\Helpers;

use App\Translate\TranslatableTrait;
use App\Translate\TranslateProvider;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\ValueObject\PartialDate;
use Cake\Chronos\Chronos;
use DateTime;
use IntlDateFormatter;
use RMP\Translate\DateCorrection;

/**
 * View helper - Creates complete lists of localised days of the week and months
 * Available collators in PHP:
 * http://stackoverflow.com/questions/9422553/list-of-available-collators-in-php/9422745#9422745
 *
 * @author BBC Programmes Developers <programmes-devel@lists.forge.bbc.co.uk>
 * @copyright Copyright (c) 2015 BBC (http://www.bbc.co.uk)
 *
 */
class LocalisedDaysAndMonthsHelper
{
    use TranslatableTrait;

    /** @var TranslateProvider */
    protected $translateProvider;

    public function __construct(TranslateProvider $translateProvider)
    {
        $this->translateProvider = $translateProvider;
    }

    public function localisedDaysAndMonths(): string
    {
        $localisedDaysAndMonths = [];
        $dateHelper = new DateCorrection();

        $locale = $this->translateProvider->getLocale();
        $fmt = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'GMT'
        );
        $date = new DateTime();
        $date->setTime(6, 30);

        $localisedDaysAndMonths['days'] = [];
        $fmt->setPattern('EEE');
        foreach (range(1, 7) as $dayNumber) {
            $date->setDate(2015, 3, $dayNumber);
            $string = $fmt->format($date->getTimestamp());
            $string = $dateHelper->fixSpelling($string, $locale);
            $localisedDaysAndMonths['days'][] = $string;
        }

        $localisedDaysAndMonths['months'] = [];
        $fmt->setPattern('MMM');
        foreach (range(1, 12) as $monthNumber) {
            $date->setDate(2015, $monthNumber, 1);
            $string = $fmt->format($date->getTimestamp());
            $string = $dateHelper->fixSpelling($string, $locale);
            $localisedDaysAndMonths['months'][] = $string;
        }

        return json_encode($localisedDaysAndMonths);
    }

    public function getFormatedPartialDate(PartialDate $partialDate)
    {
        $date = $partialDate->asDateTime();
        $hasMonth = $partialDate->hasMonth();
        if ($partialDate->hasDay() && $hasMonth) {
            return $this->getFormatedDay(new Chronos($partialDate));
        }
        if ($hasMonth) {
            return $this->localDateIntl($date, 'MMM yyyy');
        }
        return $this->localDateIntl($date, 'Y');
    }


    public function getFormatedDay(Chronos $date): string
    {
        /** @var Chronos $now */
        $now = ApplicationTime::getLocalTime();
        $date = $date->setTimezone(ApplicationTime::getLocalTimeZone());

        $translate = $this->translateProvider->getTranslate();

        $date = $date->setTime(0, 0, 0);
        $now = $now->setTime(0, 0, 0);

        if ($date->isSameDay($now)) {
            return $translate->translate('schedules_today');
        }

        if ($date->isSameDay($now->addDay(1))) {
            return $translate->translate('schedules_tomorrow');
        }

        if ($date->isSameDay($now->subDay(1))) {
            return $translate->translate('schedules_yesterday');
        }

        if ($date->format('m-d') === '12-24') {
            return $translate->translate('schedules_christmas_eve') . ' ' . $date->format('Y');
        }

        if ($date->format('m-d') === '12-25') {
            return $translate->translate('schedules_christmas_day') . ' ' . $date->format('Y');
        }

        if ($date->format('m-d') === '12-26') {
            return $translate->translate('schedules_boxing_day') . ' ' . $date->format('Y');
        }

        if ($date->format('m-d') === '01-01') {
            return $translate->translate('schedules_new_years_day') . ' ' . $date->format('Y');
        }

        $diffInDays = $now->diffInDays($date, false);
        if (-5 <= $diffInDays && $diffInDays <= 5) {
            return $this->localDateIntl($date, 'EEEE'); // Monday|Tuesday|etc
        }

        if (5 < $diffInDays && $diffInDays <= 8) {
            return $translate->translate('schedules_next_weekday', ['%1' => $this->localDateIntl($date, 'EEEE')]);
        }

        if (-8 <= $diffInDays && $diffInDays < -5) {
            return $translate->translate('schedules_last_weekday', ['%1' => $this->localDateIntl($date, 'EEEE')]);
        }

        return $this->localDateIntl($date, 'EEE d MMM yyyy'); // Tue 23 Mar 2017
    }
}
