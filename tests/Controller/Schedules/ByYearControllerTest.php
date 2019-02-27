<?php
declare(strict_types = 1);

namespace Tests\App\Controller\Schedules;

use Tests\App\BaseWebTestCase;

/**
 * @covers \App\Controller\Schedules\ByYearController
 */
class ByYearControllerTest extends BaseWebTestCase
{
    /**
     * @dataProvider serviceActiveTestProvider
     * @param string $scheduleYear    The year the user is viewing the schedule for
     * @param int $expectedResponseCode
     */
    public function testResponseIs404IfServiceIsNotActive(string $scheduleYear, int $expectedResponseCode)
    {
        $this->loadFixtures(["NetworksAndServicesFixture"]);

        $client = static::createClient();
        $url = '/schedules/p00rfdrb/' . $scheduleYear; //5liveolympicextra

        $client->request('GET', $url);

        $this->assertResponseStatusCode($client, $expectedResponseCode);
        $this->assertHasRequiredResponseHeaders($client);
    }

    public function serviceActiveTestProvider(): array
    {
        return [
            'SERVICE IS ACTIVE: not-active-in-year' => ['2011', 404],
            'SERVICE IS NOT ACTIVE: starts-half-way-through-year' => ['2012', 200],
            'SERVICE IS NOT ACTIVE: finishes-half-way-through-year' => ['2012', 200],
        ];
    }

    public function testResponseIs404FromRoutingForYearBefore1900()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/schedules/p00rfdrb/' . 1800);

        $this->assertResponseStatusCode($client, 404);
        $this->assertEquals('Invalid date supplied', $crawler->filter('.exception-message-wrapper h1')->text());
    }

    public function testServiceIsNotFound()
    {
        // This empties the DB to ensure previous iterations are cleared
        $this->loadFixtures([]);

        $client = static::createClient();
        $client->request('GET', '/schedules/zzzzzzzz/2012');

        $this->assertResponseStatusCode($client, 404);
    }
}
