<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Development\Business\DependencyTree;

use ArrayObject;
use Generated\Shared\Transfer\BundleDependenciesTransfer;
use Generated\Shared\Transfer\ComposerDependenciesTransfer;
use Generated\Shared\Transfer\ComposerDependencyCollectionTransfer;
use Generated\Shared\Transfer\ComposerDependencyTransfer;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\VarDumper\VarDumper;
use Zend\Filter\Word\SeparatorToCamelCase;

class ComposerDependencyParser
{

    const TYPE_INCLUDE = 'include';
    const TYPE_EXCLUDE = 'exclude';
    const TYPE_INCLUDE_DEV = 'include-dev';
    const TYPE_EXCLUDE_DEV = 'exclude-dev';

    /**
     * @var \Spryker\Zed\Development\Business\Composer\ComposerJsonFinder
     */
    protected $finder;

    /**
     * @param \Spryker\Zed\Development\Business\Composer\ComposerJsonFinder $finder
     */
    public function __construct($finder)
    {
        $this->finder = $finder;
    }

    /**
     * @param \Generated\Shared\Transfer\BundleDependenciesTransfer $bundleDependenciesTransfer
     *
     * @return array
     */
    public function getComposerDependencyComparison(BundleDependenciesTransfer $bundleDependenciesTransfer)
    {
//        $bundleDependenciesTransfer = $this->getOverwrittenDependenciesForBundle($bundleDependenciesTransfer);
        $bundleDependenciesTransfer = $this->filterCodeDependencies($bundleDependenciesTransfer);

        $composerDependencyCollectionTransfer = $this->getParsedComposerDependenciesForBundle($bundleDependenciesTransfer->getBundle());

        $bundleNames = $this->getBundleDependencyNames($bundleDependenciesTransfer);
        $requireNames = $this->getRequireNames($composerDependencyCollectionTransfer);
        $requireDevNames = $this->getRequireNames($composerDependencyCollectionTransfer, true);

        $allBundleNames = array_unique(array_merge($bundleNames, $requireNames, $requireDevNames));
        sort($allBundleNames);

        $dependencies = [];

        foreach ($allBundleNames as $bundleName) {
            if ($bundleDependenciesTransfer->getBundle() === $bundleName) {
                continue;
            }
            $dependencies[] = [
                'code' => in_array($bundleName, $bundleNames) ? $bundleName : '',
                'composerRequire' => in_array($bundleName, $requireNames) ? $bundleName : '',
                'composerRequireDev' => in_array($bundleName, $requireDevNames) ? $bundleName : '',
            ];
        }

        return $dependencies;
    }

    /**
     * @param \Generated\Shared\Transfer\BundleDependenciesTransfer $bundleDependenciesTransfer
     *
     * @return array
     */
    protected function getBundleDependencyNames(BundleDependenciesTransfer $bundleDependenciesTransfer)
    {
        $bundleNames = [];
        foreach ($bundleDependenciesTransfer->getDependencyBundles() as $dependencyBundleTransfer) {
            $bundleNames[] = $dependencyBundleTransfer->getBundle();
        }

        return $bundleNames;
    }

    /**
     * @param \Generated\Shared\Transfer\ComposerDependencyCollectionTransfer $composerDependencyCollectionTransfer
     * @param bool $isDev
     *
     * @return array
     */
    protected function getRequireNames(ComposerDependencyCollectionTransfer $composerDependencyCollectionTransfer, $isDev = false)
    {
        $composerBundleNames = [];
        foreach ($composerDependencyCollectionTransfer->getComposerDependencies() as $composerDependency) {
            if ($composerDependency->getName() && $composerDependency->getIsDev() === $isDev) {
                $composerBundleNames[] = $composerDependency->getName();
            }
        }

        return $composerBundleNames;
    }

    /**
     * @param \Generated\Shared\Transfer\BundleDependenciesTransfer $bundleDependenciesTransfer
     *
     * @return \Generated\Shared\Transfer\BundleDependenciesTransfer
     */
    protected function getOverwrittenDependenciesForBundle(BundleDependenciesTransfer $bundleDependenciesTransfer)
    {
        $declaredDependencies = $this->parseDeclaredDependenciesForBundle($bundleDependenciesTransfer->getBundle());

        if (!$declaredDependencies) {
            return $bundleDependenciesTransfer;
        }
//echo '<pre>' . PHP_EOL . \Symfony\Component\VarDumper\VarDumper::dump($declaredDependencies) . PHP_EOL . 'Line: ' . __LINE__ . PHP_EOL . 'File: ' . __FILE__ . die();
//        // For now we can't separate in the dependency tool yet
//        $included = array_merge($declaredDependencies[static::TYPE_INCLUDE], $declaredDependencies[static::TYPE_INCLUDE_DEV]);
//        $excluded = array_merge($declaredDependencies[static::TYPE_EXCLUDE], $declaredDependencies[static::TYPE_EXCLUDE_DEV]);
//
//        foreach ($codeDependencies as $key => $bundleDependency) {
//            if (in_array($bundleDependency, $excluded)) {
//                unset($codeDependencies[$key]);
//            }
//        }
//
//        $codeDependencies = array_merge($codeDependencies, $included);
//
//        return $codeDependencies;
    }

    /**
     * @param string $bundleName
     *
     * @return array
     */
    protected function parseDeclaredDependenciesForBundle($bundleName)
    {
        $composerJsonFiles = $this->finder->find();
        foreach ($composerJsonFiles as $composerJsonFile) {
            if ($this->shouldSkip($composerJsonFile, $bundleName)) {
                continue;
            }

            $path = dirname((string)$composerJsonFile);
            $dependencyFile = $path . DIRECTORY_SEPARATOR . 'dependency.json';
            if (!file_exists($dependencyFile)) {
                return [];
            }

            $content = file_get_contents($dependencyFile);
            $content = json_decode($content, true);

            return [
                static::TYPE_INCLUDE => isset($content[static::TYPE_INCLUDE]) ? array_keys($content[static::TYPE_INCLUDE]) : [],
                static::TYPE_EXCLUDE => isset($content[static::TYPE_EXCLUDE]) ? array_keys($content[static::TYPE_EXCLUDE]) : [],
                static::TYPE_INCLUDE_DEV => isset($content[static::TYPE_INCLUDE_DEV]) ? array_keys($content[static::TYPE_INCLUDE_DEV]) : [],
                static::TYPE_EXCLUDE_DEV => isset($content[static::TYPE_EXCLUDE_DEV]) ? array_keys($content[static::TYPE_EXCLUDE_DEV]) : [],
            ];
        }

        return [];
    }

    /**
     * @param string $bundleName
     *
     * @return \Generated\Shared\Transfer\ComposerDependencyCollectionTransfer
     */
    protected function getParsedComposerDependenciesForBundle($bundleName)
    {
        $composerJsonFiles = $this->finder->find();

        $composerDependencies = new ComposerDependencyCollectionTransfer();

        foreach ($composerJsonFiles as $composerJsonFile) {
            if ($this->shouldSkip($composerJsonFile, $bundleName)) {
                continue;
            }

            $content = file_get_contents($composerJsonFile);
            $content = json_decode($content, true);
            $require = isset($content['require']) ? $content['require'] : [];
            $requireDev = isset($content['require-dev']) ? $content['require-dev'] : [];

            $this->addComposerDependencies($require, $composerDependencies);
            $this->addComposerDependencies($requireDev, $composerDependencies, true);
        }

        return $composerDependencies;
    }

    /**
     * @param array $require
     * @param \Generated\Shared\Transfer\ComposerDependencyCollectionTransfer $composerDependencyCollectionTransfer
     * @param bool $isDev
     *
     * @return void
     */
    protected function addComposerDependencies(array $require, ComposerDependencyCollectionTransfer $composerDependencyCollectionTransfer, $isDev = false)
    {
        foreach ($require as $package => $version) {
            if (strpos($package, 'spryker/') !== 0) {
                continue;
            }
            $bundle = $this->getBundleName($package);

            $composerDependencyTransfer = new ComposerDependencyTransfer();
            $composerDependencyTransfer
                ->setName($bundle)
                ->setIsDev($isDev);

            $composerDependencyCollectionTransfer->addComposerDependency($composerDependencyTransfer);
        }
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo $composerJsonFile
     * @param string $bundleName
     *
     * @return bool
     */
    protected function shouldSkip(SplFileInfo $composerJsonFile, $bundleName)
    {
        $folder = $composerJsonFile->getRelativePath();

        return ($folder !== $bundleName);
    }

    /**
     * @TODO find better way to handle this:
     *
     * Propel bundle is separated into two bundles.
     *
     * "spryker/propel-orm" for the dependency to the external "propel/propel"
     * "spryker/propel" for our own code like Builders etc.
     *
     * "spryker/propel-orm" is a dependency of "spryker/propel" but
     * is displayed in the list of Composer dependencies. To prevent this wrong
     * dependency "alert" PropelOrm gets filtered out when both bundles are present.
     *
     * @param \Generated\Shared\Transfer\BundleDependenciesTransfer $bundleDependenciesTransfer
     *
     * @return \Generated\Shared\Transfer\BundleDependenciesTransfer
     */
    private function filterCodeDependencies(BundleDependenciesTransfer $bundleDependenciesTransfer)
    {
        if ($this->hasDependencyTo('Propel', $bundleDependenciesTransfer) && $this->hasDependencyTo('PropelOrm', $bundleDependenciesTransfer)) {
            $dependencyBundles = $bundleDependenciesTransfer->getDependencyBundles();
            $bundleDependenciesTransfer->setDependencyBundles(new ArrayObject());
            foreach ($dependencyBundles as $dependencyBundle) {
                if ($dependencyBundle->getBundle() !== 'PropelOrm') {
                    $bundleDependenciesTransfer->addDependencyBundle($dependencyBundle);
                }
            }
        }

        return $bundleDependenciesTransfer;
    }

    /**
     * @param $bundle
     * @param \Generated\Shared\Transfer\BundleDependenciesTransfer $bundleDependenciesTransfer
     *
     * @return bool
     */
    private function hasDependencyTo($bundle, BundleDependenciesTransfer $bundleDependenciesTransfer)
    {
        foreach ($bundleDependenciesTransfer->getDependencyBundles() as $dependencyBundle) {
            if ($dependencyBundle->getBundle() === $bundle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $package
     *
     * @return string
     */
    protected function getBundleName($package)
    {
        $name = substr($package, 8);
        $filter = new SeparatorToCamelCase('-');
        $name = ucfirst($filter->filter($name));
        return $name;
    }

}
