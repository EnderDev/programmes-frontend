<?php
declare(strict_types = 1);

namespace App\ExternalApi\FavouritesButton\Service;

use App\ExternalApi\Client\Factory\HttpApiClientFactory;
use App\ExternalApi\Client\HttpApiMultiClient;
use App\ExternalApi\Exception\MultiParseException;
use App\ExternalApi\FavouritesButton\Domain\FavouritesButton;
use App\ExternalApi\FavouritesButton\Mapper\FavouritesButtonMapper;
use BBC\ProgrammesCachingLibrary\CacheInterface;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;

class FavouritesButtonService
{
    /** @var HttpApiClientFactory */
    private $clientFactory;

    /** @var FavouritesButtonMapper */
    private $favouritesButtonMapper;

    /** @var string */
    private $url;

    public function __construct(HttpApiClientFactory $clientFactory, FavouritesButtonMapper $favouritesButtonMapper, string $url)
    {
        $this->clientFactory = $clientFactory;
        $this->favouritesButtonMapper = $favouritesButtonMapper;
        $this->url = $url;
    }

    /**
     * @return PromiseInterface (Promise returns ?FavouritesButton when unwrapped)
     */
    public function getContent(): PromiseInterface
    {
        $client = $this->makeHttpApiClient();
        return $client->makeCachedPromise();
    }

    private function makeHttpApiClient(): HttpApiMultiClient
    {
        $cacheKey = $this->clientFactory->keyHelper(__CLASS__, __FUNCTION__);
        // Making a call with a cert to some envs results in failures. Hence this
        $guzzleOptions = [
            'cert' => null,
            'ssl_key' => null,
        ];
        if (strpos($this->url, 'www.int') !== false) {
            // Ugly hack because cert is required on int
            $guzzleOptions = [];
        }
        $client = $this->clientFactory->getHttpApiMultiClient(
            $cacheKey,
            [$this->url],
            Closure::fromCallable([$this, 'parseResponse']),
            [],
            null,
            CacheInterface::MEDIUM,
            CacheInterface::NORMAL,
            $guzzleOptions
        );

        return $client;
    }

    /**
     * @param Response[] $responses
     * @return FavouritesButton
     */
    private function parseResponse(array $responses): FavouritesButton
    {
        $data = json_decode($responses[0]->getBody()->getContents(), true);
        if (!isset($data['head'], $data['script'], $data['bodyLast'])) {
            throw new MultiParseException(0, 'Response must contain head, script and bodyLast elements');
        }

        return $this->favouritesButtonMapper->mapItem($data);
    }
}
