<?php
/**
 * URL Class for the WordPress plugin WordPress HTTPS
 * 
 * This class and it's properties are heavily based on parse_url()
 *
 * @author Mike Ems
 * @package WordPressHTTPS
 *
 */

require_once('Base.php');

class WordPressHTTPS_Url extends WordPressHTTPS_Base {

	/**
	 * The scheme of a network host; for example, http or https
	 *
	 * @var string
	 */
	protected $_scheme;

	/**
	 * The domain name of a network host, or an IPv4 address as a set of four decimal digit groups separated by literal periods; for example, www.php.net or babelfish.altavista.com
	 *
	 * @var string
	 */
	protected $_host;

	/**
	 * The base domain of a network host; for example, php.net or altavista.com
	 *
	 * @var string
	 */
	protected $_base_host;

	/**
	 * The port being accessed. In the URL http://www.some_host.com:443/, 443 is the port component.
	 *
	 * @var int
	 */
	protected $_port;

	/**
	 * The username being passed for authentication. In the URL ftp://some_user:some_password@ftp.host.com/, some_user would be the user component.
	 *
	 * @var string
	 */
	protected $_user;

	/**
	 * The password being passed for authentication. In the above example, some_password would be the pass component.
	 *
	 * @var string
	 */
	protected $_pass;

	/**
	 * The path component contains the location to the requested resource on the given host. In the URL http://www.foo.com/test/test.php, /test/test.php is the path component.
	 *
	 * @var string
	 */
	protected $_path;

	/**
	 * The filename, if available, is the specified resource being requested. In the URL http://www.foo.com/test/test.jpg, test.jpg is the filename.
	 *
	 * @var string
	 */
	protected $_filename;

	/**
	 * The file extension of the filename, if available. In the URL http://www.foo.com/test/test.jpg, .jpg is the file extension.
	 *
	 * @var string
	 */
	protected $_extension;

	/**
	 * The query string for the request. In the URL http://www.foo.com/?page=bar, page=bar is the query component.
	 *
	 * @var string
	 */
	protected $_query;

	/**
	 * The response body of the request.
	 *
	 * @var string
	 */
	protected $_content;

	/**
	 * Set Path
	 * 
	 * Ensures the path begins with a forward slash
	 *
	 * @param none
	 * @return string
	 */
	public function setPath( $path ) {
		$this->_path = '/' . ltrim($path, '/');
		$this->_filename = basename($this->_path);
		$pathinfo = pathinfo($this->_filename);
		if ( $pathinfo && isset($pathinfo['extension']) ) {
			$this->_extension = $pathinfo['extension'];
		}
		return $this;
	}

	/**
	 * Get Path
	 * 
	 * Ensures the path begins with a forward slash
	 *
	 * @param none
	 * @return string
	 */
	public function getPath() {
		$this->_path = '/' . trim($this->_path, '/');

		return $this->_path;
	}

	/**
	 * Returns the base host of the URL
	 *
	 * @param none
	 * @return string
	 */
	public function getBaseHost() {
		$return_url = clone $this;
		$test_url = clone $this;
		$host_parts = explode('.', $test_url->get('host'));
		for ( $i = 0; $i <= sizeof($host_parts); $i++ ) {
			if ( $test_url->set('host', str_replace($host_parts[$i] . '.', '', $test_url->get('host')) )->isValid() ) {
				$return_url = clone $test_url;
			} else {
				break;
			}
		}
		return $return_url->get('host');
	}

	/**
	 * Get the contents of the URL
	 *
	 * @param boolean $verify_ssl
	 * @return boolean
	 */
	public function getContent( $verify_ssl = false ) {
		if ( function_exists('curl_init') ) {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->toString());
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

			$content = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			if ( !$info['http_code'] || ( $info['http_code'] == 0 || $info['http_code'] == 404 ) ) {
				return false;
			} else {
				return $content;
			}
		} else if ( @ini_get('allow_url_fopen') ) {
			if ( ($content = @file_get_contents($url)) !== false ) {
				return $content;
			}
		}
		return false;
	}
	
	/**
	 * Factory object from an array provided by the parse_url function
	 * 
	 * Example of usage from within the plugin or modules:
	 * WordPressHTTPS::factory('Url')->fromArray( parse_url( site_url() ) );
	 *
	 * @param array $array
	 * @return $url WordPressHTTPS_Url
	 */
	public static function fromArray( $array ) {
		$url = new WordPressHTTPS_Url;

		foreach( $array as $key => $value ) {
			$property = '_' . $key;
			if ( property_exists($url, $property) ) {
				$url->set($key, $value);
			}
		}
		return $url;
	}

	/**
	 * Factory object from a string that contains a URL
	 *
	 * @param string $string
	 * @return $url WordPressHTTPS_Url
	 */
	public static function fromString( $string ) {
		$url = new WordPressHTTPS_Url;

		@preg_match_all('/(http|https):\/\/[\/-\w\d\.,~#@^!\'()?=\+&%;:[\]]+/i', $string, $url_parts);
		if ( isset($url_parts[0][0]) ) {
			if ( $url_parts = parse_url( $url_parts[0][0] ) ) {
				foreach( $url_parts as $key => $value ) {
					$property = '_' . $key;
					if ( property_exists($url, $property) ) {
						$url->set($key, $value);
					}
				}
				return $url;
			}
		}
		
		return $url;
	}

	/**
	 * Magic __toString method that is called when the object is casted to a string
	 *
	 * @param none
	 * @return string
	 */
	public function __toString() {
		return $this->toString();
	}
	
	/**
	 * Returns an array of all URL properties
	 *
	 * @param none
	 * @return array parse_url 
	 */
	public function toArray() {
		return parse_url( $this->toString() );	
	}

	/**
	 * Formats the current URL object to a string
	 *
	 * @param none
	 * @return string
	 */
	public function toString() {
		$string = ( $this->get('scheme') ? $this->get('scheme') . '://' : '' ) . 
		( $this->get('user') ? $this->get('user') . ( $this->get('pass') ? ':' . $this->get('pass') : '' ) . '@' : '' ) . 
		$this->get('host') .
		( $this->get('port') ? ':' . $this->get('port') : '' ) . 
		$this->get('path') . 
		( $this->get('query') ? '?' . $this->get('query') : '' );
	
		return $string;
	}
	
	/**
	 * Validates the existence of the URL with cURL or file_get_contents()
	 *
	 * @param boolean $verify_ssl
	 * @return boolean
	 */
	public function isValid( $verify_ssl = false ) {
		if ( function_exists('curl_init') ) {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->toString());
			curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify_ssl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

			$content = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			if ( !$info['http_code'] || ( $info['http_code'] == 0 || $info['http_code'] == 404 ) ) {
				return false;
			} else {
				return true;
			}
		} else if ( @ini_get('allow_url_fopen') ) {
			if ( @file_get_contents($url) !== false ) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Compares the current URL object to another URL object.
	 *
	 * @param string | array $parts URL Parts to match
	 * @param WordPressHTTPS_Url $url
	 * @return boolean
	 */
	public function match( $parts = array(), WordPressHTTPS_Url $url ) {
		if ( ! is_array($parts) ) {
			$parts = array( $parts );
		}
		foreach( $parts as $part ) {
			if ( $this->get($part) != $url->get($part) ) {
				return false;
			}
		}
		return true;
	}

}