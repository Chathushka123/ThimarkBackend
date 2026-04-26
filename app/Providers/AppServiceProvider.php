<?php

namespace App\Providers;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Illuminate\Support\ServiceProvider;
use Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        Storage::extend('google', function ($app, $config) {
            $client = new GoogleClient();
            $client->setAuthConfig($config['service_account_file']);
            $client->setScopes([GoogleDrive::DRIVE]);

            $service = new GoogleDrive($client);

            // 'sharedFolderId' tells the adapter to use a folder owned by another Google account
            // (your personal Drive) that has been shared with the service account email.
            $options = [
                'sharedFolderId' => $config['shared_folder_id'],
            ];

            $adapter = new GoogleDriveAdapter($service, null, $options);
            // Required when the service account writes into a folder owned by another account
            $adapter->enableTeamDriveSupport();
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
