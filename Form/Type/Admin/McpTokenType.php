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

namespace Plugin\Api44\Form\Type\Admin;

use Plugin\Api44\Service\McpTokenService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * MCP トークン発行フォーム。 ラベル・MCP scope・有効期限を入力する。
 * scope の選択肢は MCP の領域別 read のみ (発行できる権限を構造的に制限する)。
 */
class McpTokenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scopeChoices = array_combine(McpTokenService::AVAILABLE_SCOPES, McpTokenService::AVAILABLE_SCOPES);
        // 有効日数は McpTokenService を単一のソースにする (choices と Assert\Choice の二重管理を避ける)。
        $expireDays = McpTokenService::AVAILABLE_EXPIRE_DAYS;
        $expireChoices = array_combine(array_map(static fn (int $d): string => $d.'日', $expireDays), $expireDays);

        $builder
            ->add('label', TextType::class, [
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 255]),
                ],
            ])
            ->add('scopes', ChoiceType::class, [
                'mapped' => false,
                'choices' => $scopeChoices,
                'expanded' => true,
                'multiple' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('expire', ChoiceType::class, [
                'mapped' => false,
                'choices' => $expireChoices,
                'data' => $expireDays[0],
                'expanded' => false,
                'multiple' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(['choices' => $expireDays]),
                ],
            ]);
    }
}
