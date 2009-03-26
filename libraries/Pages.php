<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Pages Library
 *
 * @package	   Pages Module
 * @author     Sam Soffes
 * @author     Josh Turmel
 */
class Pages_Core {

	protected $html;
	protected $head;
	
	// Title
	protected $title        = array();
	protected $title_rev    = FALSE;
	protected $title_sep;
	
	// Head pieces
	protected $meta         = array();
	protected $link         = array();
	protected $css          = array();
	protected $js           = array();
	protected $raw_js       = array();
	public    $css_url;
	public    $js_url;
	
	// Content pieces
	public $template;

	// Misc variables
	protected $format_output;
	protected $eol           = "\r\n"; // Windows compatible line breaking
	protected $version; // Version number to append to end of JS and CSS files to combat caching
	
	// Cache Externals	
	protected $cache_css_exists = FALSE;
	protected $cache_js_exists  = FALSE;
	protected $cache_css_key;
	protected $cache_js_key;

	protected $cache_css = array
	(
		'data' => '',
		'file' => ''
	);
	protected $cache_js = array
	(
		'data' => '',
		'file' => ''
	);
	
	protected $css_cache_list = array();
	protected $js_cache_list  = array();

	// Cache Externals Temporary Containers
	protected $cache_container_css = '';
	protected $cache_container_js  = '';
	
	/**
	 * Returns a singleton instance of URI.
	 *
	 * @return  object
	 */
	public static function instance()
	{
		static $instance;

		if ($instance == NULL)
		{
			// Initialize the URI instance
			$instance = new Pages;
		}

		return $instance;
	}

	public function __construct()
	{					
		// Setup <head> variables from config file
		$this->title[]         = Kohana::config('pages.title');
		$this->title_sep       = Kohana::config('pages.title_seperator');
		$this->css_url         = Kohana::config('pages.css_url');
		$this->js_url          = Kohana::config('pages.js_url');
		$this->template        = Kohana::config('pages.template');
		$this->cache_lifetime  = Kohana::config('pages.cache_lifetime');	
		$this->format_output   = Kohana::config('pages.format_output');

		// Make sure that urls have a trailing slash
		$this->css_url = ((substr($this->css_url, -1) != '/') ? $this->css_url.'/' : $this->css_url);
		$this->js_url = ((substr($this->js_url, -1) != '/') ? $this->js_url.'/' : $this->js_url);
		
		$this->css_path = Kohana::config('pages.css_path');
		$this->js_path  = Kohana::config('pages.js_path');
		
		// Setup misc switches and vars
		$this->cache_externals = Kohana::config('pages.cache_externals');
		$this->version = (Kohana::config('pages.version') ? '?'.Kohana::config('pages.version') : '');
	}

	public function addTitle($title)
	{
		$this->title[] = $title;
	}
	
	public function clearTitle()
	{
		$this->title = array();
	}

	public function reverseTitle()
	{
		$this->title_rev = TRUE;
	}

	public function setTitleSep($sep)
	{
		$this->title_sep = $sep;
	}
	
	public function getCacheIdForView($name)
	{
		return 'page_view_'.(($this->lang) ? $this->lang.'_' : '').md5(url::current().$name);
	}
		
	public function addLink($rel, $href, $type = FALSE, $title = FALSE) {
		// By using href as key, it allows us to override in extended controllers.
		$this->link[md5($rel.$href.$type.$title)] = '<link rel="'.$rel.'" href="'.$href.'" '.($title !== FALSE ? 'title="'.$title.'" ' : '').($type !== FALSE ? 'type="'.$type.'" ' : '').'/>';
	}
	
	public function addOpenSearch($href, $title = FALSE)
	{
		$this->addLink('search', 'application/opensearchdescription+xml', $href, $title);
	}
	
	public function addRSS($href, $title = FALSE)
	{
		$this->addLink('alternate', 'application/rss+xml', $href, $title);
	}
	
	public function addMeta($name, $content)
	{
		$this->meta[$name] = '<meta name="'.$name.'" content="'.$content.'" />';
	}
	
	/**
	 * Protected method to add a script (css or js) to the head
	 *
	 * @throws  Kohana_Exception
	 * @param   string        type ('css' or 'js')
	 * @param   string        filename
	 * @param   boolean       embed the script or link to it
	 * @return  void
	 */
	protected function addScript($type, $file, $cache = null)
	{
		// Set cache defult
		if ($cache === null && Kohana::config('pages.cache_externals') === TRUE)
		{
			$cache_list = $type.'_cache_list';
			$list = &$this->$cache_list;
			$list[$file] = TRUE;

			$cache = TRUE;
		}
		elseif ($cache === null)
		{
			$cache = FALSE;
		}
	
		$url = $type.'_url';
		$scripts = &$this->$type;
		
		if (strpos($file, '://') !== FALSE)
		{
		    $scripts[$file]['file'] = $file;
		}
		elseif (strpos($this->$url, '://') >= 0)
		{
		    $scripts[$file]['file'] = $this->$url.$file.'.'.$type.$this->version;
		}
		else
		{
		    $scripts[$file]['file'] = url::site($this->$url.$file.'.'.$type.$this->version);
		}
		
		$scripts[$file]['cache'] = (bool) $cache;
	}
	
	public function addCSS($file, $cache = null)
	{
		$this->addScript('css', $file, $cache);
	}
	
	public function removeCSS($script)
	{
		unset($this->css[$script]);
	}
	
	public function removeAllCSS()
	{
		$this->css = array();
	}
	
	public function addJS($file, $cache = null)
	{
		$this->addScript('js', $file, $cache);
	}
	
	public function removeJS($script)
	{
		unset($this->js[$script]);
	}
	
	public function removeAllJS()
	{
		$this->js = array();
	}
	
	public function addRawJS($javascript)
	{	
		$this->raw_js[] = $javascript;
	}
	
	private function buildCSSAndJS()
	{
		// Make the eol a local variable for less typing
		$eol = $this->eol;
		
		if (Kohana::config('pages.cache_externals'))
		{
			$this->cache_css_key = '_pages_'.Kohana::config('pages.version').'_'.md5(implode('', array_keys($this->css_cache_list)));
			$this->cache_js_key  = '_pages_'.Kohana::config('pages.version').'_'.md5(implode('', array_keys($this->js_cache_list)));
			
			$this->cache_css_exists = $this->cacheExists('css', $this->cache_css_key);
			$this->cache_js_exists  = $this->cacheExists('js', $this->cache_js_key);
		}
		
		$head = '';

		// Add regular css
		foreach ($this->css as $css)
		{
			if ($css['cache'] === TRUE && $this->cache_css_exists === FALSE)
			{
				$this->fileCombine('css', $css['file']);
			}
			elseif ($css['cache'] === FALSE)
			{
				$head .= '<link rel="stylesheet" href="'.$css['file'].'" type="text/css" />'.$eol;
			}
		}

		// Add Cached CSS
		if ($this->cache_container_css != '')
		{
			if ($this->cache_css['data'] === '' || $this->cache_css['data'] === null)
			{
				$this->removeExpiredCache('css');
				
				$cache = $this->setCache('css', $this->cache_css_key, $this->cache_container_css);

				$head .= '<link rel="stylesheet" href="'.$this->css_url.$cache['filename'].'" type="text/css" />'.$eol;
			}
			else
			{
				$filename = str_replace(realpath(Kohana::config('pages.css_path')).'/', '', $this->cache_css['file']);
		
				$head .= '<link rel="stylesheet" href="'.$this->css_url.$filename.'" type="text/css" />'.$eol;
			}
		}
		elseif ($this->cache_css_exists === TRUE)
		{
			$head .= '<link rel="stylesheet" href="'.$this->css_url.$this->cache_css_key.'.css'.'" type="text/css" />'.$eol;
		}

		// Add regular js
		foreach ($this->js as $js)
		{
			if ($js['cache'] === TRUE && $this->cache_js_exists === FALSE)
			{
				$this->fileCombine('js', $js['file']);
			}
			elseif ($js['cache'] === FALSE)
			{
				$head .= '<script type="text/javascript" src="'.$js['file'].'"></script>'.$eol;
			}
		}
		
		// Add Cached JS
		if ($this->cache_container_js != '')
		{
			if ($this->cache_js['data'] === '' || $this->cache_js['data'] === null)
			{
				$this->removeExpiredCache('js');
				
				$cache = $this->setCache('js', $this->cache_js_key, $this->cache_container_js);
				
				$head .= '<script type="text/javascript" src="'.$this->js_url.$cache['filename'].'"></script>'.$eol;
			}
			else
			{
				$filename = str_replace(realpath(Kohana::config('pages.js_path')).'/', '', $this->cache_js['file']);
		
				$head .= '<script type="text/javascript" src="'.$this->js_url.$filename.'.js"></script>'.$eol;
			}
		}
		elseif ($this->cache_js_exists === TRUE)
		{
			$head .= '<script type="text/javascript" src="'.$this->js_url.$this->cache_js_key.'.js"></script>'.$eol;
		}

		// Add raw js
		if (count($this->raw_js) > 0)
		{
			$head .= '<script type="text/javascript">'.$eol.
			         implode($eol, $this->raw_js).$eol.
			         '</script>'.$eol;
		}

		return $head;
	}
	
	private function buildHead()
	{
		// Make the eol a local variable for less typing
		$eol = $this->eol;
		
		// Start with nothing
		$head = '';
		
		// Meta tags
		$head .= (count($this->meta)) ? implode($eol, $this->meta).$eol : '';

		// Link tags
		$head .= (count($this->link)) ? implode($eol, $this->link).$eol : '';
		
		// Reverse Title
		if ($this->title_rev)
		{
			$this->title = array_reverse($this->title);
		}
		
		// Title
		$head .= '<title>'.implode($this->title_sep, $this->title).'</title>'.$eol;
		
		// Build all of the CSS and JS
		$head .= $this->buildCSSAndJS();
				
		return $head;
	}
	
	public function display($content = FALSE, $config = array(), $type = FALSE) {
		
		// Make the eol a local variable for less typing
		$eol = $this->eol;
		
		// Construct the <head>
		$this->head = $this->buildHead();
		
		// Grab the inner view
		if ($content)
		{
			// Add the rest of the config if there isn't a template
			if (!$this->template)
			{
				$temp = array(
					'head'   => $this->head
				);
				
				$config = array_merge($config, $temp);
			}
			
			$content = page::view($content, $config, $type);
		}
		
		// Display the page with a template
		if ($this->template) {
		
			// Setup the main template config array
			$config = array(
				'head' 		=> $this->head,
				'content' 	=> $content.$eol
			);
		
			// Display the view
			$view = new View($this->template, $config);
			$output = $view->render();
		
		} else {
			
			$output = $content;
		}
		
		// Format output
		$output = $this->formatHTML($output);
		
		// Display the page
		echo $output;
	}
	
	private function fileCombine($type, $file)
	{
		$exists    = 'cache_'.$type.'_exists';
		$cache     = 'cache_'.$type;
		$key       = 'cache_'.$type.'_key';
		$path      = Kohana::config('pages.'.$type.'_path');
		$container = 'cache_container_'.$type;

		if ($this->$exists === FALSE)
		{
			// If we haven't attempted loading the css cache, try to get it
			$cur_cache = $this->$cache;
			if ($cur_cache['data'] === '')
			{
				$this->$cache = $this->getCache($type, $this->$key);
			}
			
			$cur_cache = $this->$cache;
			if ($cur_cache['data'] === null)
			{
				$output = FALSE;
			
				// Open actual file to load it into our container
				// Use cURL incase it's an external file
		    	$ch = curl_init();
		    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		    	curl_setopt($ch, CURLOPT_HEADER, 0);
		    	curl_setopt($ch, CURLOPT_URL, $file);
		    	$output = curl_exec($ch);
		    	curl_close($ch);
			
			    if ($output !== FALSE)
			    {
			    	$this->$container = $this->$container."\n".$output;
			    }
			}
			else
			{
				$this->$container = $this->$cache;
			}
		}
	}
	
	private function formatHTML($output, $format = FALSE)
	{
		$format = (($format) ? $format : $this->format_output);
		
		switch($format)
		{
			case 'compress':
				$output = page::compress($output);
				break;
				
			case 'indent':
				$output = page::indent($output);
				break;
		}
		
		return $output;
	}
	
	private function cacheExists($type, $key)
	{
		return (bool) file_exists(Kohana::config('pages.'.$type.'_path').$key.'.'.$type);
	}
	
	private function getCache($type, $key)
	{
		$data = FALSE;

		if (file_exists(Kohana::config('pages.'.$type.'_path').$key.'.'.$type))
		{
			$data = file_get_contents(Kohana::config('pages.'.$type.'_path').$key.'.'.$type);
		}
		
		if ($data !== FALSE)
		{
			return array('data' => $data, 'file' => $key.'.'.$type);
		}
		
		return array('data' => null, 'file' => $key.'.'.$type);
	}
	
	private function removeExpiredCache($type)
	{
		$type_path = $type.'_path';
		$version   = Kohana::config('pages.version');

		if (is_dir($this->$type_path))
		{
		    if ($dir = opendir($this->$type_path))
		    {
		        while (($file = readdir($dir)) !== FALSE)
		        {
		        	// Look for the version number in the filename
		        	preg_match('/^_pages_(\d+)_(?:.*)/', $file, $matches);

					if (isset($matches[1]) && is_numeric($matches[1]) && ((int) $version !== (int) $matches[1]))
					{
						unlink($this->$type_path.$file);
					}
		        }
				
				closedir($dir);
		    }
		}
	}
	
	private function setCache($type, $key, $data)
	{
		$filename = $key.'.'.$type;
		
		if (Kohana::config('pages.format_output') === 'compress')
		{
			switch (TRUE)
			{
				case ($type === 'js'):
					$data = page::minifyJS($data);
					break;
				case ($type === 'css'):
					$data = page::compressCSS($data);
					break;
			}
		}		
		
		$put = (bool) file_put_contents(Kohana::config('pages.'.$type.'_path').$filename, $data);
	
		return array('put' => $put, 'filename' => $filename);
	}
}
