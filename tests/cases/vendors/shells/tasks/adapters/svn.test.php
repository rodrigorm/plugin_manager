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

App::import('Vendors', 'PluginManager.SvnTask', array('file' => 'shells/tasks/adapters/svn.php'));

Mock::generate('ShellDispatcher');

define('TEST_APP_ROOT', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));
define('TEST_APP', TEST_APP_ROOT . DS . 'test_app');

define('TEST_SVN_PLUGIN', TEST_APP_ROOT . DS . 'test_svn_plugin');
define('FOUR_PLUGIN_LOCATION', TEST_APP . DS . 'plugins' . DS . 'four');

Mock::generatePartial(
	'SvnTask',
	'TestSvnTask',
	array(
		'formattedOut',
		'_exec',
		'_stop'
	)
);

class SvnTaskTestCase extends CakeTestCase {
	function startTest() {
		$this->skipUnless(shell_exec('svn --version 2>/dev/null'), 'Svn não está instalado');

		$params = array(
			'working' => TEST_APP, 
			'app'     => 'test_app', 
			'root'    => TEST_APP_ROOT, 
			'webroot' => 'webroot', 
		);

		$this->Dispatcher = new MockShellDispatcher();
		$this->Dispatcher->params = $params;
		$this->SvnTask = new TestSvnTask($this->Dispatcher);
		$this->SvnTask->params = $params;
	}

	function endTest() {
		App::import('Folder');
		$folder = new Folder(FOUR_PLUGIN_LOCATION);
		
		if (file_exists($folder->path)) {
			$folder->delete();
		}
	}

	function testClassExists() {
		$this->assertTrue(class_exists('SvnTask'));
	}

	function testIsSupported() {
		$this->SvnTask->setReturnValue('_exec', false);
		$this->SvnTask->expectOnce('_stop');
		$this->SvnTask->expectOnce('formattedOut', array(
			__d('plugin', "[bg=red][fg=black] ERRO : SVN não suportado [/fg][/bg]\n", true)
		));
		$this->SvnTask->_isSupported();
	}

	function testIsSupportedReturnsTrue() {
		$output = "svn, version 1.4.4 (r25188)\n" . 
		"   compiled Nov 25 2007, 08:20:33\n\n" . 
		"Copyright (C) 2000-2006 CollabNet.\n" . 
		"Subversion is open source software, see http://subversion.tigris.org/\n" . 
		"This product includes software developed by CollabNet (http://www.Collab.Net/).\n\n" . 
		"The following repository access (RA) modules are available:\n\n" . 
		"* ra_dav : Module for accessing a repository via WebDAV (DeltaV) protocol.\n" . 
		"  - handles 'http' scheme\n" . 
		"  - handles 'https' scheme\n" . 
		"* ra_svn : Module for accessing a repository using the svn network protocol.\n" . 
		"  - handles 'svn' scheme\n" . 
		"* ra_local : Module for accessing a repository on local disk.\n" . 
		"  - handles 'file' scheme\n\n\n";
		$this->SvnTask->setReturnValue('_exec', $output);
		$this->SvnTask->expectNever('formattedOut');
		$this->SvnTask->expectNever('_stop');
		$this->SvnTask->_isSupported();
	}

	function testDotSvnPathExists() {
		@rmdir(TEST_APP . DS . '.svn' . DS);
		$result = $this->SvnTask->_dotSvnPathExists();
		$this->assertFalse($result);
	}
	
	function testDotSvnPathExistsReturnsTrue() {
		@mkdir(TEST_APP . DS . '.svn' . DS);
		$result = $this->SvnTask->_dotSvnPathExists();
		$this->assertTrue($result);
		@rmdir(TEST_APP . DS . '.svn' . DS);
	}

	function testExport() {
		$url = 'svn://example.com/user/example';
		$pluginPath = 'plugins/example';
		$output = "A    example/example_app_controller.php\n" .
		"A    example/example_app_model.php\n" .
		"A    example/models/example.php\n" .
		"A    example/controllers/examples_controller.php\n" .
		"Exported revision 1.";

		$this->SvnTask->setReturnValue('_exec', $output);
		$this->SvnTask->expectOnce('_exec', array('svn export ' . $url . ' ' . $pluginPath));

		$result = $this->SvnTask->_export($url, $pluginPath);
		$this->assertEqual($result, array());
	}

	function testExportFails() {
		$url = 'svn://example.com/user/example';
		$pluginPath = 'plugins/example';
		$output = "svn: URL '" . $url . "' doesn't exist\n";

		$this->SvnTask->setReturnValue('_exec', $output);
		$this->SvnTask->expectOnce('_exec', array('svn export ' . $url . ' ' . $pluginPath));

		$result = $this->SvnTask->_export($url, $pluginPath);
		$this->assertEqual($result, array("svn: URL '" . $url . "' doesn't exist"));
	}

	function testExternals() {
		$url = 'svn://example.com/user/example';
		$pluginPath = 'plugins/example';
		$name = 'example';

		$this->SvnTask->setReturnValue('_exec', '', array('svn propget svn:externals .', false));
		$this->SvnTask->setReturnValue('_exec', '', array('svn propset -q svn:externals . -F .externals-tmp'));
		$output = "\nFetching external item into 'app/plugins/example'\n" . 
		"A    app/plugins/example/index.php\n" . 
		"Updated external to revision 1.\n\n" . 
		"Updated to revision 1.\n";
		$this->SvnTask->setReturnValue('_exec', $output, array('svn update', false));
	
		$this->SvnTask->expectAt(0, '_exec', array('svn propget svn:externals .', false));
		$this->SvnTask->expectAt(1, '_exec', array('svn propset -q svn:externals . -F .externals-tmp'));
		$this->SvnTask->expectAt(2, '_exec', array('svn update', false));
	
		$result = $this->SvnTask->_externals($url, $pluginPath, $name);
		$this->assertEqual($result, array());
	}
	
	function testExternalsFails() {
		$url = 'svn://example.com/user/example';
		$pluginPath = 'plugins/example';
		$name = 'example';

		$this->SvnTask->setReturnValue('_exec', '', array('svn propget svn:externals .', false));
		$this->SvnTask->setReturnValue('_exec', '', array('svn propset -q svn:externals . -F .externals-tmp'));
		$output = "svn: URL '" . $url . "' doesn't exist\n";
		$this->SvnTask->setReturnValue('_exec', $output, array('svn update', false));
	
		$this->SvnTask->expectAt(0, '_exec', array('svn propget svn:externals .', false));
		$this->SvnTask->expectAt(1, '_exec', array('svn propset -q svn:externals . -F .externals-tmp'));
		$this->SvnTask->expectAt(2, '_exec', array('svn update', false));
	
		$result = $this->SvnTask->_externals($url, $pluginPath, $name);
		$this->assertEqual($result, array("svn: URL '" . $url . "' doesn't exist"));
	}

	function testInstall() {
		$url = 'svn://example.com/user/example';
		$pluginPath = TEST_APP . DS . 'plugins' . DS . 'example';
		$name = 'example';

		$svnFolder = $pluginPath . DS . '.svn' . DS;
		@rmdir($svnFolder);

		$svnVersionParams = array('svn --version 2>/dev/null', false);
		$output = "svn, version 1.4.4 (r25188)\n" . 
		"   compiled Nov 25 2007, 08:20:33\n\n" . 
		"Copyright (C) 2000-2006 CollabNet.\n" . 
		"Subversion is open source software, see http://subversion.tigris.org/\n" . 
		"This product includes software developed by CollabNet (http://www.Collab.Net/).\n\n" . 
		"The following repository access (RA) modules are available:\n\n" . 
		"* ra_dav : Module for accessing a repository via WebDAV (DeltaV) protocol.\n" . 
		"  - handles 'http' scheme\n" . 
		"  - handles 'https' scheme\n" . 
		"* ra_svn : Module for accessing a repository using the svn network protocol.\n" . 
		"  - handles 'svn' scheme\n" . 
		"* ra_local : Module for accessing a repository on local disk.\n" . 
		"  - handles 'file' scheme\n\n\n";
		$this->SvnTask->setReturnValueAt(0, '_exec', $output, $svnVersionParams);

		$svnExportParams = array('svn export ' . $url . ' ' . $pluginPath);
		$output = "A    example/example_app_controller.php\n" .
		"A    example/example_app_model.php\n" .
		"A    example/models/example.php\n" .
		"A    example/controllers/examples_controller.php\n" .
		"Exported revision 1.";
		$this->SvnTask->setReturnValueAt(1, '_exec', $output, $svnExportParams);

		$this->SvnTask->expectAt(0, '_exec', $svnVersionParams);
		$this->SvnTask->expectAt(1, '_exec', $svnExportParams);

		$result = $this->SvnTask->install($url, $pluginPath, $name);
		$this->assertTrue($result);
	}
}
?>