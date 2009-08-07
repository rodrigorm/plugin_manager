<?php
App::import('Plugins', 'ImprovedCakeShell.ImprovedCakeShell');

/**
 * Instalador de plugins utilizando um repositório Git
 */
class GitTask extends ImprovedCakeShell {
	function install($url, $pluginPath) {
		$this->_isSupported();

		$return = true;

		if ($this->_dotGitPathExists()) {
			$output = __d('plugin', '  -> adicionando novo submodulo... ', true);
			$errors = $this->_submodule($url, $pluginPath);
		} else {
			$output = __d('plugin', '  -> clonando repositorio... ', true);
			$errors = $this->_clone($url, $pluginPath);
		}

		if (!empty($errors)) {
			$return = false;
		}

		if ($return) {
			$this->formattedOut(__d('plugin', '[fg=yellow][u]GIT[/u][/fg]', true));
			$this->formattedOut($output, false);
		} else {
			foreach ($errors as $error) {
				$this->formattedOut("    - $error");
			}
		}

		return $return;
	}

/**
 * Verificar se o git está instalado e funcionando
 */
	function _isSupported() {
		if (!$this->_exec('git --version 2>/dev/null', false)) {
			$this->formattedOut(__d('plugin', "[bg=red][fg=black] ERRO : GIT não suportado [/fg][/bg]\n", true));
			$this->_stop();
		}
	}

/**
 * Verificar se existe a pasta APP/.git
 */
	function _dotGitPathExists() {
		return file_exists($this->params['working'] . DS . '.git' . DS);
	}

/**
 * Instala o plugin através do git clone
 */
	function _clone($url, $pluginPath) {
		$return = $this->_exec('git clone ' . $url . ' ' . $pluginPath);

		$pattern = "/.*fatal.*/im";
		$found = null;

		if (!preg_match_all($pattern, $return, $found)) {
			$this->_excludeGitFolder($pluginPath);
		}

		return $found[0];
	}

/**
 * Instala o plugin através do git submodule add
 */
	function _submodule($url, $pluginPath) {
		$moduleLocation = str_replace($this->params['working'], '', $pluginPath);
		$return = $this->_exec('git submodule add ' . $url . ' ' . $moduleLocation);

		$pattern = "/.*fatal.*/im";
		$found = array();
		preg_match_all($pattern, $return, $found);

		if(empty($found[0])) {
			$this->_exec('git submodule init && git submodule update', false);
		}

		return $found[0];
	}

/**
 * Remove a pasta .git
 */
	function _excludeGitFolder($pluginPath) {
		App::import('Folder');

		$gitFolder = $pluginPath . DS . '.git' . DS;
		$folder    = new Folder($gitFolder, false);
		$folder->delete($gitFolder);
	}

	function _exec($cmd, $stdErr = true) {
		$suffix = '';
		if ($stdErr) {
			$suffix = ' 2>&1';
		}
		
		return shell_exec($cmd . $suffix);
	}
}
?>