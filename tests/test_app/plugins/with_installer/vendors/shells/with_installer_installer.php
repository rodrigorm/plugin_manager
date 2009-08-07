<?php
App::import('Vendors', 'PluginManager.PluginInstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'plugin_installer.php'));

class WithInstallerInstaller extends PluginInstallerTask {
/**
 * Array contendo todas as dependências para este plugin, ex.:
 * array(
 * 		'example' => 'git://example.com/user/example.git' // Será instalado em app/plugins/example
 * )
 */
	var $deps = array();
}
?>