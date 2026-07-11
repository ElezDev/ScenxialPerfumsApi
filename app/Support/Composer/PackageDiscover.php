<?php

namespace App\Support\Composer;

use Composer\Script\Event;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

class PackageDiscover
{
    public static function discover(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        if (! is_file($vendorDir.'/autoload.php')) {
            return;
        }

        require_once $vendorDir.'/autoload.php';

        $basePath = getcwd() ?: dirname(__DIR__, 3);

        (new PackageManifest(
            new Filesystem,
            $basePath,
            $basePath.'/bootstrap/cache/packages.php'
        ))->build();
    }
}
