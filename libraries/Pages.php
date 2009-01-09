<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Pages Library
 *
 * @package	   Pages Module
 * @author     Sam Soffes
 * @author     Josh Turmel
 * @copyright  (c) 2007-2009 LifeChurch.tv
 */
class Pages_Core {

	protected $html;
	protected $head;
	
	// Title
	protected $title        = array();
	protected $title_rev    = false;
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

	public function __construct($i18n = false)
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
		$this->title_rev = true;
	}

	public function setTitleSep($sep)
	{
		$this->title_sep = $sep;
	}
	
	public function getCacheIdForView($name)
	{
		return 'page_view_'.(($this->lang) ? $this->lang.'_' : '').md5(url::current().$name);
	}
		
	public function addLink($rel, $type, $href, $title = false) {
		// By using href as key, it allows us to override in extended controllers.
		$this->link[$href] = '<link href="'.$href.'" rel="'.$rel.'" title="'.$title.'"'.(($type) ? ' type="'.$type.'"' : '').' />';
	}
	
	public function addOpenSearch($href, $title = false)
	{
		$this->addLink('search', 'application/opensearchdescription+xml', $href, $title);
	}
	
	public function addRSS($href, $title = false)
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
	protected function addScript($type, $file, $cache = false, $embed = false)
	{
		$url = $type.'_url';
		$scripts = &$this->$type;
		
		if (strpos($file, '://') !== false)
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
	
	public function addCSS($file, $cache = false, $embed = false)
	{
		$this->addScript('css', $file, $cache, $embed);
	}
	
	public function removeCSS($script)
	{
		unset($this->css[$script]);
	}
	
	public function removeAllCSS()
	{
		$this->css = array();
	}
	
	public function addJS($file, $cache = false, $embed = false)
	{
		$this->addScript('js', $file, $cache, $embed);
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

		$head = '';

		// Add regular css
		foreach ($this->css as $css)
		{
			// TODO: Add in embedding of CSS code
			$head .= '<link rel="stylesheet" href="'.$css['file'].'" type="text/css" />'.$eol;
		}

		// Add regular js
		foreach ($this->js as $js)
		{
			$head .= '<script type="text/javascript" src="'.$js['file'].'"></script>'.$eol;
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
	
	public function display($content = false, $config = array()) {
		
		// Make the eol a local variable for less typing
		$eol = $this->eol;
		
		// Construct the <head>
		$this->head = $this->buildHead();
		
		// Grab the inner view
		if ($content)
		{
			$content = new View($content);
			
			// Add the rest of the config if there isn't a template
			if (!$this->template)
			{
				$temp = array(
					'head'   => $this->head
				);
				
				$config = array_merge($config, $temp);
			}
			
			$content->set($config);
			$content = $content->render();
		}
		
		// Display the page with a template
		if ($this->template) {
		
			// Setup the main template config array
			$config = array(
				'head' 		=> $this->head,
				'content' 	=> $content.$eol
			);
		
			// Display the view
			$view = new View($this->template);
			$view->set($config);
			$output = $view->render();
		
		} else {
			
			$output = $content;
		}
		
		// Format output
		$output = $this->formatHTML($output);
		
		// Display the page
		echo $output;
	}
	
	private function formatHTML($output, $format = false)
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
}