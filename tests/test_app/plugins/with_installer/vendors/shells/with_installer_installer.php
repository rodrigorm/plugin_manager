<?php
App::import('Vendors', 'PluginManager.PluginInstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'plugin_installer.php'));

class WithInstallerInstaller extends PluginInstallerTask {
/**
 * Array contendo todas as depend�ncias para este plugin, ex.:
 * array(
 * 		'example' => 'git://example.com/user/example.git' // Ser� instalado em app/plugins/example
 * )
 */
	var $deps = array();
}
?>