<?php
App::import('Vendors', 'PluginManager.PluginInstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'plugin_installer.php'));

class WithInstallerInstaller extends PluginInstallerTask {}
?>