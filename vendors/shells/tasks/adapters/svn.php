<?php
App::import('Plugins', 'ImprovedCakeShell.ImprovedCakeShell');

/**
 * Instalador de plugins utilizando um repositório SVN
 */
class SvnTask extends ImprovedCakeShell {
	function install($url, $pluginPath, $name) {
		$this->_isSupported();

		$return = true;
		if ($this->_dotSvnPathExists()) {
			$output = __d('plugin', '  -> adicionando svn:external... ', true);
			$errors = $this->_externals($url, $pluginPath, $name);
		} else {
			$output = __d('plugin', '  -> importando repositorio... ', true);
			$errors = $this->_export($url, $pluginPath);
		}

		if (!empty($errors)) {
			$return = false;
		}

		if ($return) {
			$this->formattedOut(__d('plugin', '[fg=yellow][u]SVN[/u][/fg]', true));
			$this->formattedOut($output, false);
		} else {
			foreach ($errors as $error) {
				$this->formattedOut("    - $error");
			}
		}

		return $return;
	}

/**
 * Verificar se o SVN está instalado e funcionando
 */
	function _isSupported() {
		if (!$this->_exec('svn --version 2>/dev/null', false)) {
			$this->formattedOut(__d('plugin', "[bg=red][fg=black] ERRO : SVN não suportado [/fg][/bg]\n", true));
			$this->_stop();
		}
	}

/**
 * Verificar se existe a pasta APP/.svn
 */
	function _dotSvnPathExists() {
		return file_exists($this->params['working'] . DS . '.svn' . DS);
	}

/**
 * Instala o plugin através do svn export
 */
	function _export($url, $pluginPath) {
		$return = $this->_exec('svn export ' . $url . ' ' . $pluginPath);

		$pattern = "/^svn\:.*/i";
		$found = null;
		preg_match_all($pattern, $return, $found);

		return $found[0];
	}

/**
 * Instala o plugin através do svn eternals
 */
	function _externals($url, $pluginPath, $name) {
		$this->formattedOut('');

		$externals = $this->_getExternals();
		$externals .= "\nplugins" . DS . $name . ' ' . $url;

		if (file_put_contents('.externals-tmp', $externals) !== false) {
			$this->_exec('svn propset -q svn:externals . -F .externals-tmp');
			$return = $this->_exec('svn update', false);
			unlink('.externals-tmp');
		}

		$pattern = "/^svn\:.*/i";
		$found = null;
		preg_match_all($pattern, $return, $found);

		return $found[0];
	}

	function _getExternals() {
		return trim($this->_exec('svn propget svn:externals .', false));
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