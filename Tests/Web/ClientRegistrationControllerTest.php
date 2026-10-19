<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Api44\Tests\Web;

use Eccube\Tests\EccubeTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugin\Api44\Controller\ClientRegistrationController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * 動的クライアント登録 (POST /register) の入力検証テスト。
 *
 * クライアントの入力の誤りは、 保存や値オブジェクトの生成で例外にせず、
 * RFC 7591 のエラー (400) として JSON で返すことを担保する。
 */
class ClientRegistrationControllerTest extends EccubeTestCase
{
    private const VALID_REDIRECT_URI = 'http://127.0.0.1:3334/oauth/callback';

    protected function setUp(): void
    {
        parent::setUp();

        // レート制限の状態は cache.app (ファイル) に残るため、 テストごとに戻す
        foreach (['limiter.mcp_dcr_register' => 'dcr:127.0.0.1', 'limiter.mcp_dcr_register_global' => 'dcr:global'] as $service => $key) {
            /** @var RateLimiterFactory $factory */
            $factory = static::getContainer()->get($service);
            $factory->create($key)->reset();
        }
    }

    public function testLongClientNameIsTruncatedToColumnLength(): void
    {
        $response = $this->register([
            'client_name' => str_repeat('あ', 300),
            'redirect_uris' => [self::VALID_REDIRECT_URI],
        ]);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        $client = $clientManager->find($data['client_id']);
        $this->assertNotNull($client);
        $this->assertSame(ClientRegistrationController::CLIENT_NAME_MAX_LENGTH, mb_strlen($client->getName()));
        $this->assertStringStartsWith(ClientRegistrationController::CLIENT_NAME_PREFIX, $client->getName());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRedirectUriProvider(): array
    {
        return [
            '空白を含む' => ['https://example.com/oauth/call back'],
            'タブを含む' => ["https://example.com/oauth/\tcallback"],
            '改行を含む' => ["https://example.com/oauth/callback\n"],
            'NUL を含む' => ["https://example.com/oauth/\0callback"],
            'loopback で空白を含む' => ['http://127.0.0.1:3334/oauth/call back'],
            '長すぎる' => ['https://example.com/'.str_repeat('a', ClientRegistrationController::REDIRECT_URI_MAX_LENGTH)],
        ];
    }

    #[DataProvider('invalidRedirectUriProvider')]
    public function testInvalidRedirectUriReturnsInvalidRedirectUri(string $redirectUri): void
    {
        $response = $this->register(['redirect_uris' => [$redirectUri]]);

        $this->assertErrorResponse($response, 'invalid_redirect_uri');
    }

    public function testTooManyRedirectUrisReturnsInvalidRedirectUri(): void
    {
        $redirectUris = [];
        for ($i = 0; $i <= ClientRegistrationController::REDIRECT_URIS_MAX_COUNT; ++$i) {
            $redirectUris[] = 'http://127.0.0.1:'.(3000 + $i).'/oauth/callback';
        }

        $response = $this->register(['redirect_uris' => $redirectUris]);

        $this->assertErrorResponse($response, 'invalid_redirect_uri');
    }

    public function testMaxRedirectUrisAreAccepted(): void
    {
        $redirectUris = [];
        for ($i = 1; $i <= ClientRegistrationController::REDIRECT_URIS_MAX_COUNT; ++$i) {
            $redirectUris[] = 'https://example.com/'.str_repeat('a', ClientRegistrationController::REDIRECT_URI_MAX_LENGTH - 25).sprintf('/%03d', $i);
        }

        $response = $this->register(['redirect_uris' => $redirectUris]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame($redirectUris, $data['redirect_uris']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidBodyProvider(): array
    {
        return [
            'JSON でない' => ['not json', 'invalid_client_metadata'],
            '文字列' => ['"text"', 'invalid_client_metadata'],
            '数値' => ['123', 'invalid_client_metadata'],
            'null' => ['null', 'invalid_client_metadata'],
            '配列' => ['["'.self::VALID_REDIRECT_URI.'"]', 'invalid_client_metadata'],
            'redirect_uris が無い' => ['{}', 'invalid_redirect_uri'],
            'redirect_uris が文字列' => ['{"redirect_uris":"'.self::VALID_REDIRECT_URI.'"}', 'invalid_redirect_uri'],
            'redirect_uris が空' => ['{"redirect_uris":[]}', 'invalid_redirect_uri'],
            'redirect_uris の要素が文字列でない' => ['{"redirect_uris":[{"uri":"'.self::VALID_REDIRECT_URI.'"}]}', 'invalid_redirect_uri'],
            'redirect_uris の要素が数値' => ['{"redirect_uris":[1]}', 'invalid_redirect_uri'],
        ];
    }

    #[DataProvider('invalidBodyProvider')]
    public function testInvalidBodyReturnsClientError(string $content, string $expectedError): void
    {
        $response = $this->request($content);

        $this->assertErrorResponse($response, $expectedError);
    }

    /**
     * 登録で使わない項目は、 どの型で送られても無視して登録する (500 にしない)。
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ignoredMetadataProvider(): array
    {
        return [
            'client_name が配列' => [['client_name' => ['name']]],
            'client_name が数値' => [['client_name' => 123]],
            'client_name が制御文字だけ' => [['client_name' => "\0\t\n"]],
            'grant_types が文字列' => [['grant_types' => 'authorization_code']],
            'grant_types の要素が配列' => [['grant_types' => [['authorization_code']]]],
            'scope が配列' => [['scope' => ['mcp:product:read']]],
            'scope が数値' => [['scope' => 1]],
            'token_endpoint_auth_method が配列' => [['token_endpoint_auth_method' => ['none']]],
            'token_endpoint_auth_method が未対応' => [['token_endpoint_auth_method' => 'client_secret_basic']],
            'response_types がオブジェクト' => [['response_types' => ['code' => true]]],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('ignoredMetadataProvider')]
    public function testIgnoredMetadataOfAnyTypeDoesNotCauseServerError(array $metadata): void
    {
        $response = $this->register(array_merge($metadata, ['redirect_uris' => [self::VALID_REDIRECT_URI]]));

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(['authorization_code', 'refresh_token'], $data['grant_types']);
        $this->assertSame('none', $data['token_endpoint_auth_method']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function register(array $payload): Response
    {
        return $this->request((string) json_encode($payload));
    }

    private function request(string $content): Response
    {
        $this->client->request(
            Request::METHOD_POST,
            '/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $content,
        );

        return $this->client->getResponse();
    }

    private function assertErrorResponse(Response $response, string $expectedError): void
    {
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame($expectedError, $data['error'] ?? null);
    }
}
