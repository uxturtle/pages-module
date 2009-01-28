<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Page Helper
 *
 * @package	   Pages Module
 * @author     Sam Soffes
 * @author     Josh Turmel
 */
class page_Core {

	/**
	 * Returns the value of a view ane merges the config with any data passed to it
	 *
	 * @param   string        name of view
	 * @param   boolean|array optional array of data to pass to the view
	 * @param   boolean|int   lifetime of cache. if set to true it will use the default
	 *                            cache from the pages config or use an int if it is passed one
	 * @return  string        contents of view or cache file
	 */
	public static function view($view, $config = false, $lifetime = false)
	{
		$page = Pages::instance();
	
		// Setup caching and return the cache file it it works
		if ($lifetime)
		{
			$cache = new Cache;
			$cache_name = $page->getCacheIdForView($view.serialize($data));

			if ($output = $cache->get($cache_name))
			{
				return $output;
			}
		}

		// Load the view
		$view = new View($view);
		$view->set($config);
		$output = $view->render();

		// Store into cache
		if ($lifetime)
		{
			// Setup lifetime
			if ($lifetime === true)
			{
				$lifetime = $page->cache_lifetime;

			} else {

				$lifetime = (int) $lifetime;
			}

			// Store the cache
			$cache->set($cache_name, $output, null, $lifetime);
		}

		return $output;
	}
	
	/**
	 * Returns compressed xml or html
	 *
	 * @param   string        input
	 * @return  string        compressed version
	 */
	public static function compress($input)
	{
		$input = str_replace(array("\r\n", "\r", "\n", "\t"), '', $input);
		$input = preg_replace(array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'), array('>', '<', '\\1'), $input);
		return $input;
	}
	
	public static function indent($input)
	{
		$xml = new DOMDocument;
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$xml->loadXML($input);
		$output = substr($xml->saveXML(), 22); // Strip the <?xml> at the beginning
		$output = str_replace('<![CDATA[', '//<![CDATA[', $output);
		return str_replace(']]>', '//]]>', $output);
	}
	
	/**
	 * Returns compressed css
	 *
	 * @param   string        buffer
	 * @return  string        compressed version
	 */
	function compressCSS($buffer)
	{
		$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
		$buffer = str_replace(array("\r\n", "\r", "\n", "\t", ' ', ' ', ' '), '', $buffer);
		return $buffer;
	}
	
	/**
	 * Returns packed js
	 *
	 * @param   string        script to pack
	 * @return  string        packed version
	 */
	function packJS($script)
	{
		$packer = new JavaScriptPacker($script);
		return $packer->pack();
	}
	
	/**
	 * Returns minified js
	 *
	 * @param   string        script to minify
	 * @return  string        minified version
	 */
	function minifyJS($script)
	{
		return JSMin::minify($script);
	}
}