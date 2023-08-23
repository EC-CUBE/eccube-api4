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

namespace Plugin\Api42\GraphQL\Error;

/**
 * ja: エラーカテゴリを表す列挙型
 * en: Enumerated type representing error category
 */
enum Category: string
{
    case Global = 'Global';
    case FormValidation = 'FormValidation';
}
