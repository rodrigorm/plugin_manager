<?php
App::import('Core', 'Shell');

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

App::import('Vendors', 'PluginManager.InstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'installer.php'));
App::import('Vendors', 'PluginManager.PluginInstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'plugin_installer.php'));

Mock::generate('ShellDispatcher');
Mock::generate('InstallerTask');

define('TEST_APP_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))));
define('TEST_APP', TEST_APP_ROOT . DS . 'test_app');

Mock::generatePartial(
	'PluginInstallerTask',
	'TestPluginInstallerTask',
	array(
		'formattedOut',
	)
);

class PluginInstallerTaskTestCase extends CakeTestCase {
	function startTest() {
		$params = array(
			'working' => TEST_APP, 
			'app'     => 'test_app', 
			'root'    => TEST_APP_ROOT, 
			'webroot' => 'webroot', 
		);

		$this->Dispatcher = new MockShellDispatcher();
		$this->Dispatcher->params = $params;

		$this->PluginInstallerTask = new TestPluginInstallerTask($this->Dispatcher);
		$this->PluginInstallerTask->params = $params;

		$this->InstallerTask = new MockInstallerTask($this->Dispatcher);
		$this->InstallerTask->params = $params;
		$this->PluginInstallerTask->Installer =& $this->InstallerTask;
	}

	function testClassExists() {
		$this->assertTrue(class_exists('PluginInstallerTask'));
	}

	function testInstallDeps() {
		$deps = array(
			'example'  => 'git://example.com/user/example.git',
			'example1' => 'git://example.com/user/example1.git'
		);
		$this->PluginInstallerTask->deps = $deps;

		$timing = 0;
		foreach ($deps as $name => $url) {
			$this->InstallerTask->expectAt($timing++, 'install', array($url, $name));
		}
		$this->PluginInstallerTask->_installDeps();
	}
}
?>