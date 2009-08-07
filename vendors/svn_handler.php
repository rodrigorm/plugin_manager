<?php
class SvnHandlerPM {
	var $mainShell;

	function __construct($_params) {
		$this->mainShell = $_params['mainShell'];

		$this->_isSvnSupported();
	}

	function _isSvnSupported() {
		if (!shell_exec('svn --version 2>/dev/null')) {
			$this->mainShell->formattedOut(__d('plugin', "\n[bg=red][fg=black] ERRO : SVN nao suportado [/fg][/bg]\n", true));
			exit;
		}
	}

	function _dotSvnPathExists() {
		return file_exists(APP . '.svn/');
	}

	function _export($_url, $_pluginName) {
		$return = shell_exec('svn export ' . $_url . ' ' . APP . 'plugins' . DS . $_pluginName . ' 2>&1');

		$pattern = "/^svn\:.*/i";
		$found;
		preg_match_all($pattern, $return, $found);

		return $found[0];
	}

	function _getExternals() {
		return trim(shell_exec('svn propget svn:externals .'));
	}

	function _externals($_url, $_pluginName) {
		$this->mainShell->out('');

		$externals = $this->_getExternals();
		$externals .= "\n" . "plugins" . DS . "$_pluginName $_url";

		if (file_put_contents('.externals-tmp', $externals) !== false) {
			$return = shell_exec('svn propset -q svn:externals . -F .externals-tmp 2>&1');
			shell_exec('svn update');
			unlink('.externals-tmp');
		}

		$pattern = "/^svn\:.*/i";
		$found;
		preg_match_all($pattern, $return, $found);

		return $found[0];
	}

	function install($_url, $_pluginName) {
		$return = true;

		if ($this->_dotSvnPathExists()) {
			$this->mainShell->formattedOut(__d('plugin', '  -> adicionando svn:external... ', true), false);

			$errors = $this->_externals($_url, $_pluginName);
			if (!empty($errors)) {
				$this->mainShell->formattedOut(__d('plugin', '[fg=black][bg=red] ERRO [/bg][/fg]', true));
				$return = false;
			}
		} else {
			$this->mainShell->formattedOut(__d('plugin', '  -> importando repositorio... ', true), false);

			$errors = $this->_export($_url, $_pluginName);
			if (!empty($errors)) {
				$this->mainShell->formattedOut(__d('plugin', '[fg=black][bg=red] ERRO [/bg][/fg]', true));
				$return = false;
			}
		}

		if ($return) {
			$this->mainShell->formattedOut(__d('plugin', '[fg=black][bg=green]  OK  [/bg][/fg]', true));
		} else {
			foreach($errors as $error) {
				$this->mainShell->formattedOut("    - $error");
			}
		}

		return $return;
	}
}
?>