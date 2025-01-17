<?php

namespace DoubleThreeDigital\DigitalProducts\Tests;

use DoubleThreeDigital\DigitalProducts\ServiceProvider;
use DoubleThreeDigital\SimpleCommerce\ServiceProvider as SimpleCommerceServiceProvider;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Extend\Manifest;
use Statamic\Facades\Blueprint;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Stache\Stores\UsersStore;
use Statamic\Statamic;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            StatamicServiceProvider::class,
            SimpleCommerceServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Statamic' => Statamic::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->make(Manifest::class)->manifest = [
            'doublethreedigital/sc-digital-products' => [
                'id' => 'doublethreedigital/sc-digital-products',
                'namespace' => 'DoubleThreeDigital\\DigitalProducts',
            ],
        ];

        $app->make(Manifest::class)->manifest = [
            'doublethreedigital/simple-commerce' => [
                'id' => 'doublethreedigital/simple-commerce',
                'namespace' => 'DoubleThreeDigital\\SimpleCommerce',
            ],
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $configs = [
            'assets', 'cp', 'forms', 'static_caching',
            'sites', 'stache', 'system', 'users',
        ];

        foreach ($configs as $config) {
            $app['config']->set("statamic.$config", require(__DIR__."/../vendor/statamic/cms/config/{$config}.php"));
        }

        $app['config']->set('app.key', 'base64:'.base64_encode(
            Encrypter::generateKey($app['config']['app.cipher'])
        ));
        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.stores.users', [
            'class' => UsersStore::class,
            'directory' => __DIR__.'/__fixtures/users',
        ]);
        $app['config']->set('statamic.api.enabled', true);
        $app['config']->set('simple-commerce', require(__DIR__.'/../vendor/doublethreedigital/simple-commerce/config/simple-commerce.php'));
        $app['config']->set('simple-commerce.tax_engine', \DoubleThreeDigital\SimpleCommerce\Tax\BasicTaxEngine::class);

        Statamic::booted(function () {
            Blueprint::setDirectory(__DIR__.'/../vendor/doublethreedigital/simple-commerce/resources/blueprints');

            $this->bootWhatStatamicMissed();
        });
    }

    /**
     * For some reason, Statamic isn't currently registering our event listeners
     * and routes. This method is a temporary fix.
     */
    protected function bootWhatStatamicMissed()
    {
        Event::listen(
            \Statamic\Events\EntryBlueprintFound::class,
            \DoubleThreeDigital\DigitalProducts\Listeners\AddFieldsToProductBlueprint::class
        );

        Event::listen(
            \DoubleThreeDigital\SimpleCommerce\Events\OrderStatusUpdated::class,
            \DoubleThreeDigital\DigitalProducts\Listeners\ProcessCheckout::class
        );

        Event::listen(
            \DoubleThreeDigital\DigitalProducts\Events\DigitalDownloadReady::class,
            \DoubleThreeDigital\SimpleCommerce\Listeners\SendConfiguredNotifications::class
        );

        Statamic::pushActionRoutes(function () {
            require __DIR__.'/../routes/actions.php';
        });
    }
}
