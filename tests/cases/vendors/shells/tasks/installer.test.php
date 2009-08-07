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

App::import('Vendors', 'PluginManager.GitTask', array('file' => 'shells' . DS . 'tasks' . DS . 'adapters' . DS . 'git.php'));
App::import('Vendors', 'PluginManager.SvnTask', array('file' => 'shells' . DS . 'tasks' . DS . 'adapters' . DS . 'svn.php'));
App::import('Vendors', 'PluginManager.InstallerTask', array('file' => 'shells' . DS . 'tasks' . DS . 'installer.php'));

Mock::generate('ShellDispatcher');
Mock::generate('GitTask');
Mock::generate('SvnTask');

define('TEST_APP_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))));
define('TEST_APP', TEST_APP_ROOT . DS . 'test_app');

define('FOUR_PLUGIN_LOCATION', TEST_APP . DS . 'plugins' . DS . 'four');
define('THREE_PLUGIN_LOCATION', TEST_APP . DS . 'plugins' . DS . 'three');

Mock::generatePartial(
	'InstallerTask',
	'TestInstallerTask',
	array(
		'formattedOut',
	)
);

class InstallerTaskTestCase extends CakeTestCase {
	function startTest() {
		$params = array(
			'working' => TEST_APP, 
			'app' => 'test_app', 
			'root' => TEST_APP_ROOT, 
			'webroot' => 'webroot', 
		);

		$this->Dispatcher = new MockShellDispatcher();
		$this->Dispatcher->params = $params;

		$this->InstallerTask = new TestInstallerTask($this->Dispatcher);
		$this->InstallerTask->params = $params;

		$this->GitTask = new MockGitTask();
		$this->InstallerTask->Git =& $this->GitTask;

		$this->SvnTask = new MockSvnTask();
		$this->InstallerTask->Svn =& $this->SvnTask;
	}

	function endTest() {
		App::import('Folder');
		$folder = new Folder(FOUR_PLUGIN_LOCATION);
		
		if (file_exists($folder->path)) {
			$folder->delete();
		}
	}

	function testClassExists() {
		$this->assertTrue(class_exists('InstallerTask'));
	}

	function testCreateUrlFile() {
		$url = 'http://example.com/plugin.git';
		$dotUrlPath = THREE_PLUGIN_LOCATION . DS . '.url';

		$this->InstallerTask->_createUrlFile($url, THREE_PLUGIN_LOCATION);
		$this->assertTrue(file_exists($dotUrlPath));
		$this->assertEqual(file_get_contents($dotUrlPath), $url);
		@unlink($dotUrlPath);
	}

	function testCreate() {
		$name = 'example';
		$path = TEST_APP . DS . 'plugins' . DS . $name;

		$result = $this->InstallerTask->_create($name);
		$this->assertTrue($result);
		$this->assertTrue(file_exists($path));
		$this->assertTrue(is_dir($path));
		@unlink($path);
	}

	function testUnderlineInstallWithGit() {
		$url = 'git://example.com/user/example.git';
		$path = TEST_APP . DS . 'plugins' . DS . 'example';
		$name = 'example';

		$this->GitTask->setReturnValue('install', true);
		$this->GitTask->expectOnce('install', array($url, $path));

		$result = $this->InstallerTask->_install($url, $path, $name);
		$this->assertTrue($result);
		@rmdir($path);
	}

	function testUnderlineInstallWithSvn() {
		$url = 'svn://example.com/user/example';
		$path = TEST_APP . DS . 'plugins' . DS . 'example';
		$name = 'example';

		$this->GitTask->setReturnValue('install', false);
		$this->SvnTask->setReturnValue('install', true);

		$this->InstallerTask->expectOnce('formattedOut', array(__d('plugin', '  -> selecionando modo de instalacao: ', true), false));
		$this->GitTask->expectOnce('install', array($url, $path));
		$this->SvnTask->expectOnce('install', array($url, $path, $name));

		$result = $this->InstallerTask->_install($url, $path, $name);
		$this->assertTrue($result);
		@rmdir($path);
	}

	function testRunInstallHook() {
		$plugin = 'with_installer';

		$this->InstallerTask->expectAt(0, 'formattedOut', array(__d('plugin', "  -> verificando a existencia do hook de instalacao...", true)));
		$this->InstallerTask->expectAt(1, 'formattedOut', array(__d('plugin', "    - carregando... ", true), false));
		$this->InstallerTask->expectAt(2, 'formattedOut', array(__d('plugin', "[fg=black][bg=green]  OK  [/bg][/fg]\n  -> executando hook de instalação...", true)));

		$this->InstallerTask->_runInstallHook($plugin);
	}

	function testInstall() {
		$url = 'git://example.com/user/example.git';
		$name = 'example';
		$path = TEST_APP . DS . 'plugins' . DS . $name;
		mkdir($path);

		$this->GitTask->setReturnValue('install', true);

		$result = $this->InstallerTask->install($url, $name);
		$this->assertTrue($result);
		@unlink($path . DS . '.url');
		@rmdir($path);
	}
}
?>