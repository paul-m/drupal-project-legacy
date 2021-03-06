<?php

namespace DrupalLegacyProject;

use DrupalLegacyProject\FileStorage;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\ProcessExecutor;
use Composer\IO\IOInterface;

/**
 * Provides static functions for composer script events.
 *
 * This is a copy of Drupal\Core\Composer\Composer.
 *
 * @see \Drupal\Core\Composer\Composer
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class Composer {

  protected static $packageToCleanup = [
    'behat/mink' => ['tests', 'driver-testsuite'],
    'behat/mink-browserkit-driver' => ['tests'],
    'behat/mink-goutte-driver' => ['tests'],
    'drupal/coder' => ['coder_sniffer/Drupal/Test', 'coder_sniffer/DrupalPractice/Test'],
    'doctrine/cache' => ['tests'],
    'doctrine/collections' => ['tests'],
    'doctrine/common' => ['tests'],
    'doctrine/inflector' => ['tests'],
    'doctrine/instantiator' => ['tests'],
    'egulias/email-validator' => ['documentation', 'tests'],
    'fabpot/goutte' => ['Goutte/Tests'],
    'guzzlehttp/promises' => ['tests'],
    'guzzlehttp/psr7' => ['tests'],
    'jcalderonzumba/gastonjs' => ['docs', 'examples', 'tests'],
    'jcalderonzumba/mink-phantomjs-driver' => ['tests'],
    'masterminds/html5' => ['test'],
    'mikey179/vfsStream' => ['src/test'],
    'paragonie/random_compat' => ['tests'],
    'phpdocumentor/reflection-docblock' => ['tests'],
    'phpunit/php-code-coverage' => ['tests'],
    'phpunit/php-timer' => ['tests'],
    'phpunit/php-token-stream' => ['tests'],
    'phpunit/phpunit' => ['tests'],
    'phpunit/php-mock-objects' => ['tests'],
    'sebastian/comparator' => ['tests'],
    'sebastian/diff' => ['tests'],
    'sebastian/environment' => ['tests'],
    'sebastian/exporter' => ['tests'],
    'sebastian/global-state' => ['tests'],
    'sebastian/recursion-context' => ['tests'],
    'stack/builder' => ['tests'],
    'symfony/browser-kit' => ['Tests'],
    'symfony/class-loader' => ['Tests'],
    'symfony/console' => ['Tests'],
    'symfony/css-selector' => ['Tests'],
    'symfony/debug' => ['Tests'],
    'symfony/dependency-injection' => ['Tests'],
    'symfony/dom-crawler' => ['Tests'],
    // @see \Drupal\Tests\Component\EventDispatcher\ContainerAwareEventDispatcherTest
    // 'symfony/event-dispatcher' => ['Tests'],
    'symfony/http-foundation' => ['Tests'],
    'symfony/http-kernel' => ['Tests'],
    'symfony/process' => ['Tests'],
    'symfony/psr-http-message-bridge' => ['Tests'],
    'symfony/routing' => ['Tests'],
    'symfony/serializer' => ['Tests'],
    'symfony/translation' => ['Tests'],
    'symfony/validator' => ['Tests', 'Resources'],
    'symfony/yaml' => ['Tests'],
    'symfony-cmf/routing' => ['Test', 'Tests'],
    'twig/twig' => ['doc', 'ext', 'test'],
  ];

  /**
   * Add vendor classes to Composer's static classmap.
   */
  public static function preAutoloadDump(Event $event) {
    // Get the configured vendor directory.
    $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');

    // We need the root package so we can add our classmaps to its loader.
    $package = $event->getComposer()->getPackage();
    // We need the local repository so that we can query and see if it's likely
    // that our files are present there.
    $repository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
    // This is, essentially, a null constraint. We only care whether the package
    // is present in the vendor directory yet, but findPackage() requires it.
    $constraint = new Constraint('>', '');
    // It's possible that there is no classmap specified in a custom project
    // composer.json file. We need one so we can optimize lookup for some of our
    // dependencies.
    $autoload = $package->getAutoload();
    if (!isset($autoload['classmap'])) {
      $autoload['classmap'] = [];
    }
    // Check for our packages, and then optimize them if they're present.
    if ($repository->findPackage('symfony/http-foundation', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-foundation/Request.php',
        $vendor_dir . '/symfony/http-foundation/ParameterBag.php',
        $vendor_dir . '/symfony/http-foundation/FileBag.php',
        $vendor_dir . '/symfony/http-foundation/ServerBag.php',
        $vendor_dir . '/symfony/http-foundation/HeaderBag.php',
      ]);
    }
    if ($repository->findPackage('symfony/http-kernel', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-kernel/HttpKernel.php',
        $vendor_dir . '/symfony/http-kernel/HttpKernelInterface.php',
        $vendor_dir . '/symfony/http-kernel/TerminableInterface.php',
      ]);
    }
    $package->setAutoload($autoload);
  }

  /**
   * Ensures that .htaccess and web.config files are present in Composer root.
   *
   * @param \Composer\Script\Event $event
   */
  public static function ensureHtaccess(Event $event) {

    // The current working directory for composer scripts is where you run
    // composer from.
    $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');

    // Prevent access to vendor directory on Apache servers.
    $htaccess_file = $vendor_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
      file_put_contents($htaccess_file, FileStorage::htaccessLines(TRUE) . "\n");
    }

    // Prevent access to vendor directory on IIS servers.
    $webconfig_file = $vendor_dir . '/web.config';
    if (!file_exists($webconfig_file)) {
      $lines = <<<EOT
<configuration>
  <system.webServer>
    <authorization>
      <deny users="*">
    </authorization>
  </system.webServer>
</configuration>
EOT;
      file_put_contents($webconfig_file, $lines . "\n");
    }
  }

  /**
   * Fires the drupal-phpunit-upgrade script event if necessary.
   *
   * @param \Composer\Script\Event $event
   */
  public static function upgradePHPUnit(Event $event) {
    $repository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
    // This is, essentially, a null constraint. We only care whether the package
    // is present in the vendor directory yet, but findPackage() requires it.
    $constraint = new Constraint('>', '');
    $phpunit_package = $repository->findPackage('phpunit/phpunit', $constraint);
    if (!$phpunit_package) {
      // There is nothing to do. The user is probably installing using the
      // --no-dev flag.
      return;
    }

    // If the PHP version is 7.0 or above and PHPUnit is less than version 6
    // call the drupal-phpunit-upgrade script to upgrade PHPUnit.
    if (!static::upgradePHPUnitCheck($phpunit_package->getVersion())) {
      $event->getComposer()
        ->getEventDispatcher()
        ->dispatchScript('drupal-phpunit-upgrade');
    }
  }

  /**
   * Determines if PHPUnit needs to be upgraded.
   *
   * This method is located in this file because it is possible that it is
   * called before the autoloader is available.
   *
   * @param string $phpunit_version
   *   The PHPUnit version string.
   *
   * @return bool
   *   TRUE if the PHPUnit needs to be upgraded, FALSE if not.
   */
  public static function upgradePHPUnitCheck($phpunit_version) {
    return !(version_compare(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, '7.0') >= 0 && version_compare($phpunit_version, '6.1') < 0);
  }

  /**
   * Remove possibly problematic test files from a single vendor package.
   *
   * @param \Composer\Script\Event $event
   *   A Composer Event object to get the configured composer vendor directories
   *   from.
   */
  public static function vendorTestCodeCleanup(PackageEvent $event) {
    $operation = $event->getOperation();
    // Get target package if we're updating, package otherwise.
    if ($operation->getJobType() == 'update') {
      $package = $operation->getTargetPackage();
    }
    else {
      $package = $operation->getPackage();
    }
    if ($package_key = static::findPackageKey($package->getName())) {
      return static::doTestCodeCleanup(
        $event->getComposer()->getConfig()->get('vendor-dir'),
        $package_key,
        static::$packageToCleanup[$package_key],
        $event->getIO()
      );
    }
  }

  /**
   * Remove possibly problematic test files from vendor packages.
   *
   * This method cleans all the available packages at the same time.
   *
   * @param \Composer\Script\Event $event
   *   The Composer Event object.
   */
  public static function vendorTestCodeCleanupCommand(Event $event) {
    $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');
    foreach (static::$packageToCleanup as $package_path => $cleanup_paths) {
      static::doTestCodeCleanup($vendor_dir, $package_path, $cleanup_paths, $event->getIO());
    }
  }

  /**
   * Remove possibly problematic test files from a single vendor package.
   *
   * @param string $vendor_dir
   *   Full path to the vendor directory.
   * @param string $package_path
   *   The package path within the vendor directory. Example: psr/log
   * @param string[] $cleanup_paths
   *   An array of relative paths within the vendor path which should be
   *   removed.
   * @param \Composer\IO\IOInterface $io
   *   IO object provided by Composer.
   */
  protected static function doTestCodeCleanup($vendor_dir, $package_path, $cleanup_paths, IOInterface $io) {
    $package_dir = $vendor_dir . '/' . $package_path;
    if (is_dir($package_dir)) {
      $io->write(sprintf("    Test code cleanup for <comment>%s</comment>", $package_path), TRUE, $io::VERY_VERBOSE);
      foreach ($cleanup_paths as $cleanup_path) {
        $cleanup_dir = $package_dir . '/' . $cleanup_path;
        if (is_dir($cleanup_dir)) {
          // Try to clean up.
          if (static::deleteRecursive($cleanup_dir)) {
            $io->write(sprintf("      <info>Removing directory '%s'</info>", $cleanup_path), TRUE, $io::VERY_VERBOSE);
          }
          else {
            // Always display a message if this fails as it means something
            // has gone wrong. Therefore the message has to include the
            // package name as the first informational message might not
            // exist.
            $io->write(sprintf("      <error>Failure removing directory '%s'</error> in package <comment>%s</comment>.", $cleanup_path, $package_path), TRUE, IOInterface::NORMAL);
          }
        }
        else {
          // If the package has changed or the --prefer-dist version does not
          // include the directory this is not an error.
          $io->write(sprintf("      Directory '%s' does not exist", $cleanup_dir), TRUE, $io::VERY_VERBOSE);
        }
      }
    }
  }

  /**
   * Find the array key for a given package name with a case-insensitive search.
   *
   * @param string $package_name
   *   The package name from composer. This is always already lower case.
   *
   * @return string|null
   *   The string key, or NULL if none was found.
   */
  protected static function findPackageKey($package_name) {
    $package_key = NULL;
    // In most cases the package name is already used as the array key.
    if (isset(static::$packageToCleanup[$package_name])) {
      $package_key = $package_name;
    }
    else {
      // Handle any mismatch in case between the package name and array key.
      // For example, the array key 'mikey179/vfsStream' needs to be found
      // when composer returns a package name of 'mikey179/vfsstream'.
      foreach (static::$packageToCleanup as $key => $dirs) {
        if (strtolower($key) === $package_name) {
          $package_key = $key;
          break;
        }
      }
    }
    return $package_key;
  }

  /**
   * Removes Composer's timeout so that scripts can run indefinitely.
   */
  public static function removeTimeout() {
    ProcessExecutor::setTimeout(0);
  }

  /**
   * Helper method to remove directories and the files they contain.
   *
   * @param string $path
   *   The directory or file to remove. It must exist.
   *
   * @return bool
   *   TRUE on success or FALSE on failure.
   */
  protected static function deleteRecursive($path) {
    if (is_file($path) || is_link($path)) {
      return unlink($path);
    }
    $success = TRUE;
    $dir = dir($path);
    while (($entry = $dir->read()) !== FALSE) {
      if ($entry == '.' || $entry == '..') {
        continue;
      }
      $entry_path = $path . '/' . $entry;
      $success = static::deleteRecursive($entry_path) && $success;
    }
    $dir->close();

    return rmdir($path) && $success;
  }

}
