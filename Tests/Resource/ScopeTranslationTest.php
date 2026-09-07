<?php

declare(strict_types=1);

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

namespace Plugin\Api44\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * 権限移譲確認画面 (`admin/OAuth/authorization.twig`) は要求された scope を
 * `api.admin.oauth.scope.<scope>.description` で表示する。 翻訳が無い scope は
 * 翻訳キーがそのまま画面に出るため、 `scopes.available` との同期をテストで担保する。
 */
final class ScopeTranslationTest extends TestCase
{
    #[DataProvider('localeProvider')]
    public function testAllAvailableScopesHaveDescription(string $locale): void
    {
        $messages = Yaml::parseFile(__DIR__.'/../../Resource/locale/messages.'.$locale.'.yaml');
        $oauth = $messages['api']['admin']['oauth'];

        foreach (self::availableScopes() as $scope) {
            $key = 'scope.'.$scope.'.description';
            self::assertArrayHasKey(
                $key,
                $oauth,
                sprintf('scope `%s` の説明が messages.%s.yaml に定義されていない', $scope, $locale)
            );
            self::assertNotSame('', trim((string) $oauth[$key]));
        }
    }

    /**
     * @return string[][]
     */
    public static function localeProvider(): array
    {
        return [['ja'], ['en']];
    }

    /**
     * @return list<string>
     */
    private static function availableScopes(): array
    {
        $config = Yaml::parseFile(__DIR__.'/../../Resource/config/services.yaml');

        return $config['league_oauth2_server']['scopes']['available'];
    }
}
