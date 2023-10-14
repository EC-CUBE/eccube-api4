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

use Eccube\Entity\Customer;
use Eccube\Entity\ItemHolderInterface;
use Eccube\Entity\Order;
use Eccube\Exception\ShoppingException;
use Eccube\Http\RedirectResponse;
use Eccube\Http\Response;
use Eccube\ORM\EntityManager;
use Eccube\Security\SecurityContext;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentMethodLocator;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Plugin\Api42\GraphQL\Error\InvalidArgumentException;
use Plugin\Api42\GraphQL\Types;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;

class PurchaseMutation extends AbstractMutation
{
    private EntityManager $entityManager;
    private CartService $cartService;
    private OrderHelper $orderHelper;
    private SecurityContext $securityContext;
    private PurchaseFlow $cartPurchaseFlow;
    private PaymentMethodLocator $locator;
    private MailService $mailService;

    public function __construct(
        Types $types,
        EntityManager $entityManager,
        CartService $cartService,
        OrderHelper $orderHelper,
        SecurityContext $securityContext,
        PurchaseFlow $cartPurchaseFlow,
        PaymentMethodLocator $locator,
        MailService $mailService,
        FormFactoryInterface $formFactory,
    ) {
        $this->entityManager = $entityManager;
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->securityContext = $securityContext;
        $this->cartPurchaseFlow = $cartPurchaseFlow;
        $this->locator = $locator;
        $this->mailService = $mailService;
        $this->setTypes($types);
        $this->setFormFactory($formFactory);
    }

    public function getName(): string
    {
        return 'purchaseMutation';
    }

    public function getTypesClass(): string
    {
        return Order::class;
    }

    public function getArgsType(): string
    {
        return FormType::class;
    }

    public function executeMutation($root, array $args): mixed
    {
        $Customer = $this->securityContext->getLoginUser();
        if (!$Customer instanceof Customer) {
            throw new ShoppingException('ログインしていません');
        }
        // ログイン状態のチェック.
        if ($this->orderHelper->isLoginRequired()) {
            $message = '[注文処理] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.';
            log_info($message);
            throw new ShoppingException($message);
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);
        if (!$Order) {
            $message = '[注文処理] 購入処理中の受注が存在しません.';
            log_info($message, [$preOrderId]);
            throw new ShoppingException($message);
        }

        log_info('[注文処理] 注文処理を開始します.', [$Order->getId()]);

        try {
            /*
             * 集計処理
             */
            log_info('[注文処理] 集計処理を開始します.', [$Order->getId()]);
            $response = $this->executePurchaseFlow($Order);
            $this->entityManager->flush();

            if ($response) {
                return $response;
            }

            // @TODO: 未対応
//            log_info('[注文完了] IPベースのスロットリングを実行します.');
//            $ipLimiter = $this->shoppingCheckoutIpLimiter->create($request->getClientIp());
//            if (!$ipLimiter->consume()->isAccepted()) {
//                log_info('[注文完了] 試行回数制限を超過しました(IPベース)');
//                throw new TooManyRequestsHttpException();
//            }
//
            // @TODO: 未対応
//            if ($Customer instanceof Customer) {
//                log_info('[注文完了] 会員ベースのスロットリングを実行します.');
//                $customerLimiter = $this->shoppingCheckoutCustomerLimiter->create($Customer->getId());
//                if (!$customerLimiter->consume()->isAccepted()) {
//                    log_info('[注文完了] 試行回数制限を超過しました(会員ベース)');
//                    throw new TooManyRequestsHttpException();
//                }
//            }

            log_info('[注文処理] PaymentMethodを取得します.', [$Order->getPayment()->getMethodClass()]);
            $paymentMethod = $this->createPaymentMethod($Order);

            /*
             * 決済実行(前処理)
             */
            log_info('[注文処理] PaymentMethod::applyを実行します.');
            if ($response = $this->executeApply($paymentMethod)) {
                // @TODO: ここでどうするか?
                return $response;
            }

            /*
             * 決済実行
             *
             * PaymentMethod::checkoutでは決済処理が行われ, 正常に処理出来た場合はPurchaseFlow::commitがコールされます.
             */
            log_info('[注文処理] PaymentMethod::checkoutを実行します.');
            if ($response = $this->executeCheckout($paymentMethod)) {
                // @TODO: ここでどうするか?
                return $response;
            }

            $this->entityManager->flush();

            log_info('[注文処理] 注文処理が完了しました.', [$Order->getId()]);
        } catch (ShoppingException $e) {
            log_error('[注文処理] 購入エラーが発生しました.', [$e->getMessage()]);

            $this->entityManager->rollback();

            throw new ShoppingException($e->getMessage());
        } catch (\Exception $e) {
            log_error('[注文処理] 予期しないエラーが発生しました.', [$e->getMessage()]);

            // $this->entityManager->rollback(); FIXME ユニットテストで There is no active transaction エラーになってしまう

            throw new ShoppingException($e->getMessage());
        }

        // カート削除
        log_info('[注文処理] カートをクリアします.', [$Order->getId()]);
        $this->cartService->clear();

        // @TODO: 未対応
//        // 受注IDをセッションにセット
//        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        // メール送信
        log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
        $this->mailService->sendOrderMail($Order);
        $this->entityManager->flush();

        log_info('[注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);

        return $Order;
    }

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @param PurchaseFlow $shoppingPurchaseFlow
     *
     * @required
     */
    public function setPurchaseFlow(PurchaseFlow $shoppingPurchaseFlow)
    {
        $this->purchaseFlow = $shoppingPurchaseFlow;
    }

    /**
     * @param ItemHolderInterface $itemHolder
     * @param bool $returnResponse レスポンスを返すかどうか. falseの場合はPurchaseFlowResultを返す.
     *
     * @return PurchaseFlowResult|RedirectResponse|null
     */
    protected function executePurchaseFlow(ItemHolderInterface $itemHolder, $returnResponse = true)
    {
        /** @var PurchaseFlowResult $flowResult */
        $flowResult = $this->purchaseFlow->validate($itemHolder, new PurchaseContext(clone $itemHolder, $itemHolder->getCustomer()));
        foreach ($flowResult->getWarning() as $warning) {
            $this->addWarning($warning->getMessage());
        }
        foreach ($flowResult->getErrors() as $error) {
            throw new ShoppingException($error->getMessage());
        }

        if (!$returnResponse) {
            return $flowResult;
        }

        if ($flowResult->hasError()) {
            log_info('Errorが発生したため購入エラー画面へ遷移します.', [$flowResult->getErrors()]);
        }

        if ($flowResult->hasWarning()) {
            log_info('Warningが発生したため注文手続き画面へ遷移します.', [$flowResult->getWarning()]);
        }

        return null;
    }

    /**
     * PaymentMethod::applyを実行する.
     *
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return InvalidArgumentException|Response
     */
    protected function executeApply(PaymentMethodInterface $paymentMethod)
    {
        $dispatcher = $paymentMethod->apply(); // 決済処理中.

        // リンク式決済のように他のサイトへ遷移する場合などは, dispatcherに処理を移譲する.
        if ($dispatcher instanceof PaymentDispatcher) {
            $response = $dispatcher->getResponse();
            $this->entityManager->flush();

            // dispatcherがresponseを保持している場合はresponseを返す
            if ($response instanceof Response && ($response->isRedirection() || $response->isSuccessful())) {
                log_info('[注文処理] PaymentMethod::applyが指定したレスポンスを表示します.');

                return $response;
            }

            // forwardすることも可能.
            if ($dispatcher->isForward()) {
                // TODO: forwardの場合は, ここで購入処理を中断する必要があるかもしれない.
                log_info('[注文処理] PaymentMethod::applyによりForwardします.',
                    [$dispatcher->getRoute(), $dispatcher->getPathParameters(), $dispatcher->getQueryParameters()]);

                // @TODO: 未対応
                return new InvalidArgumentException();
//                return $this->forwardToRoute($dispatcher->getRoute(), $dispatcher->getPathParameters(),
//                    $dispatcher->getQueryParameters());
            } else {
                // TODO: リダイレクトする場合は, ここで購入処理を中断する必要がある.
                log_info('[注文処理] PaymentMethod::applyによりリダイレクトします.',
                    [$dispatcher->getRoute(), $dispatcher->getPathParameters(), $dispatcher->getQueryParameters()]);

                // @TODO: 未対応
                return new InvalidArgumentException();

//                return $this->redirectToRoute($dispatcher->getRoute(),
//                    array_merge($dispatcher->getPathParameters(), $dispatcher->getQueryParameters()));
            }
        }
    }

    /**
     * PaymentMethod::checkoutを実行する.
     *
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return \Eccube\Http\RedirectResponse|\Eccube\Http\Response|null
     */
    protected function executeCheckout(PaymentMethodInterface $paymentMethod)
    {
        $PaymentResult = $paymentMethod->checkout();
        $response = $PaymentResult->getResponse();
        // PaymentResultがresponseを保持している場合はresponseを返す
        if ($response instanceof Response && ($response->isRedirection() || $response->isSuccessful())) {
            $this->entityManager->flush();
            log_info('[注文処理] PaymentMethod::checkoutが指定したレスポンスを表示します.');

            return $response;
        }

        // エラー時はロールバックして購入エラーとする.
        if (!$PaymentResult->isSuccess()) {
            $this->entityManager->rollback();
            foreach ($PaymentResult->getErrors() as $error) {
                throw new ShoppingException($error->getMessage());
            }

            log_info('[注文処理] PaymentMethod::checkoutのエラーのため, 購入エラー画面へ遷移します.', [$PaymentResult->getErrors()]);

            throw new InvalidArgumentException();
        }

        return null;
    }

    private function createPaymentMethod(Order $Order)
    {
        $PaymentMethod = $this->locator->get($Order->getPayment()->getMethodClass());
        $PaymentMethod->setOrder($Order);

        return $PaymentMethod;
    }
}
