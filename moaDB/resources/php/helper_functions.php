<?php

/**
 * Copyright (C) 2013 MoaDB
 * @license GPL v3
 */

/**
 * HTML helper tools
 */
class htmlHelper {

	/**
	 * Internal storage of the link-prefix and hypertext protocol values
	 * @var string
	 */
			protected $_linkPrefix, $_protocol;

	/**
	 * Internal list of included CSS & JS files used by $this->_tagBuilder() to assure that files are not included twice
	 * @var array
	 */
	protected $_includedFiles = array();

	/**
	 * Flag array to avoid defining singleton JavaScript & CSS snippets more than once
	 * @var array
	 */
			protected $_jsSingleton = array(), $_cssSingleton = array();

	/**
	 * Sets the protocol (http/https) - this is modified from the original Vork version for moaDB usage
	 */
	public function __construct() {
		$this->_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://');
	}

	/**
	 * Creates simple HTML wrappers, accessed via $this->__call()
	 *
	 * JS and CSS files are never included more than once even if requested twice. If DEBUG mode is enabled than the
	 * second request will be added to the debug log as a duplicate. The jsSingleton and cssSingleton methods operate
	 * the same as the js & css methods except that they will silently skip duplicate requests instead of logging them.
	 *
	 * jsInlineSingleton and cssInlineSingleton makes sure a JavaScript or CSS snippet will only be output once, even
	 * if echoed out multiple times and this method will attempt to place the JS code into the head section, if <head>
	 * has already been echoed out then it will return the JS code inline the same as jsInline. Eg.:
	 * $helloJs = "function helloWorld() {alert('Hello World');}";
	 * echo $html->jsInlineSingleton($helloJs);
	 *
	 * Adding an optional extra argument to jsInlineSingleton/cssInlineSingleton will return the inline code bare (plus
	 * a trailing linebreak) if it cannot place it into the head section, this is used for joint JS/CSS statements:
	 * echo $html->jsInline($html->jsInlineSingleton($helloJs, true) . 'helloWorld();');
	 *
	 * @param string $tagType
	 * @param array $args
	 * @return string
	 */
	protected function _tagBuilder($tagType, $args = array()) {
		$arg = current($args);
		if (empty($arg) || $arg === '') {
			$errorMsg = 'Missing argument for ' . __CLASS__ . '::' . $tagType . '()';
			trigger_error($errorMsg, E_USER_WARNING);
		}

		if (is_array($arg)) {
			foreach ($arg as $thisArg) {
				$return[] = $this->_tagBuilder($tagType, array($thisArg));
			}
			$return = implode(PHP_EOL, $return);
		} else {
			switch ($tagType) {
				case 'js':
				case 'jsSingleton':
				case 'css': //Optional extra argument to define CSS media type
				case 'cssSingleton':
				case 'jqueryTheme':
					if ($tagType == 'jqueryTheme') {
						$arg = $this->_protocol . 'ajax.googleapis.com/ajax/libs/jqueryui/1/themes/'
								. str_replace(' ', '-', strtolower($arg)) . '/jquery-ui.css';
						$tagType = 'css';
					}
					if (!isset($this->_includedFiles[$tagType][$arg])) {
						if ($tagType == 'css' || $tagType == 'cssSingleton') {
							$return = '<link rel="stylesheet" type="text/css" href="' . $arg . '"'
									. ' media="' . (isset($args[1]) ? $args[1] : 'all') . '" />';
						} else {
							$return = '<script type="text/javascript" src="' . $arg . '"></script>';
						}
						$this->_includedFiles[$tagType][$arg] = true;
					} else {
						$return = null;
						if (DEBUG_MODE && ($tagType == 'js' || $tagType == 'css')) {
							debug::log($arg . $tagType . ' file has already been included', 'warn');
						}
					}
					break;
				case 'cssInline': //Optional extra argument to define CSS media type
					$return = '<style type="text/css" media="' . (isset($args[1]) ? $args[1] : 'all') . '">'
							. PHP_EOL . '/*<![CDATA[*/'
							. PHP_EOL . '<!--'
							. PHP_EOL . $arg
							. PHP_EOL . '//-->'
							. PHP_EOL . '/*]]>*/'
							. PHP_EOL . '</style>';
					break;
				case 'jsInline':
					$return = '<script type="text/javascript">'
							. PHP_EOL . '//<![CDATA['
							. PHP_EOL . '<!--'
							. PHP_EOL . $arg
							. PHP_EOL . '//-->'
							. PHP_EOL . '//]]>'
							. PHP_EOL . '</script>';
					break;
				case 'jsInlineSingleton': //Optional extra argument to supress adding of inline JS/CSS wrapper
				case 'cssInlineSingleton':
					$tagTypeBase = substr($tagType, 0, -15);
					$return = null;
					$md5 = md5($arg);
					if (!isset($this->{'_' . $tagTypeBase . 'Singleton'}[$md5])) {
						$this->{'_' . $tagTypeBase . 'Singleton'}[$md5] = true;
						if (!$this->_bodyOpen) {
							$this->vorkHead[$tagTypeBase . 'Inline'][] = $arg;
						} else {
							$return = (!isset($args[1]) || !$args[1] ? $this->{$tagTypeBase . 'Inline'}($arg) : $arg . PHP_EOL);
						}
					}
					break;
				case 'div':
				case 'li':
				case 'p':
				case 'h1':
				case 'h2':
				case 'h3':
				case 'h4':
					$return = '<' . $tagType . '>' . $arg . '</' . $tagType . '>';
					break;
				default:
					$errorMsg = 'TagType ' . $tagType . ' not valid in ' . __CLASS__ . '::' . __METHOD__;
					throw new Exception($errorMsg);
					break;
			}
		}
		return $return;
	}

	/**
	 * Creates virtual wrapper methods via $this->_tagBuilder() for the simple wrapper functions including:
	 * $html->css, js, cssInline, jsInline, div, li, p and h1-h4
	 *
	 * @param string $method
	 * @param array $arg
	 * @return string
	 */
	public function __call($method, $args) {
		$validTags = array('css', 'js', 'cssSingleton', 'jsSingleton', 'jqueryTheme',
			'cssInline', 'jsInline', 'jsInlineSingleton', 'cssInlineSingleton',
			'div', 'li', 'p', 'h1', 'h2', 'h3', 'h4');
		if (in_array($method, $validTags)) {
			return $this->_tagBuilder($method, $args);
		} else {
			$errorMsg = 'Call to undefined method ' . __CLASS__ . '::' . $method . '()';
			trigger_error($errorMsg, E_USER_ERROR);
		}
	}

	/**
	 * Flag to make sure that header() can only be opened one-at-a-time and footer() can only be used after header()
	 * @var boolean
	 */
	private $_bodyOpen = false;

	/**
	 * Sets the default doctype to XHTML 1.1
	 * @var string
	 */
	protected $_docType = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';

	/**
	 * Allows modification of the docType
	 *
	 * Can either set to an actual doctype definition or to one of the presets (case-insensitive):
	 * XHTML Mobile 1.2
	 * XHTML Mobile 1.1
	 * XHTML Mobile 1.0
	 * Mobile 1.2 (alias for XHTML Mobile 1.2)
	 * Mobile 1.1 (alias for XHTML Mobile 1.1)
	 * Mobile 1.0 (alias for XHTML Mobile 1.0)
	 * Mobile (alias for the most-strict Mobile DTD, currently 1.2)
	 * XHTML 1.1 (this is the default DTD, there is no need to apply this method for an XHTML 1.1 doctype)
	 * XHTML (Alias for XHTML 1.1)
	 * XHTML 1.0 Strict
	 * XHTML 1.0 Transitional
	 * XHTML 1.0 Frameset
	 * XHTML 1.0 (Alias for XHTML 1.0 Strict)
	 * HTML 5
	 * HTML 4.01
	 * HTML (Alias for HTML 4.01)
	 *
	 * @param string $docType
	 */
	public function setDocType($docType) {
		$docType = str_replace(' ', '', strtolower($docType));
		if ($docType == 'xhtml1.1' || $docType == 'xhtml') {
			return; //XHTML 1.1 is the default
		} else if ($docType == 'xhtml1.0') {
			$docType = 'strict';
		}
		$docType = str_replace(array('xhtml mobile', 'xhtml1.0'), array('mobile', ''), $docType);
		$docTypes = array(
			'mobile1.2' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" '
			. '"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">',
			'mobile1.1' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.1//EN '
			. '"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile11.dtd">',
			'mobile1.0' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" '
			. '"http://www.wapforum.org/DTD/xhtml-mobile10.dtd">',
			'strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
			'transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
			'frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" '
			. '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
			'html4.01' => '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" '
			. '"http://www.w3.org/TR/html4/strict.dtd">',
			'html5' => '<!DOCTYPE html>'
		);
		$docTypes['mobile'] = $docTypes['mobile1.2'];
		$docTypes['html'] = $docTypes['html4.01'];
		$this->_docType = (isset($docTypes[$docType]) ? $docTypes[$docType] : $docType);
	}

	/**
	 * Array used internally by Vork to cache JavaScript and CSS snippets and place them in the head section
	 * Changing the contents of this property may cause Vork components to be rendered incorrectly.
	 * @var array
	 */
	public $vorkHead = array();

	/**
	 * Returns an HTML header and opens the body container
	 * This method will trigger an error if executed more than once without first calling
	 * the footer() method on the prior usage
	 * This is meant to be utilized within layouts, not views (but will work in either)
	 *
	 * @param array $args
	 * @return string
	 */
	public function header(array $args) {
		if (!$this->_bodyOpen) {
			$this->_bodyOpen = true;
			extract($args);
			$return = $this->_docType
					. PHP_EOL . '<html xmlns="http://www.w3.org/1999/xhtml">'
					. PHP_EOL . '<head>'
					. PHP_EOL . '<title>' . $title . '</title>';

			if (!isset($metaheader['Content-Type'])) {
				$metaheader['Content-Type'] = 'text/html; charset=utf-8';
			}
			foreach ($metaheader as $name => $content) {
				$return .= PHP_EOL . '<meta http-equiv="' . $name . '" content="' . $content . '" />';
			}

//            $meta['generator'] = 'Forked Vork 2.00';
//            foreach ($meta as $name => $content) {
//                $return .= PHP_EOL . '<meta name="' . $name . '" content="' . $content . '" />';
//            }

			if (isset($favicon)) {
				$return .= PHP_EOL . '<link rel="shortcut icon" href="' . $favicon . '" type="image/x-icon" />';
			}
			if (isset($animatedFavicon)) {
				$return .= PHP_EOL . '<link rel="icon" href="' . $animatedFavicon . '" type="image/gif" />';
			}

			$containers = array('css', 'cssInline', 'js', 'jsInline', 'jqueryTheme');
			foreach ($containers as $container) {
				if (isset($$container)) {
					$return .= PHP_EOL . $this->$container($$container);
				}
			}

			if (isset($head)) {
				$return .= PHP_EOL . (is_array($head) ? implode(PHP_EOL, $head) : $head);
			}

			$return .= PHP_EOL . '</head>' . PHP_EOL . '<body>';

			//Deprecation : GoChat tracking.
			//. $this->js('https://GoChat.us/chat.js#identity=5047dd509c3a8dd8fec07b5b&appid=phpmoadmin.com');

			return $return;
		} else {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - the header has already been returned';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Returns an HTML footer and optional Google Analytics
	 * This method will trigger an error if executed without first calling the header() method
	 * This is meant to be utilized within layouts, not views (but will work in either)
	 *
	 * @param array $args
	 * @return string
	 */
	public function footer(array $args = array()) {
		if ($this->_bodyOpen) {
			$this->_bodyOpen = false;
			return '</body></html>';
		} else {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - header() has not been called';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Establishes a basic set of JavaScript tools, just echo $html->jsTools() before any JavaScript code that
	 * will use the tools.
	 *
	 * This method will only operate from the first occurrence in your code, subsequent calls will not output anything
	 * but you should add it anyway as it will make sure that your code continues to work if you later remove a
	 * previous call to jsTools.
	 *
	 * Tools provided:
	 *
	 * dom() method is a direct replacement for document.getElementById() that works in all JS-capable
	 * browsers Y2k and newer.
	 *
	 * vork object - defines a global vork storage space; use by appending your own properties, eg.: vork.widgetCount
	 *
	 * @param Boolean $noJsWrapper set to True if calling from within a $html->jsInline() wrapper
	 * @return string
	 */
	public function jsTools($noJsWrapper = false) {
		return $this->jsInlineSingleton("var vork = function() {}
var dom = function(id) {
    if (typeof document.getElementById != 'undefined') {
        dom = function(id) {return document.getElementById(id);}
    } else if (typeof document.all != 'undefined') {
        dom = function(id) {return document.all[id];}
    } else {
        return false;
    }
    return dom(id);
}", $noJsWrapper);
	}

	/**
	 * Load a JavaScript library via Google's AJAX API
	 * http://code.google.com/apis/ajaxlibs/documentation/
	 *
	 * Version is optional and can be exact (1.8.2) or just version-major (1 or 1.8)
	 *
	 * Usage:
	 * echo $html->jsLoad('jquery');
	 * echo $html->jsLoad(array('yui', 'mootools'));
	 * echo $html->jsLoad(array('yui' => 2.7, 'jquery', 'dojo' => '1.3.1', 'scriptaculous'));
	 *
	 * //You can also use the Google API format JSON-decoded in which case version is required & name must be lowercase
	 * $jsLibs = array(array('name' => 'mootools', 'version' => 1.2, 'base_domain' => 'ditu.google.cn'), array(...));
	 * echo $html->jsLoad($jsLibs);
	 *
	 * @param mixed $library Can be a string, array(str1, str2...) or , array(name1 => version1, name2 => version2...)
	 *                       or JSON-decoded Google API syntax array(array('name' => 'yui', 'version' => 2), array(...))
	 * @param mixed $version Optional, int or str, this is only used if $library is a string
	 * @param array $options Optional, passed to Google "optionalSettings" argument, only used if $library == str
	 * @return str
	 */
	public function jsLoad($library, $version = null, array $options = array()) {
		$versionDefaults = array('swfobject' => 2, 'yui' => 2, 'ext-core' => 3, 'mootools' => 1.2);
		if (!is_array($library)) { //jsLoad('yui')
			$library = strtolower($library);
			if (!$version) {
				$version = (!isset($versionDefaults[$library]) ? 1 : $versionDefaults[$library]);
			}
			$library = array('name' => $library, 'version' => $version);
			$library = array(!$options ? $library : array_merge($library, $options));
		} else {
			foreach ($library as $key => $val) {
				if (!is_array($val)) {
					if (is_int($key)) { //jsLoad(array('yui', 'prototype'))
						$val = strtolower($val);
						$version = (!isset($versionDefaults[$val]) ? 1 : $versionDefaults[$val]);
						$library[$key] = array('name' => $val, 'version' => $version);
					} else if (!is_array($val)) { // //jsLoad(array('yui' => '2.8.0r4', 'prototype' => 1.6))
						$library[$key] = array('name' => strtolower($key), 'version' => $val);
					}
				}
			}
		}
		$url = $this->_protocol . 'www.google.com/jsapi';
		if (!isset($this->_includedFiles['js'][$url])) { //autoload library
			$this->_includedFiles['js'][$url] = true;
			$url .= '?autoload=' . urlencode(json_encode(array('modules' => array_values($library))));
			$return = $this->js($url);
		} else { //load inline
			foreach ($library as $lib) {
				$js = 'google.load("' . $lib['name'] . '", "' . $lib['version'] . '"';
				if (count($lib) > 2) {
					unset($lib['name'], $lib['version']);
					$js .= ', ' . json_encode($lib);
				}
				$jsLoads[] = $js . ');';
			}
			$return = $this->jsInline(implode(PHP_EOL, $jsLoads));
		}
		return $return;
	}

	/**
	 * Takes an array of key-value pairs and formats them in the syntax of HTML-container properties
	 *
	 * @param array $properties
	 * @return string
	 */
	public static function formatProperties(array $properties) {
		$return = array();
		foreach ($properties as $name => $value) {
			$return[] = $name . '="' . get::htmlentities($value) . '"';
		}
		return implode(' ', $return);
	}

	/**
	 * Creates an anchor or link container
	 *
	 * @param array $args
	 * @return string
	 */
	public function anchor(array $args) {
		if (!isset($args['text']) && isset($args['href'])) {
			$args['text'] = $args['href'];
		}
		if (!isset($args['title']) && isset($args['text'])) {
			$args['title'] = str_replace(array("\n", "\r"), ' ', strip_tags($args['text']));
		}
		$return = '';
		if (isset($args['ajaxload'])) {
			$return = $this->jsSingleton('/js/ajax.js');
			$onclick = "return ajax.load('" . $args['ajaxload'] . "', this.href);";
			$args['onclick'] = (!isset($args['onclick']) ? $onclick : $args['onclick'] . '; ' . $onclick);
			unset($args['ajaxload']);
		}
		$text = (isset($args['text']) ? $args['text'] : null);
		unset($args['text']);
		return $return . '<a ' . self::formatProperties($args) . '>' . $text . '</a>';
	}

	/**
	 * Shortcut to access the anchor method
	 *
	 * @param str $href
	 * @param str $text
	 * @param array $args
	 * @return str
	 */
	public function link($href, $text = null, array $args = array()) {
		if ($href && strpos($href, 'http') !== 0) {
			$href = $this->_linkPrefix . $href;
		}
		if ($href !== null)
			$args['href'] = $href;
		if ($text !== null) {
			$args['text'] = $text;
		}
		return $this->anchor($args);
	}

	/**
	 * Wrapper display computer-code samples
	 *
	 * @param str $str
	 * @return str
	 */
	public function code($str) {
		return '<code>' . str_replace('  ', '&nbsp;&nbsp;', nl2br(get::htmlentities($str))) . '</code>';
	}

	/**
	 * Will return true if the number passed in is even, false if odd.
	 *
	 * @param int $number
	 * @return boolean
	 */
	public function isEven($number) {
		return (Boolean) ($number % 2 == 0);
	}

	/**
	 * Will return number friendly format.
	 * by james at bandit.co.nz
	 * @param int $n
	 * @return string
	 */
	public function bd_nice_number($n) {
		// first strip any formatting;
		$n = (0 + str_replace(",", "", $n));

		// is this a number?
		if (!is_numeric($n))
			return false;

		// now filter it;
		if ($n > 1000000000000)
			return round(($n / 1000000000000), 2) . 't';
		else if ($n > 1000000000)
			return round(($n / 1000000000), 2) . 'b';
		else if ($n > 1000000)
			return round(($n / 1000000), 2) . 'm';
		else if ($n > 1000)
			return round(($n / 1000), 2) . 'k';

		return number_format($n);
	}

	/**
	 * Internal incrementing integar for the alternator() method
	 * @var int
	 */
	private $alternator = 1;

	/**
	 * Returns an alternating Boolean, useful to generate alternating background colors
	 * Eg.:
	 * $colors = array(true => 'gray', false => 'white');
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //gray background
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //white background
	 * echo '<div style="background: ' . $colors[$html->alternator()] . ';">...</div>'; //gray background
	 *
	 * @return Boolean
	 */
	public function alternator() {
		return $this->isEven(++$this->alternator);
	}

	/**
	 * Returns a list of notifications if there are any - similar to the Flash feature of Ruby on Rails
	 *
	 * @param mixed $messages String or an array of strings
	 * @param string $class
	 * @return string Returns null if there are no notifications to return
	 */
	public function getNotifications($messages, $class = 'errormessage') {
		if (isset($messages) && $messages) {
			return '<div class="' . $class . '">'
					. (is_array($messages) ? implode('<br />', $messages) : $messages) . '</div>';
		}
	}

}

/**
 * Form-helper
 */
class formHelper {

	/**
	 * Internal flag to keep track if a form tag has been opened and not yet closed
	 * @var boolean
	 */
	private $_formOpen = false;

	/**
	 * Internal form element counter
	 * @var int
	 */
	private $_inputCounter = array();

	/**
	 * Converts dynamically-assigned array indecies to use an explicitely defined index
	 *
	 * @param string $name
	 * @return string
	 */
	protected function _indexDynamicArray($name) {
		$dynamicArrayStart = strpos($name, '[]');
		if ($dynamicArrayStart) {
			$prefix = substr($name, 0, $dynamicArrayStart);
			if (!isset($this->_inputCounter[$prefix])) {
				$this->_inputCounter[$prefix] = -1;
			}
			$name = $prefix . '[' . ++$this->_inputCounter[$prefix] . substr($name, ($dynamicArrayStart + 1));
		}
		return $name;
	}

	/**
	 * Form types that do not change value with user input
	 * @var array
	 */
	protected $_staticTypes = array('hidden', 'submit', 'button', 'image');

	/**
	 * Sets the standard properties available to all input elements in addition to user-defined properties
	 * Standard properties are: name, value, class, style, id
	 *
	 * @param array $args
	 * @param array $propertyNames Optional, an array of user-defined properties
	 * @return array
	 */
	protected function _getProperties(array $args, array $propertyNames = array()) {
		$method = (isset($this->_formOpen['method']) && $this->_formOpen['method'] == 'get' ? $_GET : $_POST);
		if (isset($args['name']) && (!isset($args['type']) || !in_array($args['type'], $this->_staticTypes))) {
			$arrayStart = strpos($args['name'], '[');
			if (!$arrayStart) {
				if (isset($method[$args['name']])) {
					$args['value'] = $method[$args['name']];
				}
			} else {
				$name = $this->_indexDynamicArray($args['name']);
				if (preg_match_all('/\[(.*)\]/', $name, $arrayIndex)) {
					array_shift($arrayIndex); //dump the 0 index element containing full match string
				}
				$name = substr($name, 0, $arrayStart);
				if (isset($method[$name])) {
					$args['value'] = $method[$name];
					if (!isset($args['type']) || $args['type'] != 'checkbox') {
						foreach ($arrayIndex as $idx) {
							if (isset($args['value'][current($idx)])) {
								$args['value'] = $args['value'][current($idx)];
							} else {
								unset($args['value']);
								break;
							}
						}
					}
				}
			}
		}
		$return = array();
		$validProperties = array_merge($propertyNames, array('name', 'value', 'class', 'style', 'id'));
		foreach ($validProperties as $propertyName) {
			if (isset($args[$propertyName])) {
				$return[$propertyName] = $args[$propertyName];
			}
		}
		return $return;
	}

	/**
	 * Begins a form
	 * Includes a safety mechanism to prevent re-opening an already-open form
	 *
	 * @param array $args
	 * @return string
	 */
	public function open(array $args = array()) {
		if (!$this->_formOpen) {
			if (!isset($args['method'])) {
				$args['method'] = 'post';
			}

			$this->_formOpen = array('id' => (isset($args['id']) ? $args['id'] : true),
				'method' => $args['method']);

			if (!isset($args['action'])) {
				$args['action'] = $_SERVER['REQUEST_URI'];
			}
			if (isset($args['upload']) && $args['upload'] && !isset($args['enctype'])) {
				$args['enctype'] = 'multipart/form-data';
			}
			if (isset($args['legend'])) {
				$legend = $args['legend'];
				unset($args['legend']);
				if (!isset($args['title'])) {
					$args['title'] = $legend;
				}
			} else if (isset($args['title'])) {
				$legend = $args['title'];
			}
			if (isset($args['alert'])) {
				if ($args['alert']) {
					$alert = (is_array($args['alert']) ? implode('<br />', $args['alert']) : $args['alert']);
				}
				unset($args['alert']);
			}
			$return = '<form ' . htmlHelper::formatProperties($args) . '>' . PHP_EOL . '<fieldset>' . PHP_EOL;
			if (isset($legend)) {
				$return .= '<legend>' . $legend . '</legend>' . PHP_EOL;
			}
			if (isset($alert)) {
				$return .= $this->getErrorMessageContainer((isset($args['id']) ? $args['id'] : 'form'), $alert);
			}
			return $return;
		} else if (DEBUG_MODE) {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - a form is already open';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Closes a form if one is open
	 *
	 * @return string
	 */
	public function close() {
		if ($this->_formOpen) {
			$this->_formOpen = false;
			return '</fieldset></form>';
		} else if (DEBUG_MODE) {
			$errorMsg = 'Invalid usage of ' . __METHOD__ . '() - there is no open form to close';
			trigger_error($errorMsg, E_USER_NOTICE);
		}
	}

	/**
	 * Adds label tags to a form element
	 *
	 * @param array $args
	 * @param str $formElement
	 * @return str
	 */
	protected function _getLabel(array $args, $formElement) {
		if (!isset($args['label']) && isset($args['name'])
				&& (!isset($args['type']) || !in_array($args['type'], $this->_staticTypes))) {
			$args['label'] = ucfirst($args['name']);
		}

		if (isset($args['label'])) {
			$label = get::xhtmlentities($args['label']);
			if (isset($_POST['errors']) && isset($args['name']) && isset($_POST['errors'][$args['name']])) {
				$label .= ' ' . $this->getErrorMessageContainer($args['name'], $_POST['errors'][$args['name']]);
			}
			$labelFirst = (!isset($args['labelFirst']) || $args['labelFirst']);
			if (isset($args['id'])) {
				$label = '<label for="' . $args['id'] . '" id="' . $args['id'] . 'label">'
						. $label . '</label>';
			}
			if (isset($args['addBreak']) && $args['addBreak']) {
				$label = ($labelFirst ? $label . '<br />' : '<br />' . $label);
			}
			$formElement = ($labelFirst ? $label . $formElement : $formElement . $label);
			if (!isset($args['id'])) {
				$formElement = '<label>' . $formElement . '</label>';
			}
		}
		return $formElement;
	}

	/**
	 * Returns a standardized container to wrap an error message
	 *
	 * @param string $id
	 * @param string $errorMessage Optional, you may want to leave this blank and populate dynamically via JavaScript
	 * @return string
	 */
	public function getErrorMessageContainer($id, $errorMessage = '') {
		return '<span class="errormessage" id="' . $id . 'errorwrapper">'
				. get::htmlentities($errorMessage) . '</span>';
	}

	/**
	 * Used for text, textarea, hidden, password, file, button, image and submit
	 *
	 * Valid args are any properties valid within an HTML input as well as label
	 *
	 * @param array $args
	 * @return string
	 */
	public function input(array $args) {
		$args['type'] = (isset($args['type']) ? $args['type'] : 'text');

		switch ($args['type']) {
			case 'select':
				return $this->select($args);
				break;
			case 'checkbox':
				return $this->checkboxes($args);
				break;
			case 'radio':
				return $this->radios($args);
				break;
		}

		if ($args['type'] == 'textarea' && isset($args['maxlength'])) {
			if (!isset($args['id']) && isset($args['name'])) {
				$args['id'] = $args['name'];
			}
			if (isset($args['id'])) {
				$maxlength = $args['maxlength'];
			}
			unset($args['maxlength']);
		}

		if ($args['type'] == 'submit' && !isset($args['class'])) {
			$args['class'] = $args['type'];
		}

		$takeFocus = (isset($args['focus']) && $args['focus'] && $args['type'] != 'hidden');
		if ($takeFocus && !isset($args['id'])) {
			if (isset($args['name'])) {
				$args['id'] = $args['name'];
			} else {
				$takeFocus = false;
				if (DEBUG_MODE) {
					$errorMsg = 'Either name or id is required to use the focus option on a form input';
					trigger_error($errorMsg, E_USER_NOTICE);
				}
			}
		}

		$properties = $this->_getProperties($args, array('type', 'maxlength'));

		if ($args['type'] == 'image') {
			$properties['src'] = $args['src'];
			$properties['alt'] = (isset($args['alt']) ? $args['alt'] : '');
			$optionalProperties = array('height', 'width');
			foreach ($optionalProperties as $optionalProperty) {
				if (isset($args[$optionalProperty])) {
					$properties[$optionalProperty] = $args[$optionalProperty];
				}
			}
		}

		if ($args['type'] != 'textarea') {
			$return[] = '<input ' . htmlHelper::formatProperties($properties) . ' />';
		} else {
			unset($properties['type']);
			if (isset($properties['value'])) {
				$value = $properties['value'];
				unset($properties['value']);
			}
			if (isset($args['preview']) && $args['preview'] && !isset($properties['id'])) {
				$properties['id'] = 'textarea_' . rand(100, 999);
			}
			$properties['rows'] = (isset($args['rows']) ? $args['rows'] : 13);
			$properties['cols'] = (isset($args['cols']) ? $args['cols'] : 55);
			$return[] = '<textarea ' . htmlHelper::formatProperties($properties);
			if (isset($maxlength)) {
				$return[] = ' onkeyup="document.getElementById(\''
						. $properties['id'] . 'errorwrapper\').innerHTML = (this.value.length > '
						. $maxlength . ' ? \'Form content exceeds maximum length of '
						. $maxlength . ' characters\' : \'Length: \' + this.value.length + \' (maximum: '
						. $maxlength . ' characters)\')"';
			}
			$return[] = '>';
			if (isset($value)) {
				$return[] = get::htmlentities($value, null, null, true); //double-encode allowed
			}
			$return[] = '</textarea>';
			if (isset($maxlength) && (!isset($args['validatedInput']) || !$args['validatedInput'])) {
				$return[] = $this->getErrorMessageContainer($properties['id']);
			}
		}
		if (!isset($args['addBreak'])) {
			$args['addBreak'] = true;
		}
		if ($takeFocus) {
			$html = get::helper('html');
			$return[] = $html->jsInline($html->jsTools(true) . 'dom("' . $args['id'] . '").focus();');
		}
		if (isset($args['preview']) && $args['preview']) {
			$js = 'document.writeln(\'<div class="htmlpreviewlabel">'
					. '<label for="livepreview_' . $properties['id'] . '">Preview:</label></div>'
					. '<div id="livepreview_' . $properties['id'] . '" class="htmlpreview"></div>\');' . PHP_EOL
					. 'if (dom("livepreview_' . $properties['id'] . '")) {' . PHP_EOL
					. '    var updateLivePreview_' . $properties['id'] . ' = '
					. 'function() {dom("livepreview_' . $properties['id'] . '").innerHTML = '
					. 'dom("' . $properties['id'] . '").value};' . PHP_EOL
					. '    dom("' . $properties['id'] . '").onkeyup = updateLivePreview_' . $properties['id'] . ';'
					. ' updateLivePreview_' . $properties['id'] . '();' . PHP_EOL
					. '}';
			if (!isset($html)) {
				$html = get::helper('html');
			}
			$return[] = $html->jsInline($html->jsTools(true) . $js);
		}
		return $this->_getLabel($args, implode($return));
	}

	/**
	 * Returns a form select element
	 *
	 * $args = array(
	 * 'name' => '',
	 * 'multiple' => true,
	 * 'leadingOptions' => array(),
	 * 'optgroups' => array('group 1' => array('label' => 'g1o1', 'value' => 'grp 1 opt 1'),
	 *                      'group 2' => array(),),
	 * 'options' => array('value1' => 'text1', 'value2' => 'text2', 'value3' => 'text3'),
	 * 'value' => array('value2', 'value3') //if (multiple==false) 'value' => (str) 'value3'
	 * );
	 *
	 * @param array $args
	 * @return str
	 */
	public function select(array $args) {
		if (!isset($args['id'])) {
			$args['id'] = $args['name'];
		}
		if (isset($args['multiple']) && $args['multiple']) {
			$args['multiple'] = 'multiple';
			if (substr($args['name'], -2) != '[]') {
				$args['name'] .= '[]';
			}
		}
		$properties = $this->_getProperties($args, array('multiple'));
		$values = (isset($properties['value']) ? $properties['value'] : null);
		unset($properties['value']);
		if (!is_array($values)) {
			$values = ($values != '' ? array($values) : array());
		}
		$return = '<select ' . htmlHelper::formatProperties($properties) . '>';
		if (isset($args['prependBlank']) && $args['prependBlank']) {
			$return .= '<option value=""></option>';
		}

		if (isset($args['leadingOptions'])) {
			$useValues = (key($args['leadingOptions']) !== 0
					|| (isset($args['useValue']) && $args['useValue']));
			foreach ($args['leadingOptions'] as $value => $text) {
				if (!$useValues) {
					$value = $text;
				}
				$return .= '<option value="' . get::htmlentities($value) . '"';
				if (in_array((string) $value, $values)) {
					$return .= ' selected="selected"';
				}
				$return .= '>' . get::htmlentities($text) . '</option>';
			}
		}

		if (isset($args['optgroups'])) {
			foreach ($args['optgroups'] as $groupLabel => $optgroup) {
				$return .= '<optgroup label="' . get::htmlentities($groupLabel) . '">';
				foreach ($optgroup as $value => $label) {
					$return .= '<option value="' . get::htmlentities($value) . '"';
					if (isset($label)) {
						$return .= ' label="' . get::htmlentities($label) . '"';
					}
					if (in_array((string) $value, $values)) {
						$return .= ' selected="selected"';
					}
					$return .= '>' . get::htmlentities($label) . '</option>';
				}
				$return .= '</optgroup>';
			}
		}

		if (isset($args['options'])) {
			$useValues = (key($args['options']) !== 0 || (isset($args['useValue']) && $args['useValue']));
			foreach ($args['options'] as $value => $text) {
				if (!$useValues) {
					$value = $text;
				}
				$return .= '<option value="' . get::htmlentities($value) . '"';
				if (in_array((string) $value, $values)) {
					$return .= ' selected="selected"';
				}
				$return .= '>' . get::htmlentities($text) . '</option>';
			}
		}
		$return .= '</select>';
		if (!isset($args['addBreak'])) {
			$args['addBreak'] = true;
		}
		$return = $this->_getLabel($args, $return);
		if (isset($args['error'])) {
			$return .= $this->getErrorMessageContainer($args['id'], '<br />' . $args['error']);
		}
		return $return;
	}

	/**
	 * Cache containing individual radio or checkbox elements in an array
	 * @var array
	 */
			public $radios = array(), $checkboxes = array();

	/**
	 * Returns a set of radio form elements
	 *
	 * array(
	 * 'name' => '',
	 * 'value' => '',
	 * 'id' => '',
	 * 'legend' => '',
	 * 'options' => array('value1' => 'text1', 'value2' => 'text2', 'value3' => 'text3'),
	 * 'options' => array('text1', 'text2', 'text3'), //also acceptable (cannot do half this, half above syntax)
	 * )
	 *
	 * @param array $args
	 * @return str
	 */
	public function radios(array $args) {
		$id = (isset($args['id']) ? $args['id'] : $args['name']);
		$properties = $this->_getProperties($args);
		if (isset($properties['value'])) {
			$checked = $properties['value'];
			unset($properties['value']);
		}
		$properties['type'] = (isset($args['type']) ? $args['type'] : 'radio');
		$useValues = (key($args['options']) !== 0 || (isset($args['useValue']) && $args['useValue']));
		foreach ($args['options'] as $value => $text) {
			if (!$useValues) {
				$value = $text;
			}
			$properties['id'] = $id . '_' . preg_replace('/\W/', '', $value);
			$properties['value'] = $value;
			if (isset($checked) &&
					((($properties['type'] == 'radio' || !is_array($checked)) && $value == $checked)
					|| ($properties['type'] == 'checkbox' && is_array($checked) && in_array((string) $value, $checked)))) {
				$properties['checked'] = 'checked';
				$rowClass = (!isset($properties['class']) ? 'checked' : $properties['class'] . ' checked');
			}
			$labelFirst = (isset($args['labelFirst']) ? $args['labelFirst'] : false);
			$labelArgs = array('label' => $text, 'id' => $properties['id'], 'labelFirst' => $labelFirst);
			$input = '<input ' . htmlHelper::formatProperties($properties) . ' />';
			$row = $this->_getLabel($labelArgs, $input);
			if (isset($rowClass)) {
				$row = '<span class="' . $rowClass . '">' . $row . '</span>';
			}
			$radios[] = $row;
			unset($properties['checked'], $rowClass);
		}
		$this->{$properties['type'] == 'radio' ? 'radios' : 'checkboxes'} = $radios;
		$break = (!isset($args['optionBreak']) ? '<br />' : $args['optionBreak']);
		$addFieldset = (isset($args['addFieldset']) ? $args['addFieldset'] : ((isset($args['label']) && $args['label']) || count($args['options']) > 1));
		if ($addFieldset) {
			$return = '<fieldset id="' . $id . '">';
			if (isset($args['label'])) {
				$return .= '<legend>' . get::htmlentities($args['label']) . '</legend>';
			}
			$return .= implode($break, $radios) . '</fieldset>';
		} else {
			$return = implode($break, $radios);
		}
		if (isset($_POST['errors']) && isset($_POST['errors'][$id])) {
			$return = $this->getErrorMessageContainer($id, $_POST['errors'][$id]) . $return;
		}
		return $return;
	}

	/**
	 * Returns a set of checkbox form elements
	 *
	 * This method essentially extends the radios method and uses an identical signature except
	 * that $args['value'] can also accept an array of values to be checked.
	 *
	 * @param array $args
	 * @return str
	 */
	public function checkboxes(array $args) {
		$args['type'] = 'checkbox';
		if (isset($args['value']) && !is_array($args['value'])) {
			$args['value'] = array($args['value']);
		}
		$nameParts = explode('[', $args['name']);
		if (!isset($args['id'])) {
			$args['id'] = $nameParts[0];
		}
		if (!isset($nameParts[1]) && count($args['options']) > 1) {
			$args['name'] .= '[]';
		}
		return $this->radios($args);
	}

	/**
	 * Opens up shorthand usage of form elements like $form->file() and $form->submit()
	 *
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	public function __call($name, array $args) {
		$inputShorthand = array('text', 'textarea', 'password', 'file', 'hidden', 'submit', 'button', 'image');
		if (in_array($name, $inputShorthand)) {
			$args[0]['type'] = $name;
			return $this->input($args[0]);
		}
		trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
	}

}

class jsonHelper {

	/**
	 * Outputs content in JSON format
	 * @param mixed $content Can be a JSON string or an array of any data that will automatically be converted to JSON
	 * @param string $filename Default filename within the user-prompt for saving the JSON file
	 */
	public function echoJson($content, $filename = null) {
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 01 Jan 2000 01:00:00 GMT');
		header('Content-type: application/json');
		if ($filename) {
			header('Content-Disposition: attachment; filename=' . $filename);
		}
		echo (!is_array($content) && !is_object($content) ? $content : json_encode($content));
	}

}
