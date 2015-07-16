<?php
/**
 * PHP class for JavaScript minification  using UglifyJS as a service.
 *
 * Created by Makis Tracend (@tracend), Cadicvnn
 * Distributed through [Makesites.org](http://makesites.org/)
 * Released under the [Apache License v2.0](http://makesites.org/licenses/APACHE-2.0)
 */
class UglifyJS {

	private $_mode = "WHITESPACE_ONLY";
	private $_warning_level = "DEFAULT";
	private $_pretty_print = false;
	private $_debug = true;
	private $_compiler = array(
		"host" => "marijnhaverbeke.nl",
		"port" => "80",
		"path" => "/uglifyjs"
	);

	public function __construct() {}

	public function compiler( $string ) {
		// get the previous compiler
		$compiler = $this->_compiler;
		$url = parse_url( $string );
		// gather vars
		if( array_key_exists("host", $url) ) $compiler['host'] = $url['host'];
		if( array_key_exists("port", $url) ) $compiler['port'] = $url['port'];
		if( array_key_exists("path", $url) ) $compiler['path'] = $url['path'];
		// save back the compiler
		$this->_compiler = $compiler;
		return $compiler;
	}

	/**
	 * Tells the compiler to pretty print the output.
	 */
	function prettyPrint() {
		$this->_pretty_print = true;
		return $this;
	}

	/**
	 * Turns of the debug info.
	 * By default statistics, errors and warnings are logged to the console.
	 */
	function hideDebugInfo() {
		$this->_debug = false;
		return $this;
	}

	/**
	 * Sets the compilation mode to optimize whitespace only.
	 */
	function whitespaceOnly() {
		$this->_mode = "WHITESPACE_ONLY";
		return $this;
	}

	/**
	 * Sets the compilation mode to simple optimizations.
	 */
	function simpleMode() {
		$this->_mode = "SIMPLE_OPTIMIZATIONS";
		return $this;
	}

	/**
	 * Sets the compilation mode to advanced optimizations (recommended).
	 */
	function advancedMode() {
		$this->_mode = "ADVANCED_OPTIMIZATIONS";
		return $this;
	}

	/**
	 * Gets the compilation mode from the URL, set the mode param to
	 * 'w', 's' or 'a'.
	 */
	function getModeFromUrl() {
		if ($_GET['mode'] == 's') $this->simpleMode();
		else if ($_GET['mode'] == 'a') $this->advancedMode();
		else $this->whitespaceOnly();
		return $this;
	}

	/**
	 * Sets the warning level to QUIET.
	 */
	function quiet() {
		$this->_warning_level = "QUIET";
		return $this;
	}

	/**
	 * Sets the default warning level.
	 */
	function defaultWarnings() {
		$this->_warning_level = "DEFAULT";
		return $this;
	}

	/**
	 * Sets the warning level to VERBOSE.
	 */
	function verbose() {
		$this->_warning_level = "VERBOSE";
		return $this;
	}

	/**
	 * Writes the compiled response.
	 */
	function write( $js ) {
		return $this->_compile( $js );
	}

	function _compile( $js ) {
		// No debug info?
		$result = $this->_makeRequest( $js );
		return $result;
	}

	function _getParams( $js ) {
		$params = array();
		foreach ($this->_getParamList( $js ) as $key => $value) {
			$params[] = preg_replace("/_[0-9]$/", "", $key) . "=" . urlencode($value);
		}
		return implode("&", $params);
	}

	function _getParamList( $js ) {
		$params = array();
		$params["js_code"] = $js;
		$params["compilation_level"] = $this->_mode;
		$params["output_format"] = "xml";
		$params["warning_level"] = $this->_warning_level;
		if ($this->_pretty_print) $params["formatting"] = "pretty_print";
		$params["output_info_1"] = "compiled_code";
		$params["output_info_2"] = "statistics";
		$params["output_info_3"] = "warnings";
		$params["output_info_4"] = "errors";
		return $params;
	}

	function _makeRequest( $js ) {
		$data = $this->_getParams( $js );
		$referer = @$_SERVER["HTTP_REFERER"] or "";
		// variables
		extract($this->_compiler);

		$fp = fsockopen($host, $port);
		if (!$fp) {
			throw new Exception("Unable to open socket");
		}

		if ($fp) {
			fputs($fp, "POST $path HTTP/1.1\r\n");
			fputs($fp, "Host: $host\r\n");
			fputs($fp, "Referer: $referer\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: ". strlen($data) ."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $data);

			$result = "";
			while (!feof($fp)) {
				$result .= fgets($fp, 128);
			}

			fclose($fp);
		}

		$data = substr($result, (strpos($result, "\r\n\r\n")+4));
		if (strpos(strtolower($result), "transfer-encoding: chunked") !== FALSE) {
			$data = $this->_unchunk($data);
		}

		return $data;
	}

	function _unchunk($data) {
		$fp = 0;
		$outData = "";
		while ($fp < strlen($data)) {
			$rawnum = substr($data, $fp, strpos(substr($data, $fp), "\r\n") + 2);
			$num = hexdec(trim($rawnum));
			$fp += strlen($rawnum);
			$chunk = substr($data, $fp, $num);
			$outData .= $chunk;
			$fp += strlen($chunk);
		}
		return $outData;
	}

}
