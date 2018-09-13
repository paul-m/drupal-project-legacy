<?php

namespace DrupalLegacyProject;

use Composer\Script\Event;
use Drupal\Core\Composer\Composer as DrupalComposer;

class Composer {

  public static function projectFinished(Event $e) {
    $locker = $e->getComposer()->getLocker();
    $packages = $locker->getLockData()['packages'];
    foreach ($packages as $package) {
      $name = $package['name'];
      if (DrupalComposer::findPackageKey($name)) {
        error_log('removing: ' . $name);
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


}
