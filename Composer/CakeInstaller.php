<?php
namespace aclavijo\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class CakeInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        return 'app/plugins/toto/openid'.substr($package->getPrettyName(), 23);
        fsdfsdfsd;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'cakephp-plugin' === $packageType;
    }
}