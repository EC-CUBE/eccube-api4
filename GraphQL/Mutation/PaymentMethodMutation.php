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

namespace Plugin\Api42\GraphQL\Mutation;

use Eccube\Entity\Delivery;
use Eccube\Entity\Order;
use Eccube\Entity\Payment;
use Eccube\ORM\Collections\ArrayCollection;
use Eccube\ORM\EntityManager;
use Eccube\Service\CartService;
use Eccube\Service\OrderHelper;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;

class PaymentMethodMutation implements Mutation
{
    private Types $types;
    private CartService $cartService;
    private OrderHelper $orderHelper;
    private EntityManager $entityManager;

    public function __construct(
        Types $types,
        EntityManager $entityManager,
        CartService $cartService,
        OrderHelper $orderHelper,
    ) {
        $this->types = $types;
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->entityManager = $entityManager;
    }

    public function getName(): string
    {
        return 'paymentMethodMutation';
    }

    public function getMutation(): array
    {
        return [
            'type' => Type::listOf($this->types->get(Payment::class)),
            'args' => [
                'payment_method_id' => [
                    'type' => Type::id(),
                    'description' => '支払い方法ID',
                ],
            ],
            'resolve' => [$this, 'paymentMethodMutation'],
        ];
    }

    public function paymentMethodMutation($root, $args)
    {
        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        /** @var Order|null $Order */
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            log_info('[注文確認] 購入処理中の受注が存在しません.', [$preOrderId]);
            throw new InvalidArgumentException();
        }

        if (!empty($args['payment_method_id'])) {
            /** @var Payment|null $Payment */
            $Payment = $this->entityManager->find(Payment::class, $args['payment_method_id']);
            if (!$Payment) {
                log_info('[注文確認] 支払い方法が存在しません.', [$args['payment_method_id']]);
                throw new InvalidArgumentException();
            }
            log_info('[注文確認] 支払い方法を変更します.', [$Payment->getMethod()]);
            $Order->setPayment($Payment);
            $this->entityManager->persist($Order);
            $this->entityManager->flush();

        }

        $Deliveries = [];
        if (!empty($Order->getShippings())) {
            foreach ($Order->getShippings() as $Shipping) {
                if (!empty($Shipping->getDelivery())) {
                    $Deliveries[] = $Shipping->getDelivery();
                }
            }
        }

        $Payments = $this->getPayments($Deliveries);
        // @see https://github.com/EC-CUBE/ec-cube/issues/4881
        $charge = $Order->getPayment() ? $Order->getPayment()->getCharge() : 0;

        return $this->filterPayments($Payments, $Order->getPaymentTotal() - $charge);
    }

    /**
     * 配送方法に紐づく支払い方法を取得する
     * 各配送方法に共通する支払い方法のみ返す.
     *
     * @param Delivery[] $Deliveries
     *
     * @return ArrayCollection
     */
    private function getPayments($Deliveries)
    {
        $PaymentsByDeliveries = [];
        foreach ($Deliveries as $Delivery) {
            $PaymentOptions = $Delivery->getPaymentOptions();
            foreach ($PaymentOptions as $PaymentOption) {
                /** @var Payment $Payment */
                $Payment = $PaymentOption->getPayment();
                if ($Payment->isVisible()) {
                    $PaymentsByDeliveries[$Delivery->getId()][] = $Payment;
                }
            }
        }

        if (empty($PaymentsByDeliveries)) {
            return new ArrayCollection();
        }

        $i = 0;
        $PaymentsIntersected = [];
        foreach ($PaymentsByDeliveries as $Payments) {
            if ($i === 0) {
                $PaymentsIntersected = $Payments;
            } else {
                $PaymentsIntersected = array_intersect($PaymentsIntersected, $Payments);
            }
            $i++;
        }

        return new ArrayCollection($PaymentsIntersected);
    }

    /**
     * 支払い方法の利用条件でフィルタをかける.
     *
     * @param ArrayCollection $Payments
     * @param $total
     *
     * @return Payment[]
     */
    private function filterPayments(ArrayCollection $Payments, $total)
    {
        $PaymentArrays = $Payments->filter(function (Payment $Payment) use ($total) {
            $charge = $Payment->getCharge();
            $min = $Payment->getRuleMin();
            $max = $Payment->getRuleMax();

            if (null !== $min && ($total + $charge) < $min) {
                return false;
            }

            if (null !== $max && ($total + $charge) > $max) {
                return false;
            }

            return true;
        })->toArray();
        usort($PaymentArrays, function (Payment $a, Payment $b) {
            return $a->getSortNo() < $b->getSortNo() ? 1 : -1;
        });

        return $PaymentArrays;
    }
}
