<?php

defined('MW_PATH') || exit('No direct script access allowed');

abstract class AutomationExtBlock
{
    final public static function getConstants()
    {
        $refl = new ReflectionClass(static::class);
        return $refl->getConstants();
    }

    final public static function getConstantsJson()
    {
        return json_encode(self::getConstants());
    }
}