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

namespace Plugin\Api44\Tests\GraphQL\Mutation;

use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Service\MailService;
use Eccube\Service\OrderStateMachine;
use Eccube\Tests\EccubeTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Plugin\Api44\GraphQL\Error\InvalidArgumentException;
use Plugin\Api44\GraphQL\Mutation\UpdateShippedMutation;
use Plugin\Api44\GraphQL\Types;

class UpdateShippedMutationTest extends EccubeTestCase
{
    /**
     * @var UpdateShippedMutation
     */
    private ?UpdateShippedMutation $updateShippedMutation = null;

    /**
     * @var Order
     */
    private ?Order $Order = null;

    public function setUp(): void
    {
        parent::setUp();

        $mailService = self::getContainer()->get(MailService::class);
        $orderStateMachine = self::getContainer()->get(OrderStateMachine::class);
        $orderStatusRepository = self::getContainer()->get(OrderStatusRepository::class);
        $types = self::getContainer()->get(Types::class);
        $shippingRepository = self::getContainer()->get(ShippingRepository::class);

        $this->updateShippedMutation = new UpdateShippedMutation(
            $this->eccubeConfig,
            $this->entityManager,
            $mailService,
            $orderStateMachine,
            $orderStatusRepository,
            $types,
            $shippingRepository
        );

        // 出荷可能な受注を作成
        $Customer = $this->createCustomer();
        $this->Order = $this->createOrder($Customer);
        $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::NEW);
        $this->Order->setOrderStatus($OrderStatus);
        $this->entityManager->flush();
    }

    /**
     * 正常系
     */
    public function testResponseAndDB(): void
    {
        $dateTime = \DateTime::createFromFormat(\DateTime::ATOM, '2020-05-18T12:57:08+09:00');
        $args = [
            'id' => $this->Order->getShippings()->current()->getId(),
            'shipping_date' => $dateTime,
            'shipping_delivery_name' => 'テスト配送業者',
            'tracking_number' => 'tracking_number_0123',
            'note' => 'notes_0123456789',
            'is_send_mail' => false,
        ];

        try {
            $Shipping = $this->updateShippedMutation->updateShipped(null, $args);

            // レスポンスの確認
            self::assertEquals($dateTime, $Shipping->getShippingDate());
            self::assertEquals('テスト配送業者', $Shipping->getShippingDeliveryName());
            self::assertEquals('tracking_number_0123', $Shipping->getTrackingNumber());
            self::assertEquals('notes_0123456789', $Shipping->getNote());
        } catch (InvalidArgumentException $e) {
            // 通らない
            self::fail($e->getMessage());
        }

        // DB の確認
        $this->entityManager->refresh($this->Order);
        self::assertEquals($dateTime, $this->Order->getShippings()->current()->getShippingDate());
        self::assertEquals('テスト配送業者', $this->Order->getShippings()->current()->getShippingDeliveryName());
        self::assertEquals('tracking_number_0123', $this->Order->getShippings()->current()->getTrackingNumber());
        self::assertEquals('notes_0123456789', $this->Order->getShippings()->current()->getNote());
    }

    /**
     * 引数のバリデーションチェック
     *
     * @param array<string, bool|int|string|\DateTime> $args
     */
    #[DataProvider('validateArgsProvider')]
    public function testValidateArgs(array $args = [], ?string $message = null): void
    {
        $args = array_merge($args, ['id' => $this->Order->getShippings()->current()->getId()]);

        try {
            $Shipping = $this->updateShippedMutation->updateShipped(null, $args);
            // Shipping が出荷済みになっている
            self::assertNotNull($Shipping->getShippingDate());
            self::assertNotNull($this->Order->getShippings()->current()->getShippingDate());
        } catch (InvalidArgumentException $e) {
            // エラーの確認
            self::assertTrue($e->isClientSafe());
            if ($message !== null) {
                self::assertStringContainsString($message, $e->getMessage());
            }
            // Shipping が出荷済みになっていない
            self::assertNull($this->Order->getShippings()->current()->getShippingDate());
        }
    }

    /**
     * @return array<int, array{0: array<string, bool|int|string|\DateTime>, 1?: string}>
     */
    public static function validateArgsProvider(): array
    {
        // dataProvider 実行時点で eccubeConfig がまだ使えないのでベタがきする。
        $eccube_mtext_len = 200;
        $eccube_ltext_len = 3000;
        $str_eccube_mtext_len = str_repeat('a', $eccube_mtext_len);
        $str_eccube_mtext_len_plus = str_repeat('a', $eccube_mtext_len + 1);
        $str_eccube_ltext_len = str_repeat('a', $eccube_ltext_len);
        $str_eccube_ltext_len_plus = str_repeat('a', $eccube_ltext_len + 1);

        $dateTime = \DateTime::createFromFormat(\DateTime::ATOM, '2020-05-18T12:57:08+09:00');

        return [
            [['id' => -1], '/id/'],
            [['shipping_date' => $dateTime]],
            [['shipping_delivery_name' => $str_eccube_mtext_len]],
            [['shipping_delivery_name' => $str_eccube_mtext_len_plus], 'shipping_delivery_name'],
            [['tracking_number' => $str_eccube_mtext_len]],
            [['tracking_number' => $str_eccube_mtext_len_plus], 'tracking_number'],
            [['note' => $str_eccube_ltext_len]],
            [['note' => $str_eccube_ltext_len_plus], 'note'],
            [['is_send_mail' => false]],
        ];
    }

    /**
     * 対象の出荷情報がない場合
     */
    public function testNoShippingFound(): void
    {
        $args = ['id' => 9999];

        try {
            $this->updateShippedMutation->updateShipped(null, $args);
            // 通らない
            self::fail();
        } catch (InvalidArgumentException $e) {
            // エラーの確認
            self::assertTrue($e->isClientSafe());
            self::assertMatchesRegularExpression('/No Shipping found/', $e->getMessage());
        }
    }

    /**
     * 対象の出荷情報がすでに出荷済みの場合
     */
    public function testAlreadyShipped(): void
    {
        // Shipping を出荷済みに変更
        /** @var Shipping $Shipping */
        $Shipping = $this->Order->getShippings()->current();
        $Shipping->setShippingDate(new \DateTime());
        $this->entityManager->flush();

        $args = ['id' => $Shipping->getId()];

        try {
            $this->updateShippedMutation->updateShipped(null, $args);
            // 通らない
            self::fail();
        } catch (InvalidArgumentException $e) {
            // エラーの確認
            self::assertTrue($e->isClientSafe());
            self::assertMatchesRegularExpression('/Already shipped/', $e->getMessage());
        }
    }

    /**
     * 対象の受注情報が出荷済みにできない受注ステータスだった場合
     */
    public function testOrderCannotBeShipped(): void
    {
        // 受注をキャンセルに変更
        $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::CANCEL);
        $this->Order->setOrderStatus($OrderStatus);
        $this->entityManager->flush();

        $args = ['id' => $this->Order->getShippings()->current()->getId()];

        try {
            $this->updateShippedMutation->updateShipped(null, $args);
            // 通らない
            self::fail();
        } catch (InvalidArgumentException $e) {
            // エラーの確認
            self::assertTrue($e->isClientSafe());
            self::assertMatchesRegularExpression('/order cannot be shipped/', $e->getMessage());
        }
    }

    /**
     * 対象の受注の出荷情報が全て出荷済みになれば受注を出荷済みにする
     */
    public function testOrderShipped(): void
    {
        // Orderを新しく作り、Shippingをもう一方に付け替える
        $Order = $this->createOrder($this->Order->getCustomer());
        /** @var Shipping $Shipping */
        $Shipping = $Order->getShippings()->current();
        $Shipping->setOrder($this->Order);
        $Order->removeShipping($Shipping);
        $this->Order->addShipping($Shipping);
        $this->entityManager->flush();

        // １個目の出荷情報を出荷済みに変更
        $args = ['id' => $this->Order->getShippings()[0]->getId()];

        try {
            $Shipping = $this->updateShippedMutation->updateShipped(null, $args);
            // Shipping が出荷済みになっている
            self::assertNotNull($Shipping->getShippingDate());
            self::assertNotNull($this->Order->getShippings()[0]->getShippingDate());
            // 受注は出荷済みになっていない
            $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::NEW);
            self::assertEquals($OrderStatus, $this->Order->getOrderStatus());
        } catch (InvalidArgumentException $e) {
            // 通らない
            self::fail();
        }

        // ２個目の出荷情報を出荷済みに変更
        $args = ['id' => $this->Order->getShippings()[1]->getId()];

        try {
            $Shipping = $this->updateShippedMutation->updateShipped(null, $args);
            // Shipping が出荷済みになっている
            self::assertNotNull($Shipping->getShippingDate());
            self::assertNotNull($this->Order->getShippings()[1]->getShippingDate());
            // 受注も出荷済みになっている
            $OrderStatus = $this->entityManager->getRepository(OrderStatus::class)->find(OrderStatus::DELIVERED);
            self::assertEquals($OrderStatus, $this->Order->getOrderStatus());
        } catch (InvalidArgumentException $e) {
            // 通らない
            self::fail();
        }
    }
}
