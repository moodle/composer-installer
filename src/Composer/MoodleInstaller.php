<?php

namespace Moodle\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class MoodleInstaller extends LibraryInstaller
{
    /**
     * A mapping of plugin types to installation locations.
     *
     * @var array<string, string>
     */
    protected array $locations = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(
        \Composer\IO\IOInterface $io,
        \Composer\Composer $composer,
        ?string $type = 'library',
        ?\Composer\Util\Filesystem $filesystem = null,
        ?\Composer\Installer\BinaryInstaller $binaryInstaller = null,
    ) {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
        $this->initLocations();
    }

    /**
     * @inheritDoc
     */
    public function getInstallPath(PackageInterface $package)
    {
        $type = $package->getType();

        if (str_starts_with($type, 'moodle-package-')) {
            // Not a plugin type, but a Moodle plugin - use the default installation path.
            return parent::getInstallPath($package);
        }

        $plugintype = substr($type, 7); // Remove 'moodle-' prefix.

        if (!isset($this->locations[$plugintype])) {
            throw new \InvalidArgumentException(
                "Unable to install package of type {$type}, unknown plugin type {$plugintype}"
            );
        }

        return $this->getLocation($package, $this->locations[$plugintype]);
    }

    /**
     * Get the list of supported plugin types.
     *
     * @return array<string>
     */
    public function getPluginTypes(): array
    {
        return $this->locations;
    }

    /**
     * @inheritDoc
     */
    public function supports(string $packageType): bool
    {
        if (str_starts_with($packageType, 'moodle-')) {
            if (str_starts_with($packageType, 'moodle-package-')) {
                // Not a plugin type, but a package for Moodle - use the default installation path.
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Initialize the locations mapping from data providers.
     */
    protected function initLocations(): void
    {
        if (!empty($this->locations)) {
            return;
        }

        $dataProviders = [
            new DataProviders\ManualData(),
            new DataProviders\LegacyData(),
            new DataProviders\ContribData(),
            new DataProviders\MoodleData(),
        ];

        foreach ($dataProviders as $provider) {
            $this->locations = array_merge($this->locations, $provider->getData());
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getLocation(PackageInterface $package, string $location): string
    {
        if (str_contains($location, '{') === false) {
            return $location;
        }

        // Determine the variables to replace.
        $vars = $this->getTemplateVars($package);

        foreach ($vars as $key => $value) {
            $location = str_replace('{$' . $key . '}', $value, $location);
        }

        return $location;
    }

    /**
     * Get the template variables for a package.
     *
     * @return array<string, string>
     */
    public function getTemplateVars(PackageInterface $package): array
    {
        return [
            'name'   => $this->getPackageName($package),
            'prefix' => $this->getRootPackagePath(),
            'public' => $this->getPublicPath(),
        ];
    }

    /**
     * Get the package name to use for installation.
     */
    public function getPackageName(PackageInterface $package): string
    {
        // Check for an explicit installer-name setting first.
        $extra = $package->getExtra();
        if (!empty($extra['installer-name']) && is_string($extra['installer-name'])) {
            return $extra['installer-name'];
        }

        $prettyName = $package->getPrettyName();
        if (str_contains($prettyName, '/')) {
            // Use the part after the slash.
            $parts = explode('/', $prettyName, 2);
            $prettyName = $parts[1];
        }

        // Guess the name from the package name if not explicitly set.
        $matches = [];
        preg_match('/^moodle-(?<type>([^_]*))_(?<name>(.*))$/', $prettyName, $matches);

        if ($matches) {
            return $matches['name'];
        }

        // Fall back to the vendor-less pretty name.
        return $prettyName;
    }

    /**
     * Get the install path for the root package.
     *
     * @return string
     */
    protected function getRootPackagePath(): string
    {
        // To allow for migration from the legacy way of doing things, we
        // check for an 'install-path' setting in the root package extra.
        // This allows the root package to put the Moodle installation in
        // a custom location.
        // If there is no such setting, we assume the root package is a
        // legacy package.
        $rootPackage = $this->composer->getPackage();

        $extra = $rootPackage->getExtra();
        $defaultValue = 'moodle/';

        if (empty($extra)) {
            return $defaultValue;
        }

        if (!array_key_exists('install-path', $extra)) {
            return $defaultValue;
        }

        if (!is_string($extra['install-path'])) {
            return $defaultValue;
        }

        return ltrim(rtrim($extra['install-path'], '/') . '/', '/');
    }

    /**
     * Determine if Moodle uses a public directory.
     *
     * @return string
     */
    protected function getPublicPath(): string
    {
        // If the root package has the setting, use that.
        $extra = $this->composer->getPackage()->getExtra();
        if (array_key_exists('haspublicdir', $extra)) {
            return $extra['haspublicdir'] ? 'public/' : '';
        }

        // The public directory setting is stored in the main Moodle package's.
        // Legacy Moodle installs do not have this path, or any setting.
        // At the moment there is no way to fetch the list of packages by virtual package, so we must do it this way.

        foreach (\Composer\InstalledVersions::getInstalledPackagesByType('moodle-core') as $packageName) {
            $extra = $this->getExtraForInstalledPackage($packageName);
            if (array_key_exists('haspublicdir', $extra)) {
                return $extra['haspublicdir'] ? 'public/' : '';
            }
        }

        return '';
    }

    /**
     * Fetch the extra data for an installed package.
     *
     * @param string $packageName
     * @return array<string, mixed>
     */
    protected function getExtraForInstalledPackage(string $packageName): array
    {
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            return [];
        }

        $installPath = \Composer\InstalledVersions::getInstallPath($packageName);
        $composerFile = $installPath . '/composer.json';
        if (!file_exists($composerFile)) {
            return [];
        }

        $filecontent = file_get_contents($composerFile);
        if ($filecontent === false) {
            return [];
        }

        $composerData = json_decode($filecontent, true);

        if (!is_array($composerData)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $composerData['extra'] ?? [];
    }
}
