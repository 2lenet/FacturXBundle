<?php

namespace Lle\FacturXBundle\Tests\Platform;

use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\Iopole\IopolePlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Iopole API: token in cache, sending of the file, statuses translated to the common codes. */
class IopolePlatformTest extends TestCase
{
    private const AUTH_URL = 'https://auth.iopole.test/token';
    private const API_URL = 'https://api.iopole.test';
    /** Extract of a real sandbox answer (invoice rejected on 2026-11-18). */
    private const HISTORY = [
        [
            'date' => '2026-11-18T12:52:58.000Z',
            'destType' => 'OPERATOR',
            'status' => ['code' => 'RECEIVED'],
            'json' => [],
        ],
        [
            'date' => '2026-11-18T12:52:58.938Z',
            'destType' => 'OPERATOR',
            'status' => ['code' => 'REJECTED'],
            'json' => ['responses' => [[
                'rejectionDetail' => [
                    'message' => 'The document was rejected because it failed validation',
                    'errors' => [[
                        'rule' => 'BR-FR-08_BT-23',
                        'message' => "\n        BR-FR-08/BT-23 : La valeur du mode de facturation est absente.\n      ",
                    ]],
                ],
            ]]],
        ],
        [
            'date' => '2026-11-18T12:52:57.000Z',
            'destType' => 'PPF',
            'status' => ['code' => 'NEW_CODE'],
        ],
    ];

    private MockHttpClient $httpClient;

    /** @var list<array{method: string, url: string, headers: array<mixed>, body: string}> */
    private array $requests = [];

    private IopolePlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'],
                'body' => self::readBody($options['body'] ?? ''),
            ];
            if ($url === self::AUTH_URL) {
                return new MockResponse((string)json_encode(['access_token' => 'token-123', 'expires_in' => 300]));
            }
            if (str_ends_with($url, '/status-history')) {
                return new MockResponse((string)json_encode(self::HISTORY));
            }

            return new MockResponse((string)json_encode(['type' => 'INVOICE', 'id' => 'iopole-id-1']), ['http_code' => 201]);
        });
        $this->platform = new IopolePlatform(
            $this->httpClient,
            new ArrayAdapter(),
            self::AUTH_URL,
            self::API_URL,
            'client-test',
            'secret-test',
            'customer-test',
        );
    }

    public function testTokenWithTheCredentialsKeptInCache(): void
    {
        $this->assertSame('token-123', $this->platform->getToken());
        $this->assertSame('token-123', $this->platform->getToken());

        $this->assertSame(1, $this->httpClient->getRequestsCount());
        parse_str($this->requests[0]['body'], $body);
        $this->assertSame(['grant_type' => 'client_credentials', 'client_id' => 'client-test', 'client_secret' => 'secret-test'], $body);
    }

    public function testSendTheFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'invoice') . '.pdf';
        file_put_contents($file, 'pdf-content');

        $this->assertSame('iopole-id-1', $this->platform->send($file));

        $request = $this->requests[1];
        $this->assertSame(self::API_URL . '/v1/invoice', $request['url']);
        $this->assertContains('Authorization: Bearer token-123', $request['headers']);
        $this->assertContains('customer-id: customer-test', $request['headers']);
        $this->assertStringContainsString('name="file"; filename="' . basename($file) . '"', $request['body']);
        $this->assertStringContainsString('pdf-content', $request['body']);
        unlink($file);
    }

    public function testStatusHistoryMostRecentFirst(): void
    {
        $history = $this->platform->getStatusHistory('iopole-id-1');

        $this->assertSame(self::API_URL . '/v1/invoice/iopole-id-1/status-history', $this->requests[1]['url']);
        $this->assertSame([StatusCode::REJECTED, StatusCode::RECEIVED, StatusCode::UNKNOWN], array_map(fn ($status) => $status->code, $history));
        $this->assertSame(['BR-FR-08_BT-23 : BR-FR-08/BT-23 : La valeur du mode de facturation est absente.'], $history[0]->reasons);
        $this->assertSame('OPERATOR', $history[0]->recipient);
        // unknown code: kept as given by the platform
        $this->assertSame('NEW_CODE', $history[2]->getLabel());
    }

    public function testStatusHistoryOrderedByStatusIdWhenDatesAreRounded(): void
    {
        // real sandbox answer (TEST-KIWI-002, 2026-11-20): RECEIVED is dated .000 but came after ISSUED
        $history = [
            ['statusId' => '01a1f950-ae57-7382-b1ed-29df4d3e3873', 'date' => '2026-11-20T14:16:16.595Z', 'destType' => 'OPERATOR', 'status' => ['code' => 'ISSUED']],
            ['statusId' => '01a1f950-ad96-733f-9792-4f229765db4b', 'date' => '2026-11-20T14:16:16.378Z', 'destType' => 'OPERATOR', 'status' => ['code' => 'SUBMITTED']],
            ['statusId' => '01a1f950-af88-70e1-bf73-9486ecefb2d5', 'date' => '2026-11-20T14:16:16.000Z', 'destType' => 'OPERATOR', 'status' => ['code' => 'RECEIVED', 'networkCode' => '202']],
        ];
        $platform = new IopolePlatform(
            new MockHttpClient(fn (string $method, string $url): MockResponse => new MockResponse((string)json_encode(
                $url === self::AUTH_URL ? ['access_token' => 'token-123', 'expires_in' => 300] : $history
            ))),
            new ArrayAdapter(),
            self::AUTH_URL,
            self::API_URL,
            'client-test',
            'secret-test',
            'customer-test',
        );

        $codes = array_map(fn ($status) => $status->code, $platform->getStatusHistory('iopole-id-2'));

        $this->assertSame([StatusCode::RECEIVED, StatusCode::ISSUED, StatusCode::SUBMITTED], $codes);
    }

    private static function readBody(mixed $body): string
    {
        if (!is_callable($body)) {
            return (string)$body;
        }
        $content = '';
        while ('' !== $chunk = $body(8192)) {
            $content .= $chunk;
        }

        return $content;
    }
}
