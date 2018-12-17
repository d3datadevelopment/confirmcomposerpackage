<?php

/**
 * This Software is the property of Data Development and is protected
 * by copyright law - it is NOT Freeware.
 * Any unauthorized use of this software without a valid license
 * is a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 * http://www.shopmodule.com
 *
 * @copyright (C) D3 Data Development (Inh. Thomas Dartsch)
 * @author    D3 Data Development - Daniel Seifert <support@shopmodule.com>
 * @link      http://www.oxidmodule.com
 */

namespace D3\confirmComposerPackage;

use Composer\Command\RemoveCommand;
use Composer\Composer;
use Composer\Config\JsonConfigSource;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use OxidEsales\ComposerPlugin\Installer\Package\ShopPackageInstaller;
use Symfony\Component\Console\Application as SymphonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Webmozart\PathUtil\Path;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer */
    protected $composer;

    /** @var IOInterface */
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Register events.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => [['onPostPackageInstall', 1]],
        ];
    }

    /**
     * @param PackageEvent $event
     * @throws \Exception
     */
    public function onPostPackageInstall(PackageEvent $event)
    {
        /** @var InstallOperation $operation */
        $operation = $event->getOperation();

        if ($operation->getJobType() == 'install') {
            $aExtra = $operation->getPackage()->getExtra();

            if (is_array($aExtra) && isset($aExtra['packageConfirmation']) && isset($aExtra['packageConfirmation']['question'])) {
                if (false == in_array(
                    trim(strtolower($this->io->ask($aExtra['packageConfirmation']['question']))),
                    array_map('strtolower', $aExtra['packageConfirmation']['acceptedanswers'])
                )) {
                    $this->io->write("You didn't accept the necessary license. The package will be uninstalled. This may take a moment. Please wait.");

                    $app = new SymphonyApplication;
                    $definition = $app->getDefinition();

                    $input = new ArgvInput();
                    $output = Factory::createOutput();

                    $removeCommand = new RemoveCommand();
                    $removeCommand->getDefinition()->setOptions(
                        (array_merge($removeCommand->getDefinition()->getOptions(), $definition->getOptions()))
                    );
                    $input->bind($removeCommand->getDefinition());

                    $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'remove', $input, $output);
                    $event->getComposer()->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

                    $install = Installer::create($this->io, $event->getComposer());

                    $updateDevMode = !$input->getOption('update-no-dev');
                    $optimize = $input->getOption('optimize-autoloader') || $event->getComposer()->getConfig()->get('optimize-autoloader');
                    $authoritative = $input->getOption('classmap-authoritative') || $event->getComposer()->getConfig()->get('classmap-authoritative');
                    $apcu = $input->getOption('apcu-autoloader') || $event->getComposer()->getConfig()->get('apcu-autoloader');

                    $package = $operation->getPackage()->getName();
                    $packages = array(strtolower($package));
                    $install
                        ->setVerbose($input->getOption('verbose'))
                        ->setDevMode($updateDevMode)
                        ->setOptimizeAutoloader($optimize)
                        ->setClassMapAuthoritative($authoritative)
                        ->setApcuAutoloader($apcu)
                        ->setUpdate(false)
                        ->setUpdateWhitelist($packages)
                        ->setWhitelistTransitiveDependencies(!$input->getOption('no-update-with-dependencies'))
                        ->setIgnorePlatformRequirements($input->getOption('ignore-platform-reqs'))
                        ->setRunScripts(false);

                    $install->run();

                    $file = Factory::getComposerFile();
                    $jsonfile = new JsonFile($file);
                    $json = new JsonConfigSource($jsonfile);
                    $jsonarray = $jsonfile->read();
                    $jsoncontent = file_get_contents($file);
                    $type = $input->getOption('dev') ? 'require-dev' : 'require';
                    $altType = !$input->getOption('dev') ? 'require-dev' : 'require';

                    if (isset($jsonarray[$type][$package])) {
                        // $json->removeLink($type, $package);
                        $manipulator = new JsonManipulator($jsoncontent);
                        if ($manipulator->removeSubNode($type, $package)) {
                            file_put_contents($file, $manipulator->getContents());
                        }
                    } elseif (isset($jsonarray[$altType][$package])) {
                        $this->io->writeError('<warning>'.$package.' could not be found in '.$type.' but it is present in '.$altType.'</warning>');
                        if ($this->io->isInteractive()) {
                            if ($this->io->askConfirmation('Do you want to remove it from '.$altType.' [<comment>yes</comment>]? ', true)) {
                                //$json->removeLink($altType, $package);
                                $manipulator = new JsonManipulator($jsoncontent);
                                if ($manipulator->removeSubNode($altType, $package)) {
                                    file_put_contents($file, $manipulator->getContents());
                                }
                            }
                        }
                    } else {
                        $this->io->writeError('<warning>'.$package.' is not required in your composer.json and has not been removed</warning>');
                    }
                }
            }
        }
    }

    /**
     * Get the path to shop's source directory.
     *
     * @return string
     */
    public function getShopSourcePath()
    {
        return Path::join(getcwd(), ShopPackageInstaller::SHOP_SOURCE_DIRECTORY);
    }
}