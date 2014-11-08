<?php

namespace SHyx0rmZ\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class SymfonyBundleInstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new SymfonyBundleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
