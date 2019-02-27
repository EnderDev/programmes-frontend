<?php
declare(strict_types = 1);

namespace Tests\App\Controller\FindByPid;

use App\Controller\FindByPid\TlecController;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\Entity\Network;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Service\CollapsedBroadcastsService;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\App\BaseWebTestCase;

class TlecControllerTest extends BaseWebTestCase
{
    public function tearDown()
    {
        ApplicationTime::blank();
    }

    public function testIsVotePriority()
    {
        $controller = $this->createMock(TlecController::class);

        $programmeContainer = $this->createMock(ProgrammeContainer::class);

        $programmeContainer->expects($this->atLeastOnce())->method('getOption')
            ->will($this->returnValueMap([
                ['brand_layout', 'vote'],
                ['telescope_block', 'anythingthatisntnull'],
            ]));

        $this->assertTrue($this->invokeMethod($controller, 'isVotePriority', [$programmeContainer]));
    }

    /** @dataProvider listOfNetworksIsFetchedOnlyForWorldNewsLastOnProvider */
    public function testListOfNetworksIsFetchedOnlyForWorldNewsLastOn(
        bool $isWorldNews,
        int $fullListCalls,
        int $regularCalls
    ) {
        $programme = $this->createConfiguredMock(
            ProgrammeContainer::class,
            [
                'getNetwork' => $this->createConfiguredMock(
                    Network::class,
                    ['isWorldNews' => $isWorldNews]
                ),
            ]
        );

        $collapsedBroadcastService = $this->createMock(CollapsedBroadcastsService::class);
        $collapsedBroadcastService
            ->expects($this->exactly($fullListCalls))
            ->method('findPastByProgrammeWithFullServicesOfNetworksList');
        $collapsedBroadcastService
            ->expects($this->exactly($regularCalls))
            ->method('findPastByProgramme');

        $controller = $this->createMock(TlecController::class);
        $this->invokeMethod($controller, 'getLastOn', [$programme, $collapsedBroadcastService]);
    }

    public function listOfNetworksIsFetchedOnlyForWorldNewsLastOnProvider(): array
    {
        return [
            'World News' => [true, 1, 0],
            'Non World News' => [false, 0, 1],
        ];
    }

    /**
     * @dataProvider showMiniMapDataProvider
     */
    public function testShowMiniMap(Request $request, ProgrammeContainer $programmeContainer, bool $isPromoPriority, bool $hasLxPromo)
    {
        $controller = $this->createMock(TlecController::class);

        $showMiniMap = $this->invokeMethod(
            $controller,
            'showMiniMap',
            [
                $request,
                $programmeContainer,
                $isPromoPriority,
                $hasLxPromo,
            ]
        );
        $this->assertTrue($showMiniMap);
    }

    public function showMiniMapDataProvider(): array
    {
        $cases = [];
        $programmeContainer = $this->createMock(ProgrammeContainer::class);
        $cases['is-vote-priority'] = [new Request(), clone $programmeContainer, true, false];
        $cases['has-lx-promo'] = [new Request(), clone $programmeContainer, true, true];
        $cases['forced-by-url'] = [new Request(['__2016minimap' => 1]), clone $programmeContainer, false];

        $programmeContainer->expects($this->once())
            ->method('getOption')
            ->with('brand_2016_layout_use_minimap')
            ->willReturn('true');

        $cases['forced-by-url'] = [new Request(), $programmeContainer, false, false];

        return $cases;
    }

    public function testSetInternationalStatusAndTimezoneFromContext()
    {
        // check default timezone is Europe/London
        $this->assertSame('Europe/London', ApplicationTime::getLocalTimeZone()->getName());

        $network = $this->createMock(Network::class);
        $network->method('isInternational')->willReturn(true);
        $tlecController = $this->createMock(TlecController::class);
        $this->invokeMethod($tlecController, 'setInternationalStatusAndTimezoneFromContext', [$network]);

        // check timezone for international services is set to UTC
        $this->assertSame('UTC', ApplicationTime::getLocalTimeZone()->getName());
    }

    private function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
