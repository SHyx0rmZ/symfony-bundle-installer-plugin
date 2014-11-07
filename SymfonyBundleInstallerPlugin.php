<?php

namespace SHy\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class SymfonyBundleInstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        var_dump([$composer, $io]);
        $installer = new SymfonyBundleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
