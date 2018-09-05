<?php
namespace Fondy\Fondy\Api;
/**
 * Interface ApiInterface
 * @package Fondy\Fondy\Api
 */
interface ApiInterface
{
    /**
     * @param string $cartId
     * @param string $method The payment method code
     * @return string
     */
    public function getToken($cartId, $method);
}