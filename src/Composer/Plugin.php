<?php

namespace Moodle\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Moodle\Composer\Downloader\DownloadManagerGenerator;
use Moodle\Composer\Downloader\MoodleDownloadManager;

class Plugin implements PluginInterface
{
    /**
     * @var MoodleInstaller
     */
    private MoodleInstaller $_installer;

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $downloadManagerGenerator = new DownloadManagerGenerator($composer, $composer->getDownloadManager());

        $composer->setDownloadManager(
            $downloadManagerGenerator->generateDownloadManager(),
        );
        $this->_installer = new MoodleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->_installer);
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->removeInstaller($this->_installer);
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // No action needed on uninstall.
    }
}
