<?php

namespace Warith\Fcm;

use Illuminate\Support\Facades\Facade;

/**
 * Class FcmFacade
 * @package Warith\Fcm\Facades
 */
class FcmFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'fcm';
    }
}
