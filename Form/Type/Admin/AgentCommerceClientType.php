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

use Eccube\Common\EccubeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * エージェントコマース (ACP/UCP) 用 OAuth2 クライアントの登録フォーム (#188)。
 *
 * 汎用の {@link ClientType} と分けているのは、 エージェントコマースでは grant と scope の
 * 組み合わせが一意に決まるためである。 エージェントは会員でもブラウザでもないので同意画面を
 * 経由できず、 grant は client_credentials 固定・redirect_uri は不使用になる。 汎用フォームで
 * 全 scope と全 grant を並べると、 成立しない組み合わせ (例: acp:checkout × authorization_code)
 * を作れてしまうため、 protocol ごとに入口を分けて画面側で整合を保証する。
 *
 * 1 クライアントに ACP と UCP を混在させないのも本フォームの目的である。 受注に記録される
 * `Order.agent_id` は OAuth2 クライアント識別子なので、 事業者ごとにクライアントを分けないと
 * 受注の帰属・失効・レート制御を事業者単位で扱えない。
 */
class AgentCommerceClientType extends AbstractType
{
    /**
     * protocol => この画面から付与できる scope。
     *
     * `Resource/config/services.yaml` の `scopes.available` と同期させること
     * (league は available に無い scope を拒否する)。
     *
     * `ucp:identity` は**意図的に含めない**。 会員本人の同意のもとで発行する capability であり、
     * Customer を subject とする authorization_code が前提になるため client_credentials では
     * 成立しない (eccube-api4#189)。 #189 landing 後に会員同意を伴う別導線として追加する。
     *
     * @var array<string, list<string>>
     */
    public const PROTOCOL_SCOPES = [
        'acp' => ['acp:checkout', 'acp:catalog'],
        'ucp' => ['ucp:checkout', 'ucp:cart', 'ucp:catalog'],
    ];

    public function __construct(
        private readonly EccubeConfig $eccubeConfig,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scopes = self::PROTOCOL_SCOPES[$options['protocol']];

        $builder
            // どのエージェント事業者向けのクライアントかを後から追えるようにする (Client::name)
            ->add('name', TextType::class, [
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                ],
            ])
            ->add('identifier', TextType::class, [
                'mapped' => false,
                'data' => hash('md5', random_bytes(16)),
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 32]),
                    new Assert\Regex(['pattern' => '/^[0-9a-zA-Z]+$/']),
                ],
            ])
            ->add('secret', TextType::class, [
                'mapped' => false,
                'data' => hash('sha512', random_bytes(32)),
                'constraints' => [
                    new Assert\NotBlank(),
                    // client_credentials ではシークレットが唯一の認証情報なので下限を設ける
                    // (既定値は sha512 hex = 128 文字なので、 手で書き換えた場合にだけ効く)。
                    new Assert\Length(['min' => 32, 'max' => 128]),
                    new Assert\Regex(['pattern' => '/^[0-9a-zA-Z]+$/']),
                ],
            ])
            ->add('scopes', ChoiceType::class, [
                'choices' => array_combine($scopes, $scopes),
                'expanded' => true,
                'multiple' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    // choices 外の scope を弾いているのは実際には ChoiceType 自身である。
                    // PRE_SUBMIT で未知値を submitted data から除去し、 POST_SUBMIT で FormError を
                    // 積む (`ChoiceType::buildForm()`)。 本制約はその機構に依存しないための多層防御で、
                    // 制約評価時にはデータが choices 済みに絞られているため通常は発火しない。
                    new Assert\All([new Assert\Choice(['choices' => $scopes])]),
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('protocol');
        $resolver->setAllowedValues('protocol', array_keys(self::PROTOCOL_SCOPES));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'api_admin_agent_commerce_client';
    }
}
