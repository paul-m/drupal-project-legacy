<?php

namespace DrupalLegacyProject;

use Composer\Script\Event;

class Composer {

  public static function projectFinished(Event $e) {
    error_log(get_class($e));
    $root_package = $e->getComposer()->getPackage();
    $path = $e->getComposer()->getInstallationManager()->getInstallPath($root_package);
    error_log($path);
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
