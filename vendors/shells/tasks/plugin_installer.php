<?php
App::import('Plugins', 'ImprovedCakeShell.ImprovedCakeShell');

class PluginInstallerTask extends ImprovedCakeShell {
	var $tasks = array('Installer');
	var $deps = array();

	function install() {}

	function _installDeps() {
		$this->formattedOut(__d('plugin', '      -> Verificando a existencia de dependencias...', true), false);

		if (empty($this->deps)) {
			$this->formattedOut(__d('plugin', '[fg=black][bg=green]  OK  [/bg][/fg]'), false);
			$this->_stop();
		}

		$this->formattedOut("\n", false);

		foreach ($this->deps as $name => $url) {
			if ($this->Installer->install($url, $name)) {
				$this->formattedOut(String::insert(__d('plugin', "    [fg=green][u]:plugin[/u][/fg] instalado com sucesso!\n", true), array('plugin' => $name)));
			} else {
				$this->formattedOut(String::insert(__d('plugin', "    Nao foi possivel instalar [fg=red][u]:plugin[/u][/fg]\n", true), array('plugin' => $name)));
			}
		}
	}
}
?>