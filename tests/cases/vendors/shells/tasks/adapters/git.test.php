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

App::import('Vendors', 'PluginManager.GitTask', array('file' => 'shells/tasks/adapters/git.php'));

Mock::generate('ShellDispatcher');

define('TEST_APP_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));
define('TEST_APP', TEST_APP_ROOT . DS . 'test_app');

define('TEST_GIT_PLUGIN', TEST_APP_ROOT . DS . 'test_git_plugin');
define('FOUR_PLUGIN_LOCATION', TEST_APP . DS . 'plugins' . DS . 'four');

Mock::generatePartial(
	'GitTask',
	'TestGitTask',
	array(
		'formattedOut',
		'_exec',
		'_stop'
	)
);

class GitTaskTestCase extends CakeTestCase {
	function startTest() {
		$this->skipUnless(shell_exec('git --version 2>/dev/null'), 'Git não está instalado');

		$params = array(
			'working' => TEST_APP, 
			'app'     => 'test_app', 
			'root'    => TEST_APP_ROOT, 
			'webroot' => 'webroot', 
		);

		$this->Dispatcher = new MockShellDispatcher();
		$this->Dispatcher->params = $params;
		$this->GitTask = new TestGitTask($this->Dispatcher);
		$this->GitTask->params = $params;
	}

	function endTest() {
		App::import('Folder');
		$folder = new Folder(FOUR_PLUGIN_LOCATION);
		
		if (file_exists($folder->path)) {
			$folder->delete();
		}
	}

	function testClassExists() {
		$this->assertTrue(class_exists('GitTask'));
	}

	function testIsSupported() {
		$this->GitTask->setReturnValue('_exec', false);
		$this->GitTask->expectOnce('_stop');
		$this->GitTask->expectOnce('formattedOut', array(
			__d('plugin', "[bg=red][fg=black] ERRO : GIT não suportado [/fg][/bg]\n", true)
		));
		$this->GitTask->_isSupported();
	}

	function testIsSupportedReturnsTrue() {
		$this->GitTask->setReturnValue('_exec', 'git version 1.6.3.2');
		$this->GitTask->expectNever('formattedOut');
		$this->GitTask->expectNever('_stop');
		$this->GitTask->_isSupported();
	}

	function testDotGitPathExists() {
		@rmdir(TEST_APP . DS . '.git' . DS);
		$result = $this->GitTask->_dotGitPathExists();
		$this->assertFalse($result);
	}
	
	function testDotGitPathExistsReturnsTrue() {
		@mkdir(TEST_APP . DS . '.git' . DS);
		$result = $this->GitTask->_dotGitPathExists();
		$this->assertTrue($result);
		@rmdir(TEST_APP . DS . '.git' . DS);
	}

	function testClone() {
		$url = 'git://example.com/user/example.git';
		$pluginPath = 'plugins/example';
		$output = "Initialized empty Git repository in " . TEST_APP . DS . $pluginPath . DS . ".git/\n" . 
		"remote: Counting objects: 413, done.\n" . 
		"remote: Compressing objects: 100% (293/293), done.\n" . 
		"remote: Total 413 (delta 206), reused 118 (delta 59)\n" . 
		"Receiving objects: 100% (413/413), 71.08 KiB | 51 KiB/s, done.\n" . 
		"Resolving deltas: 100% (206/206), done.\n";

		$this->GitTask->setReturnValue('_exec', $output);
		$this->GitTask->expectOnce('_exec', array('git clone ' . $url . ' ' . $pluginPath));

		$result = $this->GitTask->_clone($url, $pluginPath);
		$this->assertEqual($result, array());
	}

	function testCloneFails() {
		$url = 'git://example.com/user/example.git';
		$pluginPath = 'app/plugins/example';
		$output = "Initialized empty Git repository in /Users/rodrigomoyle/Desktop/Code/tmp/plugin_manager.agit/.git/\n" . 
		"fatal: protocol error: expected sha/ref, got '\n" . 
		"*********'\n\n" . 
		"No matching repositories found.\n\n" . 
		"*********'\n\n";

		$this->GitTask->setReturnValue('_exec', $output);
		$this->GitTask->expectOnce('_exec', array('git clone ' . $url . ' ' . $pluginPath));

		$result = $this->GitTask->_clone($url, $pluginPath);
		$this->assertEqual($result, array('fatal: protocol error: expected sha/ref, got \''));
	}

	function testSubmodule() {
		$url = 'git://example.com/user/example.git';
		$pluginPath = 'plugins/example';
		$output = "Initialized empty Git repository in " . TEST_APP . DS . $pluginPath . DS . ".git/\n" . 
		"remote: Counting objects: 413, done.\n" . 
		"remote: Compressing objects: 100% (293/293), done.\n" . 
		"remote: Total 413 (delta 206), reused 118 (delta 59)\n" . 
		"Receiving objects: 100% (413/413), 71.08 KiB | 51 KiB/s, done.\n" . 
		"Resolving deltas: 100% (206/206), done.\n";

		$this->GitTask->setReturnValue('_exec', $output);
		$this->GitTask->expectAt(0, '_exec', array('git submodule add ' . $url . ' ' . $pluginPath));
		$this->GitTask->expectAt(1, '_exec', array('git submodule init && git submodule update', false));

		$result = $this->GitTask->_submodule($url, $pluginPath);
		$this->assertEqual($result, array());
	}

	function testSubmoduleFails() {
		$url = 'git://example.com/user/example.git';
		$pluginPath = 'app/plugins/example';
		$output = "Initialized empty Git repository in /Users/rodrigomoyle/Desktop/Code/tmp/plugin_manager.agit/.git/\n" . 
		"fatal: protocol error: expected sha/ref, got '\n" . 
		"*********'\n\n" . 
		"No matching repositories found.\n\n" . 
		"*********'\n\n";

		$this->GitTask->setReturnValue('_exec', $output);
		$this->GitTask->expectOnce('_exec', array('git submodule add ' . $url . ' ' . $pluginPath));

		$result = $this->GitTask->_submodule($url, $pluginPath);
		$this->assertEqual($result, array('fatal: protocol error: expected sha/ref, got \''));
	}

	function testExcludeGitFolder() {
		$pluginPath = TEST_APP . DS . 'plugins' . DS . 'example';
		$gitFolder = $pluginPath . DS . '.git' . DS;
		@mkdir($gitFolder);
		$result = $this->GitTask->_excludeGitFolder($pluginPath);
		$this->assertFalse(file_exists($gitFolder));
		@rmdir($gitFolder);
		@rmdir($pluginPath);
	}

	function testInstall() {
		$url = 'git://example.com/user/example.git';
		$pluginPath = TEST_APP . DS . 'plugins' . DS . 'example';

		$gitFolder = $pluginPath . DS . '.git' . DS;
		@rmdir($gitFolder);

		$gitVersionParams = array('git --version 2>/dev/null', false);
		$this->GitTask->setReturnValueAt(0, '_exec', 'git version 1.6.3.2', $gitVersionParams);

		$gitCloneParams = array('git clone ' . $url . ' ' . $pluginPath);
		$output = "Initialized empty Git repository in " . $pluginPath . DS . ".git/\n" . 
		"remote: Counting objects: 413, done.\n" . 
		"remote: Compressing objects: 100% (293/293), done.\n" . 
		"remote: Total 413 (delta 206), reused 118 (delta 59)\n" . 
		"Receiving objects: 100% (413/413), 71.08 KiB | 51 KiB/s, done.\n" . 
		"Resolving deltas: 100% (206/206), done.\n";
		$this->GitTask->setReturnValueAt(1, '_exec', $output, $gitCloneParams);

		$this->GitTask->expectAt(0, '_exec', $gitVersionParams);
		$this->GitTask->expectAt(1, '_exec', $gitCloneParams);

		$result = $this->GitTask->install($url, $pluginPath);
		$this->assertTrue($result);
	}
}
?>