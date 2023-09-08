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

use Eccube\Entity\ItemHolderInterface;
use Eccube\Entity\Order;
use Eccube\Http\RedirectResponse;
use Eccube\ORM\EntityManager;
use Eccube\ORM\Exception\ForeignKeyConstraintViolationException;
use Eccube\ORM\Exception\ORMException;
use Eccube\Security\SecurityContext;
use Eccube\Service\CartService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Error\ShoppingException;
use Plugin\Api42\GraphQL\Mutation;
use Plugin\Api42\GraphQL\Types;

class ShoppingMutation implements Mutation
{
    private Types $types;

    private EntityManager $entityManager;
    private CartService $cartService;
    private OrderHelper $orderHelper;
    private SecurityContext $securityContext;
    private PurchaseFlow $cartPurchaseFlow;

    public function __construct(
        Types $types,
        EntityManager $entityManager,
        CartService $cartService,
        OrderHelper $orderHelper,
        SecurityContext $securityContext,
        PurchaseFlow $cartPurchaseFlow,
    ) {
        $this->types = $types;
        $this->entityManager = $entityManager;
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->securityContext = $securityContext;
        $this->cartPurchaseFlow = $cartPurchaseFlow;
    }

    public function getName(): string
    {
        return 'orderMutation';
    }

    public function getMutation(): array
    {
        return [
            'type' => $this->types->get(Order::class),
            'args' => [],
            'resolve' => [$this, 'orderMutation'],
        ];
    }

    /**
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws ForeignKeyConstraintViolationException
     */
    public function orderMutation($root, $args): ?Order
    {
        $user = $this->securityContext->getLoginUser();

        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            $message = '[注文手続] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.';
            log_info($mssage);
            throw new ShoppingException($message);
        }

        // カートチェック.
        $Cart = $this->cartService->getCart();
        if (!($Cart && $this->orderHelper->verifyCart($Cart))) {
            $message = '[注文手続] カートが購入フローへ遷移できない状態のため, カート画面に遷移します.';
            log_info($mssage);
            throw new ShoppingException($message);
        }

        // 受注の初期化.
        log_info('[注文手続] 受注の初期化処理を開始します.');
        $Customer = $user ? $user : $this->orderHelper->getNonMember();
        $Order = $this->orderHelper->initializeOrder($Cart, $Customer);

        // 集計処理.
        log_info('[注文手続] 集計処理を開始します.', [$Order->getId()]);
        $flowResult = $this->executePurchaseFlow($Order, false);
        $this->entityManager->flush();

        if ($flowResult->hasError()) {
            $message = '[注文手続] Errorが発生したため購入エラー画面へ遷移します.';
            log_info($message, [$flowResult->getErrors()]);
            throw new ShoppingException($message);
        }

        if ($flowResult->hasWarning()) {
            log_info('[注文手続] Warningが発生しました.', [$flowResult->getWarning()]);

            // 受注明細と同期をとるため, CartPurchaseFlowを実行する
            $this->cartPurchaseFlow->validate($Cart, new PurchaseContext($Cart, $user));

            // 注文フローで取得されるカートの入れ替わりを防止する
            // @see https://github.com/EC-CUBE/ec-cube/issues/4293
            $this->cartService->setPrimary($Cart->getCartKey());
        }

        // マイページで会員情報が更新されていれば, Orderの注文者情報も更新する.
        if ($Customer->getId()) {
            $this->orderHelper->updateCustomerInfo($Order, $Customer);
            $this->entityManager->flush();
        }

        return $Order;
    }

    /**
     * @param ItemHolderInterface $itemHolder
     * @param bool $returnResponse レスポンスを返すかどうか. falseの場合はPurchaseFlowResultを返す.
     *
     * @return PurchaseFlowResult|RedirectResponse|null
     *
     * @throws InvalidArgumentException
     */
    protected function executePurchaseFlow(ItemHolderInterface $itemHolder, bool $returnResponse = true): PurchaseFlowResult|RedirectResponse|null
    {
        $flowResult = $this->cartPurchaseFlow->validate($itemHolder, new PurchaseContext(clone $itemHolder, $itemHolder->getCustomer()));
        foreach ($flowResult->getWarning() as $warning) {
            throw new InvalidArgumentException(); // TODO AbstractMutation::addWaring を使用する
        }
        foreach ($flowResult->getErrors() as $error) {
            throw new ShoppingException($error->getMessage());
        }

        if (!$returnResponse) {
            return $flowResult;
        }

        return null;
    }
}
