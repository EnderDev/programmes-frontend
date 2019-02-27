<?php
declare(strict_types = 1);

namespace Tests\App\Controller\Schedules;

use Tests\App\BaseWebTestCase;

/**
 * @covers \App\Controller\Schedules\ByMonthController
 */
class ByMonthControllerTest extends BaseWebTestCase
{

    public function setUp()
    {
        $this->loadFixtures(["NetworksAndServicesFixture"]);
    }

    /**
     * @dataProvider serviceActiveTestProvider
     * @param string $scheduleDate    The month the user is viewing the schedule for
     * @param int $expectedResponseCode
     */
    public function testResponseIs404IfServiceIsNotActive(string $scheduleDate, int $expectedResponseCode)
    {
        $this->loadFixtures(["NetworksAndServicesFixture"]);

        $client = static::createClient();
        $url = '/schedules/p00rfdrb/' . $scheduleDate; //5liveolympicextra

        $client->request('GET', $url);

        $this->assertResponseStatusCode($client, $expectedResponseCode);
        $this->assertHasRequiredResponseHeaders($client);
    }

    public function serviceActiveTestProvider(): array
    {
        return [
            'SERVICE IS ACTIVE: not-active-in-month' => ['2012/06', 404],
            'SERVICE IS NOT ACTIVE: starts-half-way-through-month' => ['2012/07', 200],
            'SERVICE IS NOT ACTIVE: finishes-half-way-through-month' => ['2012/08', 200],
        ];
    }

    public function testServiceIsNotFound()
    {
        // This empties the DB to ensure previous iterations are cleared
        $this->loadFixtures([]);

        $client = static::createClient();
        $client->request('GET', '/schedules/zzzzzzzz/2012/12');

        $this->assertResponseStatusCode($client, 404);
    }

    /**
     * @dataProvider invalidFormatDatesProvider
     * @dataProvider invalidDatesForControllerValidationProvider
     */
    public function testResponseIs404ForIncorrectMonths(string $expectedMsgException, string $schedulesDateProvided)
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
            'CASE 3: valid month but invalid format number' => ['No route found for "GET /schedules/p00rfdrb/2012/7"', '2012/7'],
            'CASE 4: valid month but invalid format string' => ['No route found for "GET /schedules/p00rfdrb/2012-7"', '2012-7'],
        ];
    }

    public function invalidDatesForControllerValidationProvider(): array
    {
        // trigger HTTP NOT FOUND EXCEPTION (validation exception)
        return [
            'CASE 1: nonexistent month' => ['Invalid date supplied', '2012/13'],
            'CASE 2: nonexistent month' => ['Invalid date supplied', '2012/00'],
            'CASE 2: valid month, but invalid year' => ['Invalid date supplied', '1800/02'],
        ];
    }
}
