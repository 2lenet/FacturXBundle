<?php

namespace Lle\FacturXBundle\Platform\Iopole;

use Lle\FacturXBundle\Model\Status;
use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\PlatformInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Approved platform Iopole (lle_facturx.platform: iopole). OAuth2 client credentials token, kept in cache.
 */
class IopolePlatform implements PlatformInterface
{
    public const CACHE_KEY_TOKEN = 'lle_facturx_iopole_token';
    public const GRANT_TYPE = 'client_credentials';
    /** margin so as not to use a token that expires during the request */
    public const EXPIRATION_MARGIN = 30;
    public const ENDPOINT_INVOICE = '/v1/invoice';
    public const ENDPOINT_STATUS_HISTORY = '/status-history';
    public const HEADER_CUSTOMER_ID = 'customer-id';
    public const FIELD_FILE = 'file';

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected CacheInterface $cache,
        protected string $authUrl,
        protected string $apiUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected string $customerId,
    ) {
    }

    public function send(string $file): string
    {
        $form = new FormDataPart([self::FIELD_FILE => DataPart::fromPath($file)]);

        $data = $this->httpClient->request('POST', $this->apiUrl . self::ENDPOINT_INVOICE, [
            'auth_bearer' => $this->getToken(),
            'headers' => array_merge(
                $form->getPreparedHeaders()->toArray(),
                [self::HEADER_CUSTOMER_ID . ': ' . $this->customerId]
            ),
            'body' => $form->bodyToIterable(),
        ])->toArray();

        return $data['id'];
    }

    public function getStatusHistory(string $id): array
    {
        $statuses = $this->httpClient->request(
            'GET',
            $this->apiUrl . self::ENDPOINT_INVOICE . '/' . $id . self::ENDPOINT_STATUS_HISTORY,
            [
                'auth_bearer' => $this->getToken(),
                'headers' => [self::HEADER_CUSTOMER_ID . ': ' . $this->customerId],
            ]
        )->toArray();

        // the statusId is a time-ordered UUID (v7), reliable; the date only when it is missing
        usort($statuses, fn (array $a, array $b): int => isset($a['statusId'], $b['statusId'])
            ? strcmp((string)$b['statusId'], (string)$a['statusId'])
            : strcmp((string)$b['date'], (string)$a['date']));

        return array_map(fn (array $status): Status => new Status(
            new \DateTimeImmutable($status['date']),
            StatusCode::tryFrom($status['status']['code']) ?? StatusCode::UNKNOWN,
            $status['status']['code'],
            $status['destType'] ?? '',
            $this->getReasons($status['json'] ?? []),
        ), $statuses);
    }

    public function getToken(): string
    {
        return $this->cache->get(self::CACHE_KEY_TOKEN, function (ItemInterface $item): string {
            $data = $this->httpClient->request('POST', $this->authUrl, [
                'body' => [
                    'grant_type' => self::GRANT_TYPE,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ])->toArray();
            $item->expiresAfter(max(0, (int)$data['expires_in'] - self::EXPIRATION_MARGIN));

            return $data['access_token'];
        });
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return string[]
     */
    protected function getReasons(array $response): array
    {
        $reasons = [];
        foreach ($response['responses'] ?? [] as $answer) {
            $rejection = $answer['rejectionDetail'] ?? [];
            foreach ($rejection['errors'] ?? [] as $error) {
                $reasons[] = $error['rule'] . ' : ' . trim((string)preg_replace('/\s+/', ' ', $error['message']));
            }
            if ($reasons === [] && isset($rejection['message'])) {
                $reasons[] = $rejection['message'];
            }
        }

        return $reasons;
    }
}
