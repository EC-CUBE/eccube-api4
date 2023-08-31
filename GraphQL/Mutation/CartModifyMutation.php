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
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Plugin\Api42\Form\Type\Front\AddCartType;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

class CartModifyMutation extends AbstractMutation
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
        PurchaseFlow $cartPurchaseFlow,
        FormFactoryInterface $formFactory
    ) {
        $this->types = $types;
        $this->cartService = $cartService;
        $this->productClassRepository = $productClassRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->securityContext = $securityContext;
        $this->setTypes($types);
        $this->setFormFactory($formFactory);
    }

    public function getName(): string
    {
        return 'cartModify';
    }

    public function getArgsType(): string
    {
        return AddCartType::class;
    }

    public function getTypesClass(): string
    {
        return Cart::class;
    }

    /**
     * @param $root
     * @param $args
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function executeMutation($root, array $args): mixed
    {
        /** @var ProductClass|null $productClass */
        $productClass = $this->productClassRepository->find($args['product_class_id']);

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

        // @TODO: Cartの配列を返すようにする
         return $Carts[0];
    }
}
