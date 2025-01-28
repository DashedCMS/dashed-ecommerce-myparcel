<?php

namespace Dashed\DashedEcommerceMyParcel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedEcommerceMyParcel\DashedEcommerceMyParcel
 */
class DashedEcommerceMyParcel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-ecommerce-myparcel';
    }
}
