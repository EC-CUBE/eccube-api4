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

namespace Plugin\Api42\GraphQL\Query;

use Eccube\Entity\Cart;
use Eccube\Security\SecurityContext;
use Eccube\Service\CartService;

class CartsQuery extends MultipleResultQuery
{
    private CartService $cartService;

    /**
     * ProductQuery constructor.
     * @param SecurityContext $securityContext
     * @param CartService $cartService
     */
    public function __construct(SecurityContext $securityContext, CartService $cartService)
    {
        parent::__construct(Cart::class, $securityContext, $cartService);
        $this->cartService = $cartService;
    }

    public function runResolver($root, $args)
    {
        return $this->cartService->getCarts();
    }

    public function getArgs(): ?array
    {
        return [];
    }

    public function getName(): string
    {
        return 'carts';
    }
}
