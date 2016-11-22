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
        return 'app/plugins/openid';
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        var_dump($packageType);
        die;
        return 'cakephp-plugin' === $packageType;
    }
}