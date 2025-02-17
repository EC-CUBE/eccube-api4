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

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Entity\Shipping;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Service\MailService;
use Eccube\Service\OrderStateMachine;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Type\Definition\DateTimeType;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

class UpdateShippedMutation implements Mutation
{
    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var OrderStateMachine
     */
    private $orderStateMachine;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var Types
     */
    private $types;

    /**
     * @var ShippingRepository
     */
    private $shippingRepository;

    public function __construct(
        EccubeConfig $eccubeConfig,
        EntityManagerInterface $entityManager,
        MailService $mailService,
        OrderStateMachine $orderStateMachine,
        OrderStatusRepository $orderStatusRepository,
        Types $types,
        ShippingRepository $shippingRepository
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->entityManager = $entityManager;
        $this->mailService = $mailService;
        $this->orderStateMachine = $orderStateMachine;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->types = $types;
        $this->shippingRepository = $shippingRepository;
    }

    public function getName()
    {
        return 'updateShipped';
    }

    public function getMutation()
    {
        return  [
            'type' => $this->types->get(Shipping::class),
            'args' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => trans('api.update_shipped.args.description.id'),
                ],
                'shipping_date' => [
                    'type' => DateTimeType::dateTime(),
                    'description' => trans('api.update_shipped.args.description.shipping_date'),
                ],
                'shipping_delivery_name' => [
                    'type' => Type::string(),
                    'description' => trans('api.update_shipped.args.description.shipping_delivery_name'),
                ],
                'tracking_number' => [
                    'type' => Type::string(),
                    'description' => trans('api.update_shipped.args.description.tracking_number'),
                ],
                'note' => [
                    'type' => Type::string(),
                    'description' => trans('api.update_shipped.args.description.note'),
                ],
                'is_send_mail' => [
                    'type' => Type::boolean(),
                    'defaultValue' => false,
                    'description' => trans('api.update_shipped.args.description.is_send_mail'),
                ],
            ],
            'resolve' => [$this, 'updateShipped'],
        ];
    }

    public function updateShipped($root, $args)
    {
        // XXX Validate with string
        if (array_key_exists('shipping_date', $args) && $args['shipping_date'] instanceof \DateTime) {
            $args['shipping_date'] = $args['shipping_date']->format(\DateTime::ATOM);
        }

        // 引数の検証
        $this->validateArgs($args);

        /** @var Shipping $Shipping */
        $Shipping = $this->shippingRepository->find($args['id']);

        // 出荷処理が可能かの検証
        $this->validateShippable($Shipping);

        // args で Shipping の出荷済み処理
        $this->updateShippingShippedWithArgs($Shipping, $args);

        // Order の出荷済み処理
        $this->updateOrderShipped($Shipping->getOrder());

        // is_send_mail が true の場合は発送通知メールを送信する
        if (array_key_exists('is_send_mail', $args) && $args['is_send_mail']) {
            $this->sendShippingNotifyMail($Shipping);
        }

        $this->entityManager->flush();

        return $Shipping;
    }

    /**
     * 引数の検証
     *
     * @throws InvalidArgumentException
     */
    private function validateArgs(array $args): void
    {
        $validator = Validation::createValidator();
        $constraint = $this->getConstraint();
        $violations = $validator->validate($args, $constraint);

        if (count($violations)) {
            $message = '';
            /** @var ConstraintViolationInterface $violation */
            foreach ($violations as $violation) {
                $message .= sprintf('%s: %s;', $violation->getPropertyPath(), $violation->getMessage());
            }
            throw new InvalidArgumentException($message);
        }
    }

    private function getConstraint(): Constraint
    {
        return new Assert\Collection([
            'fields' => [
                'id' => new Assert\GreaterThan(0),
                'shipping_date' => new Assert\DateTime('Y-m-d\TH:i:sP'),
                'shipping_delivery_name' => new Assert\Length([
                    'max' => $this->eccubeConfig['eccube_mtext_len'],
                ]),
                'tracking_number' => new Assert\Length([
                    'max' => $this->eccubeConfig['eccube_mtext_len'],
                ]),
                'note' => new Assert\Length([
                    'max' => $this->eccubeConfig['eccube_ltext_len'],
                ]),
                'is_send_mail' => new Assert\Choice([true, false]),
            ],
            'allowMissingFields' => true,
        ]);
    }

    /**
     * 出荷処理が可能かの検証
     *
     * @throws InvalidArgumentException
     */
    private function validateShippable(?Shipping $Shipping): void
    {
        // Shipping が存在しない場合
        if (is_null($Shipping)) {
            throw new InvalidArgumentException('id: No Shipping found;');
        }

        // Shipping がすでに出荷済みの場合
        if ($Shipping->isShipped()) {
            throw new InvalidArgumentException('id: Already shipped;');
        }

        /** @var OrderStatus $OrderStatus */
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::DELIVERED);

        // Order が出荷済みへ変更できない場合
        if (!$this->orderStateMachine->can($Shipping->getOrder(), $OrderStatus)) {
            throw new InvalidArgumentException('id: The associated order cannot be shipped;');
        }
    }

    /**
     * args で Shipping の出荷済み処理
     */
    private function updateShippingShippedWithArgs(Shipping $Shipping, array $args): void
    {
        // XXX shipping_date may be a string.
        if (array_key_exists('shipping_date', $args) && !$args['shipping_date'] instanceof \DateTime) {
            $args['shipping_date'] = new \DateTime($args['shipping_date']);
        }
        // Shipping を出荷済みに変更
        $Shipping->setShippingDate($args['shipping_date'] ?? new \DateTime());

        // shipping_delivery_name が指定されている場合は配送社名を更新
        if (array_key_exists('shipping_delivery_name', $args)) {
            $Shipping->setShippingDeliveryName($args['shipping_delivery_name']);
        }

        // tracking_number が指定されている場合は配送番号を更新
        if (array_key_exists('tracking_number', $args)) {
            $Shipping->setTrackingNumber($args['tracking_number']);
        }

        // note が指定されている場合は出荷用メモ欄を更新
        if (array_key_exists('note', $args)) {
            $Shipping->setNote($args['note']);
        }
    }

    /**
     * Order の出荷済み処理
     */
    private function updateOrderShipped(Order $Order): void
    {
        // Order に紐づく全ての Shipping が出荷済みか
        $allShipped = array_reduce($Order->getShippings()->toArray(), function ($carry, $Shipping) {
            return $carry && $Shipping->isShipped();
        }, true);

        /** @var OrderStatus $OrderStatus */
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::DELIVERED);

        // Shipping がすべて出荷済みなら Order を出荷済みに変更
        if ($allShipped) {
            $this->orderStateMachine->apply($Order, $OrderStatus);
        }
    }

    /**
     * 発送通知メールを送信する
     *
     * @throws InvalidArgumentException
     */
    private function sendShippingNotifyMail(Shipping $Shipping): void
    {
        try {
            $this->mailService->sendShippingNotifyMail($Shipping);
            $Shipping->setMailSendDate(new \DateTime());
        } catch (\Exception $e) {
            // メール送信エラー
            throw new InvalidArgumentException('is_send_mail: Error sending email;');
        }
    }
}
