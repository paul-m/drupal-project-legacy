<?php

namespace DrupalLegacyProject;

use Composer\Script\Event;

class Composer {

  public static function projectFinished(Event $e) {
    $io = $e->getIO();
    $root_package = $e->getComposer()->getPackage();

    $path = $e->getComposer()->getInstallationManager()->getInstallPath($root_package);
    $vendor_dir = substr($path, 0, -1 - strlen($root_package->getName()));

    $locker = $e->getComposer()->getLocker();
    $packages = $locker->getLockData()['packages'];
    foreach ($packages as $package) {
      $name = $package['name'];
      if ($package_key = static::findPackageKey($name)) {
        $message = sprintf("    Processing <comment>%s</comment>", $name);
        if ($io->isVeryVerbose()) {
          $io->write($message);
        }
        if ($package_key) {
          foreach (static::$packageToCleanup[$package_key] as $path) {
            $dir_to_remove = $vendor_dir . '/' . $package_key . '/' . $path;
            $print_message = $io->isVeryVerbose();
            if (is_dir($dir_to_remove)) {
              if (static::deleteRecursive($dir_to_remove)) {
                $message = sprintf("      <info>Removing directory '%s'</info>", $path);
              }
              else {
                // Always display a message if this fails as it means something has
                // gone wrong. Therefore the message has to include the package name
                // as the first informational message might not exist.
                $print_message = TRUE;
                $message = sprintf("      <error>Failure removing directory '%s'</error> in package <comment>%s</comment>.", $path, $name);
              }
            }
            else {
              // If the package has changed or the --prefer-dist version does not
              // include the directory this is not an error.
              $message = sprintf("      Directory '%s' does not exist", $path);
            }
            if ($print_message) {
              $io->write($message);
            }
          }

          if ($io->isVeryVerbose()) {
            // Add a new line to separate this output from the next package.
            $io->write("");
          }
        }
      }
    }


//    \error_log($e->getName());
//    \error_log(print_r($e->getComposer()->getRepositoryManager()->getLocalRepository()->getPackages(),true));
    /*
    $packages = $e->getComposer()->getPackage()->getRepository()->getPackages();
    foreach ($packages as $package) {
      \error_log($package->getName());
    }
    */
  }

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
