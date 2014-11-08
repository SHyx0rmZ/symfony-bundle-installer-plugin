<?php

namespace SHyx0rmZ\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;

class SymfonyBundleInstaller extends LibraryInstaller
{
    const DIRECTION_ADD_BUNDLE = 'add';
    const DIRECTION_REMOVE_BUNDLE = 'remove';

    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);
    }

    public function installBinaries(PackageInterface $package)
    {
        parent::installBinaries($package);

        $this->updateBundleEntries($package, static::DIRECTION_ADD_BUNDLE);
    }

    public function removeBinaries(PackageInterface $package)
    {
        $this->updateBundleEntries($package, static::DIRECTION_REMOVE_BUNDLE);

        parent::removeBinaries($package);
    }

    public function supports($packageType)
    {
        return 'symfony-bundle' === $packageType;
    }

    /**
     * @return string
     */
    private function readAppKernel()
    {
        $appKernelContents = file_get_contents(__DIR__ . '/../../../app/AppKernel.php');
        return $appKernelContents;
    }

    /**
     * @param $appKernelContents
     * @param $bundlesOffset
     * @param $bundlesLength
     * @return array|string
     */
    private function extractBundlesSection($appKernelContents, $bundlesOffset, $bundlesLength)
    {
        $bundles = substr($appKernelContents, $bundlesOffset, $bundlesLength);
        $bundles = explode("\n", $bundles);
        return $bundles;
    }

    /**
     * @param $bundles
     * @return string
     */
    private function determineIndentation($bundles)
    {
        $matches = array();
        preg_match('/^(\s*)\\1/', $bundles[count($bundles) - 1], $matches);
        return $matches[1];
    }

    /**
     * @param $appKernelContents
     * @param $bundles
     * @param $bundlesSectionOffset
     * @param $bundlesSectionLength
     * @return mixed
     */
    private function replaceBundlesSection($appKernelContents, $bundles, $bundlesSectionOffset, $bundlesSectionLength)
    {
        $bundles = implode("\n", $bundles);

        $appKernelContents = substr_replace($appKernelContents, $bundles, $bundlesSectionOffset, $bundlesSectionLength);
        return $appKernelContents;
    }

    /**
     * @param $appKernelContents
     */
    private function writeAppKernel($appKernelContents)
    {
        file_put_contents(__DIR__ . '/../../../app/AppKernel.php', $appKernelContents);
    }

    /**
     * @param $bundle
     * @return string
     */
    private function readBundle($bundle)
    {
        $bundleContents = file_get_contents($bundle->getPathname());
        return $bundleContents;
    }

    /**
     * @param $bundleContents
     * @param $matches
     * @return string
     */
    private function getNamespace($bundleContents)
    {
        $matches = array();
        preg_match('/namespace (.+?);/', $bundleContents, $matches);
        return $matches[1];
    }

    /**
     * @param $bundleContents
     * @param $matches
     * @return string
     */
    private function getClass($bundleContents)
    {
        $matches = array();
        preg_match('/class (.+?)\s/', $bundleContents, $matches);
        return $matches[1];
    }

    /**
     * @param $namespace
     * @param $class
     * @return string
     */
    private function getBundleEntry($namespace, $class)
    {
        $bundleName = $this->getBundleName($namespace, $class);
        $bundleEntry = 'new ' . $bundleName . '(),';
        return $bundleEntry;
    }

    /**
     * @param $bundles
     * @param $indentation
     * @param $bundleEntry
     * @return array
     */
    private function addBundleToEntries($bundles, $indentation, $bundleEntry)
    {
        $bundles[] = $bundles[count($bundles) - 1];
        $bundles[count($bundles) - 2] = $indentation . $indentation . $indentation . $bundleEntry;
        return $bundles;
    }

    /**
     * @param PackageInterface $package
     */
    private function updateBundleEntries(PackageInterface $package, $direction)
    {
        $this->io->write('<info>Updating AppKernel.php</info>');

        $appKernelContents = $this->readAppKernel();
        $bundlesSectionOffset = strpos($appKernelContents, '$bundles = array(');
        $bundlesSectionLength = strpos($appKernelContents, ';', $bundlesSectionOffset) - $bundlesSectionOffset;
        $bundles = $this->extractBundlesSection($appKernelContents, $bundlesSectionOffset, $bundlesSectionLength);
        $indentation = $this->determineIndentation($bundles);

        $bundles = $this->addOrRemoveBundleEntries($package, $direction, $bundles, $indentation);

        $appKernelContents = $this->replaceBundlesSection($appKernelContents, $bundles, $bundlesSectionOffset, $bundlesSectionLength);

        if ($this->appKernelChanged($appKernelContents)) {
            $this->io->write('');
            $this->writeAppKernel($appKernelContents);
        }
    }

    /**
     * @param $namespace
     * @param $class
     * @return string
     */
    private function getBundleName($namespace, $class)
    {
        $bundleName = $namespace . '\\' . $class;
        return $bundleName;
    }

    /**
     * @param $appKernelContents
     * @return bool
     */
    private function appKernelChanged($appKernelContents)
    {
        return $appKernelContents != $this->readAppKernel();
    }

    /**
     * @param PackageInterface $package
     * @param $direction
     * @param $bundles
     * @param $indentation
     * @return array
     */
    private function addOrRemoveBundleEntries(PackageInterface $package, $direction, $bundles, $indentation)
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../../' . $package->getPrettyName())->name('*Bundle.php');

        foreach ($finder as $bundle) {
            $bundleContents = $this->readBundle($bundle);
            $namespace = $this->getNamespace($bundleContents);
            $class = $this->getClass($bundleContents);
            $bundleEntry = $this->getBundleEntry($namespace, $class);

            if ($direction == static::DIRECTION_ADD_BUNDLE) {
                $filteredBundleEntries = array_filter($bundles, function ($line) use ($bundleEntry) {
                    return preg_match('/(\s*)(' . escapeshellcmd($bundleEntry) . ')/', $line) == 1;
                });

                if (count($filteredBundleEntries) < 1) {
                    $this->io->write('  - Adding bundle <info>' . $this->getBundleName($namespace, $class) . '</info>');

                    $bundles = $this->addBundleToEntries($bundles, $indentation, $bundleEntry);
                }
            } elseif ($direction == static::DIRECTION_REMOVE_BUNDLE) {
                $filteredBundleEntries = array_filter($bundles, function ($line) use ($bundleEntry) {
                    return preg_match('/(\s*)(' . escapeshellcmd($bundleEntry) . ')/', $line) == 0;
                });

                if (count($filteredBundleEntries) != count($bundles)) {
                    $this->io->write('  - Removing bundle <info>' . $this->getBundleName($namespace, $class) . '</info>');

                    $bundles = $filteredBundleEntries;
                }
            }
        }
        return $bundles;
    }
}
