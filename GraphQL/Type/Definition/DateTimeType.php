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

namespace Plugin\Api\GraphQL\Type\Definition;

use DateTime;
use DateTimeInterface;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use Plugin\Api\GraphQL\Error\InvalidArgumentException;

class DateTimeType extends ScalarType
{
    private static $DateTimeType;

    /**
     * @var string
     */
    public $name = 'DateTime';

    /**
     * @var string
     */
    public $description = 'The `DateTime` scalar type represents time data, represented as an ISO-8601 encoded UTC date string.';

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function serialize($value)
    {
        if (!$value instanceof DateTimeInterface) {
            throw new InvariantViolation('DateTime is not an instance of DateTimeInterface: '.Utils::printSafe($value));
        }

        return $value->format(DateTime::ATOM);
    }

    /**
     * @param mixed $value
     *
     * @return DateTime|false|null
     * @throws InvalidArgumentException
     */
    public function parseValue($value)
    {
        $dateTime = DateTime::createFromFormat(DateTime::ATOM, $value);
        if ($dateTime) {
            return $dateTime;
        } else {
            throw new InvalidArgumentException('DateTime parse error, please specify in "Y-m-d\TH:i:sP".'.Utils::printSafe($value));
        }
    }

    /**
     * @param Node $valueNode
     * @param array|null $variables
     *
     * @return string|null
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof StringValueNode) {
            return $this->parseValue($valueNode->value);
        }

        return null;
    }

    /**
     * @api
     */
    public static function dateTime(): ScalarType
    {
        if (static::$DateTimeType === null) {
            static::$DateTimeType = new DateTimeType();
        }

        return static::$DateTimeType;
    }
}
