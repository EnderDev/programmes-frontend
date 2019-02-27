<?php
declare(strict_types = 1);
namespace Tests\App\Controller\Schedules;

use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use Cake\Chronos\Chronos;
use Tests\App\BaseWebTestCase;

/**
 * @covers \App\Controller\Schedules\ByDayController
 */
class ByDayControllerTest extends BaseWebTestCase
{
    /**
     * @dataProvider scheduleDateTestProvider
     * @param null|string $timeNow            The system time, can be null if setting $scheduleDate
     * @param string $network            The pid of the network
     * @param null|string $scheduleDate       The date the user is viewing the schedule for, can be null if $timeNow is set
     * @param string[] $expectedBroadcasts An array of expected broadcast times
     */
    public function testScheduleDisplaysCorrectBroadcastsForTime(?string $timeNow, string $network, ?string $scheduleDate, array $expectedBroadcasts)
    {
        if (!is_null($timeNow)) {
            ApplicationTime::setTime((new Chronos($timeNow))->timestamp);
        }

        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $url = '/schedules/' . $network;
        if (!is_null($scheduleDate)) {
            $url .= '/' . $scheduleDate;
        }
        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 200);
        $broadcasts = $crawler->filter(".broadcast__time")->extract(['content']);

        $this->assertEquals($expectedBroadcasts, $broadcasts);
        $this->assertHasRequiredResponseHeaders($client);
    }

    public function scheduleDateTestProvider(): array
    {
        return [
            'radio-no-date' => ['2017-05-22 00:00:00', 'p00fzl8v', null, ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00', '2017-05-22T15:45:00+01:00', '2017-05-23T03:00:00+01:00']],
            'radio-with-date' => [null, 'p00fzl8v', '2017/05/22', ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00', '2017-05-22T15:45:00+01:00', '2017-05-23T03:00:00+01:00']],
            'tv-no-date' => ['2017-05-22 09:00:00', 'p00fzl6p', null, ['2017-05-22T15:00:00+01:00', '2017-05-22T15:45:00+01:00', '2017-05-23T03:00:00+01:00']],
            'tv-no-date-tomorrow-before-6am' => ['2017-05-23 03:00:00', 'p00fzl6p', null, ['2017-05-22T15:00:00+01:00', '2017-05-22T15:45:00+01:00', '2017-05-23T03:00:00+01:00']],
            'tv-with-date' => [null, 'p00fzl6p', '2017/05/22', ['2017-05-22T15:00:00+01:00', '2017-05-22T15:45:00+01:00', '2017-05-23T03:00:00+01:00']],
            'radio-no-date-and-utcoffset' => ['2017-05-22 00:00:00', 'p00fzl8v?utcoffset=%2B04%3A00', null, ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00']],
            'radio-with-date-and-utcoffset' => [null, 'p00fzl8v', '2017/05/22?utcoffset=%2B04%3A00', ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00']],
            'tv-no-date-and-utcoffset' => ['2017-05-22 09:00:00', 'p00fzl6p?utcoffset=%2B04%3A00', null, ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00']],
            'tv-no-date-tomorrow-before-6am-and-utcoffset' => ['2017-05-23 03:00:00', 'p00fzl6p?utcoffset=%2B04%3A00', null, ['2017-05-23T03:00:00+01:00']],
            'tv-with-date-and-utcoffset' => [null, 'p00fzl6p', '2017/05/22?utcoffset=%2B04%3A00', ['2017-05-22T03:00:00+01:00', '2017-05-22T03:45:00+01:00', '2017-05-22T15:00:00+01:00']],
        ];
    }

    public function testScheduleIsNotFound()
    {
        // This empties the DB to ensure previous iterations are cleared
        $this->loadFixtures([]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/schedules/p00fzl6p');

        $this->assertResponseStatusCode($client, 404, "Expected 404 when pid specified is not found");
    }

    public function testScheduleForDateIsNotFound()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/schedules/p00fzl6p/2017/03/04');

        $this->assertResponseStatusCode($client, 200, "We expect 200 for dates before +35 days, no matter if they have broadcasts");
        $message = $crawler->filter(".noschedule")->text();
        $this->assertEquals('There is no schedule for today. Please choose another day from the calendar', trim($message));
        $this->assertEquals(0, $crawler->filter('.broadcast')->count());
    }

    public function testNoScheduleByDayBeginsOn()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $url = '/schedules/p00rfdrb/2012/07/24';
        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 404, "We expect 404 when the service is not active");
        $message = $crawler->filter(".noschedule")->text();
        $this->assertEquals('Broadcast schedule begins on Wednesday 25 July 2012', trim($message));
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'when 404 is not added');
        $this->assertHasRequiredResponseHeaders($client);
    }

    public function testNoScheduleByDayEndedOn()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $url = '/schedules/p00rfdrb/2012/08/15';
        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 404, "We expect 404 when the service is not active");
        $message = $crawler->filter(".noschedule")->text();
        $this->assertEquals('Broadcast schedule ended on Tuesday 14 August 2012', trim($message));
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'When 404 is not added');
        $this->assertHasRequiredResponseHeaders($client);
    }

    /**
     * @dataProvider datesProvider
     */
    public function testNoScheduleByDayNoResults(string $date, $expectedResponseCode)
    {
        $this->loadFixtures(["BroadcastsFixture"]);
        $client = static::createClient();

        $url = '/schedules/p00rfdrb/' . $date;
        $crawler = $client->request('GET', $url);

        $this->assertEquals(0, $crawler->filter('.broadcast')->count());
        $message = $crawler->filter(".noschedule")->text();
        $this->assertEquals('There is no schedule for today. Please choose another day from the calendar', trim($message));
        $this->assertResponseStatusCode($client, $expectedResponseCode);
        $this->assertHasRequiredResponseHeaders($client);
    }

    public function datesProvider()
    {
        return [
            // Service active between [2012-07-25 21:00:00, 2012-08-13 23:00:00]
            'CASE 1: Edge case of service active period. The service is active at this period' => ['2012/07/25', 200],
            'CASE 2: Edge case of service active period. The service is not active at this date' => ['2012/08/14', 404],
        ];
    }

    public function testNoMetaHeaderIsAddedWhenExistBroadcastsInPast()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();

        $url = '/schedules/p00fzl8v/2017/05/22';
        $crawler = $client->request('GET', $url);
        $this->assertResponseStatusCode($client, 200);
        $this->assertHasRequiredResponseHeaders($client);
        $this->assertEquals(5, $crawler->filter('.broadcast')->count());
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'When broadcasts are found is not added');
    }

    public function testMetaTagIsAddedWhenServiceWhenNoBroadcastsInThePast()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $url = '/schedules/p00fzl8v/2012/06/14';
        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 200, "The service is active on that period but there is no broadcasts");
        $this->assertEquals(0, $crawler->filter('.broadcast')->count());
        $message = $crawler->filter(".noschedule")->text();
        $this->assertEquals('There is no schedule for today. Please choose another day from the calendar', trim($message));
        $this->assertHasRequiredResponseHeaders($client);
        $this->assertTrue($this->isAddedMetaNoIndex($crawler), 'When 404 is not added');
    }

    public function testMetaTagIsNotAddedWhenRequestedNext35Days()
    {
        $this->loadFixtures(["BroadcastsFixture"]);
        ApplicationTime::setTime((new Chronos('2017/06/01'))->timestamp);

        $client = static::createClient();
        $url = '/schedules/p00fzl8v/2017/06/14';
        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 200, "We requested a day in the future but before +35 days, so we expect a 200, no matter if we have broadcasts");
        $this->assertHasRequiredResponseHeaders($client);
        $this->assertEquals(0, $crawler->filter('.broadcast')->count());
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'We return 200 for this period before +35, so we dont set the meta tag');
    }

    public function testBeyong35DaysMetatagIsNotAdded()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        ApplicationTime::setTime((new Chronos('2017/06/01'))->timestamp);
        $client = static::createClient();
        $url = '/schedules/p00fzl8v/2017/08/14';
        $crawler = $client->request('GET', $url);
        $this->assertResponseStatusCode($client, 404, "We expect 404 for days with no broadcasts and beyond +35 days");
        $this->assertHasRequiredResponseHeaders($client);
        $this->assertEquals(0, $crawler->filter('.broadcast')->count());
        $this->assertFalse($this->isAddedMetaNoIndex($crawler), 'Beyond +35 days we dont set any meta tag');
    }

    /**
     * @dataProvider invalidFormatDatesProvider
     * @dataProvider invalidDatesForControllerValidationProvider
     */
    public function testResponseIs404ForIncorrectDates(string $expectedMsgException, string $schedulesDateProvided)
    {
        $client = static::createClient();
        $url = '/schedules/p00rfdrb/' . $schedulesDateProvided;

        $crawler = $client->request('GET', $url);

        $this->assertResponseStatusCode($client, 404);
        $this->assertEquals($expectedMsgException, $crawler->filter('.exception-message-wrapper h1')->text());
    }

    public function invalidFormatDatesProvider(): array
    {
        // trigger INVALID ARGUMENT EXCEPTION (routing exception)
        return [
            'CASE 1: valid date but invalid format number' => ['No route found for "GET /schedules/p00rfdrb/2012/7/20"', '2012/7/20'],
            'CASE 2: valid date but invalid format string' => ['No route found for "GET /schedules/p00rfdrb/2012-7-20"', '2012-7-20'],
        ];
    }

    public function invalidDatesForControllerValidationProvider(): array
    {
        // trigger HTTP NOT FOUND EXCEPTION (validation exception)
        return [
            'CASE 1: nonexistent month' => ['Invalid date supplied', '2012/13/20'],
            'CASE 2: nonexistent month' => ['Invalid date supplied', '2012/00/20'],
            'CASE 3: nonexistent day' => ['Invalid date supplied', '2012/02/36'],
            'CASE 4: nonexistent day' => ['Invalid date supplied', '2009/02/00'],
            'CASE 5: invalid year, previous to 1900' => ['Invalid date supplied', '1800/02/20'],
        ];
    }

    /**
     * @dataProvider validsUtcOffsetsProvider
     */
    public function testUtcOffsetModifyTimezoneInSchedulesByDay(string $utcOffsetProvided)
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $client->request('GET', '/schedules/p00fzl8v/2017/05/22?utcoffset=' . $utcOffsetProvided);

        $this->assertResponseStatusCode($client, 200);
    }

    public function validsUtcOffsetsProvider(): array
    {
        // utc offset needs the symbol +/- always
        return [
            'CASE 1: by_day utcoffset can be positive' => [urlencode('+10:00')],
            'CASE 2: by_day utcoffset can be negative' => [urlencode('-10:00')],
        ];
    }

    /**
     * @dataProvider invalidsUtcOffsetsProvider
     */
    public function testUtcOffsetThrowExceptionWhenNoValidUtcOffsetModifyTimezoneInSchedulesByDay(string $utcOffsetProvided)
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/schedules/p00fzl8v/2017/05/22?utcoffset=' . $utcOffsetProvided);

        $this->assertResponseStatusCode($client, 404);
        $this->assertEquals('Invalid date supplied', $crawler->filter('.exception-message-wrapper h1')->text());
    }

    public function invalidsUtcOffsetsProvider(): array
    {
        return [
            'CASE 1: by_day utcoffset without symbol +/- is not allowed' => [urlencode('10:00')],
            'CASE 2: by_day utcoffset without urlencodeding is not allowed' => ['+10:00'],
            'CASE 3: by_day utcoffset before -12h is invalid' => [urlencode('-13:00')],
            'CASE 4: by_day utcoffset after +14h is invalid' => [urlencode('15:00')],
            'CASE 5: by_day utcoffset with minutes different to 00, 15, 30, 45 are invalid' => [urlencode('10:05')],
            'CASE 6: by_day utcoffset minutes are required' => [urlencode('+10')],
            'CASE 7: by_day utcoffset cannot use hours digits with one number ' => [urlencode('-9:00')],
            'CASE 8: by_day utcoffset is invalid format' => [urlencode('-13:000')],
            'CASE 9: by_day utcoffset is invalid format' => [urlencode('-+13:00')],
            'CASE 10: by_day utcoffset is invalid format' => ['-' . urlencode('+10:00')],
        ];
    }

    public function testDataPageTimeIsSetProperlyInHtmlResponse()
    {
        $this->loadFixtures(["BroadcastsFixture"]);
        $client = static::createClient();

        $crawler = $client->request('GET', '/schedules/p00fzl8v/2017/05/22');

        $this->assertResponseStatusCode($client, 200);
        $this->assertEquals(1, $crawler->filter('[data-page-time]')->count());
        $this->assertEquals('2017/05/22', $crawler->filter('[data-page-time]')->attr('data-page-time'));
    }

    public function testDataPageTimeIsNotAddedInHtmlWhenNoRouteDateIsPassedInUrl()
    {
        $this->loadFixtures(["BroadcastsFixture"]);

        $timeNow = '2017/05/22';
        ApplicationTime::setTime((new Chronos($timeNow))->timestamp);

        $client = static::createClient();

        $crawler = $client->request('GET', '/schedules/p00fzl8v');

        $this->assertResponseStatusCode($client, 200);
        $this->assertEquals(0, $crawler->filter('[data-page-time]')->count());
    }

    private function isAddedMetaNoIndex($crawler): bool
    {
        return ($crawler->filter('meta[name="robots"]')->count() > 0 && $crawler->filter('meta[name="robots"]')->first()->attr('content') === 'noindex');
    }

    protected function tearDown()
    {
        ApplicationTime::blank();
    }
}
