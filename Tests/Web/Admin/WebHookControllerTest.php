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

namespace Plugin\Api44\Tests\Web\Admin;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugin\Api44\Entity\WebHook;
use Plugin\Api44\Repository\WebHookRepository;

class WebHookControllerTest extends AbstractAdminWebTestCase
{
    private ?WebHookRepository $webHookRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webHookRepository = static::getContainer()->get(WebHookRepository::class);
    }

    public function testNewRegistersWebHook(): void
    {
        $this->client->request('POST', $this->generateUrl('admin_api_webhook_new'), [
            'web_hook' => [
                'payload_url' => 'https://example.com/webhook',
                'secret' => 'webhooksecret0123456789',
                'enabled' => '1',
                '_token' => 'dummy',
            ],
        ]);

        $WebHook = $this->webHookRepository->findOneBy(['payloadUrl' => 'https://example.com/webhook']);
        $this->assertInstanceOf(WebHook::class, $WebHook);
        $this->assertTrue($this->client->getResponse()->isRedirect(
            $this->generateUrl('admin_api_webhook_edit', ['id' => $WebHook->getId()])
        ));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingPayloadUrlProvider(): iterable
    {
        yield 'payload_url が無い' => [[]];
        yield 'payload_url が空' => [['payload_url' => '']];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('missingPayloadUrlProvider')]
    public function testNewWithoutPayloadUrlShowsFormError(array $fields): void
    {
        $count = $this->webHookRepository->count([]);

        $this->client->request('POST', $this->generateUrl('admin_api_webhook_new'), [
            'web_hook' => $fields + ['_token' => 'dummy'],
        ]);

        // 500 にせず、入力エラーとして登録画面を表示する
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertSame($count, $this->webHookRepository->count([]));
    }

    public function testEditWithoutPayloadUrlKeepsWebHook(): void
    {
        $WebHook = new WebHook();
        $WebHook->setPayloadUrl('https://example.com/webhook');
        $WebHook->setEnabled(true);
        $this->webHookRepository->save($WebHook);
        $this->entityManager->flush();

        $this->client->request('POST', $this->generateUrl('admin_api_webhook_edit', ['id' => $WebHook->getId()]), [
            'web_hook' => ['_token' => 'dummy'],
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->entityManager->clear();
        $Saved = $this->webHookRepository->find($WebHook->getId());
        $this->assertInstanceOf(WebHook::class, $Saved);
        $this->assertSame('https://example.com/webhook', $Saved->getPayloadUrl());
    }
}
