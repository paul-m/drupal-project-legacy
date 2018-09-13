<?php

namespace DrupalLegacyProject;

use Composer\Script\Event;

class Composer {

  public static function projectFinished(Event $e) {
    error_log(get_class($e));
    $root_package = $e->getComposer()->getPackage();
    $vendor_path = substr(
      $e->getComposer()->getInstallationManager()->getInstallPath($root_package),
      0,
      0 - strlen($root_package->getName())
    );
    error_log($vendor_path);

    $locker = $e->getComposer()->getLocker();
    error_log(print_r($locker->getLockData(), true));

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
