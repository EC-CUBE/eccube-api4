<?php


namespace Plugin\Api\GraphQL\Error;


use GraphQL\Error\ClientAware;

class FormInvalidException extends \Exception implements ClientAware
{

    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return 'Invalid argument';
    }
}
