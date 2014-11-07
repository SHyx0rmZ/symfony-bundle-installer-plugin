<?php

namespace SHy\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

class SymfonyBundleInstaller extends LibraryInstaller
{
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);

        var_dump($type);
    }

    public function installBinaries(PackageInterface $package)
    {
        parent::installBinaries($package);

        var_dump('install', $package);
    }

    public function removeBinaries(PackageInterface $package)
    {
        var_dump('remove', $package);

        parent::removeBinaries($package);
    }

    public function supports($packageType)
    {
        return 'symfony-bundle' === $packageType;
    }
}
