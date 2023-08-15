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

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Cart;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Entity\ProductClass;
use Eccube\Repository\ProductClassRepository;
use Eccube\Security\SecurityContext;
use Eccube\Service\CartService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

class CartModifyMutation implements Mutation
{
    private Types $types;
    private CartService $cartService;
    private ProductClassRepository $productClassRepository;
    private EccubeConfig $eccubeConfig;
    private PurchaseFlow $purchaseFlow;
    private SecurityContext $securityContext;

    public function __construct(
        Types $types,
        CartService $cartService,
        ProductClassRepository $productClassRepository,
        EccubeConfig $eccubeConfig,
        SecurityContext $securityContext,
        PurchaseFlow $cartPurchaseFlow
    ) {
        $this->types = $types;
        $this->cartService = $cartService;
        $this->productClassRepository = $productClassRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->securityContext = $securityContext;
    }

    public function getName(): string
    {
        return 'cartModify';
    }

    public function getMutation(): array
    {
        return [
            'type' => $this->types->get(Cart::class),
            'args' => [
                'product_class_id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => trans('api.cart_modify.args.description.product_class_id'),
                ],
                'quantity' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => trans('api.cart_modify.args.description.quantity'),
                ],
            ],
            'resolve' => [$this, 'updateCart'],
        ];
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
                'product_class_id' => new Assert\GreaterThan(0),
                'quantity' => new Assert\GreaterThanOrEqual(0),
            ],
            'allowMissingFields' => false,
        ]);
    }

    /**
     * 閲覧可能な商品かどうかを判定
     *
     * @param Product $Product
     *
     * @return boolean 閲覧可能な場合はtrue
     */
    protected function checkVisibility(Product $Product): bool
    {
        // 公開ステータスでない商品は表示しない.
        if ($Product->getStatus()->getId() !== ProductStatus::DISPLAY_SHOW) {
            return false;
        }

        return true;
    }

    /**
     * @param $root
     * @param $args
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function updateCart($root, $args)
    {
        // 引数の検証
        $this->validateArgs($args);

        /** @var ProductClass|null $productClass */
        $productClass = $this->productClassRepository->find($args['product_class_id']);

        if (!$productClass) {
            // @TODO: エラーメッセージを作成
            throw new InvalidArgumentException();
        }

        // エラーメッセージの配列
        $errorMessages = [];
        if (!$this->checkVisibility($productClass->getProduct())) {
            // @TODO: エラーメッセージを作成
            throw new InvalidArgumentException();
        }

        log_info(
            'カート追加処理開始',
            [
                'product_id' => $productClass->getProduct()->getId(),
                'product_class_id' => $productClass->getId(),
                'quantity' => $args['quantity'],
            ]
        );

        // カートへ追加
        $this->cartService->addProduct($productClass, $args['quantity']);

        // 明細の正規化
        $Carts = $this->cartService->getCarts();
        foreach ($Carts as $Cart) {
            $result = $this->purchaseFlow->validate($Cart, new PurchaseContext($Cart, $this->securityContext->getLoginUser()));
            // 復旧不可のエラーが発生した場合は追加した明細を削除.
            if ($result->hasError()) {
                $this->cartService->removeProduct($productClass);
                foreach ($result->getErrors() as $error) {
                    $errorMessages[] = $error->getMessage();
                }
            }
            foreach ($result->getWarning() as $warning) {
                $errorMessages[] = $warning->getMessage();
            }
        }

        $this->cartService->save();

        log_info(
            'カート追加処理完了',
            [
                'product_id' => $productClass->getProduct()->getId(),
                'product_class_id' => $productClass->getId(),
                'quantity' => $args['quantity'],
            ]
        );

        // 初期化
        $messages = [];

        if (empty($errorMessages)) {
            // エラーが発生していない場合
            $done = true;
            array_push($messages, trans('front.product.add_cart_complete'));
        } else {
            // エラーが発生している場合
            $done = false;
            $messages = $errorMessages;
        }

        // @TODO: Cartの配列を返すようにする
        return $Carts[0];
    }
}
