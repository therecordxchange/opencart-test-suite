<?php
/**
 * Need to emulate browser output for controllers
 */
class TestResponse extends Response {
	
	private $headers = array();
	private $level = 0;
	private $output;
        private $redirect = false;

	public function addHeader($header) {
		$this->headers[] = $header;
	}

	public function redirect($url, $status = 302) {
                $trace = debug_backtrace();
                $caller = $trace[1];
                $this->setOutput("\nRedirect called to: [$url] by [{$caller['function']}]\n");
                $this->redirect = true;
	}

	public function setCompression($level) {
		$this->level = $level;
	}

	public function setOutput($output) {
                if(!$this->redirect) {
                    $this->output = $output;
                }
	}

	public function getOutput() {
		return $this->output;
	}

	private function compress($data, $level = 0) {
		if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)) {
			$encoding = 'gzip';
		}

		if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)) {
			$encoding = 'x-gzip';
		}

		if (!isset($encoding) || ($level < -1 || $level > 9)) {
			return $data;
		}

		if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
			return $data;
		}

		if (headers_sent()) {
			return $data;
		}

		if (connection_status()) {
			return $data;
		}

		$this->addHeader('Content-Encoding: ' . $encoding);

		return gzencode($data, (int)$level);
	}

	public function output() {
		if ($this->output) {
			if ($this->level) {
				$output = $this->compress($this->output, $this->level);
			} else {
				$output = $this->output;
			}

			if (!headers_sent()) {
				foreach ($this->headers as $header) {
					header($header, true);
				}
			}

			echo $output;
		}
	}	
	
}