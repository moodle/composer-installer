namespace Moodle\Composer\Downloader;

use Composer\Downloader\DownloaderInterface;
use Composer\Exception\IrrecoverableDownloadException;
use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Composer\Pcre\Preg;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;

class {className} extends \Composer\Downloader\DownloadManager
{
    public function __construct(
        /** @var \Composer\Composer */
        protected \Composer\Composer $composer,
        protected \Composer\Downloader\DownloadManager $originalDownloader,
    ) {}

    #[\Override]
    public function install(PackageInterface $package, string $targetDir): PromiseInterface
    {
        $downloader = $this->getDownloaderForPackage($package);

        if ($downloader) {
            $promise = $downloader->install($package, $targetDir);
            $this->composer->getLoop()->wait([$promise]);

            return $promise;
        }

        return \React\Promise\resolve(null);
    }

    {methods}
}
