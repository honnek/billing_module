<?php

namespace Billing;

use App\Module\AbstractModule;
use App\Module\Feature\RoutesProviderInterface;

/**
 * Class Module
 *
 * @package Billing
 */
class Module extends AbstractModule implements RoutesProviderInterface
{

    const ALIAS = 'billing';

    /**
     * @return string
     */
    public static function getAlias()
    {
        return static::ALIAS;
    }

    /**
     * @inheritdoc
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * @return string
     */
    public function getRoutesPath()
    {
//        return __DIR__ . '/../routes/web.php';
    }
}
