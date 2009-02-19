<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @package    core
 * @author     Sam Soffes
 */

// Require the Markdown library
require Kohana::find_file('vendor', 'Markdown');

class markdown_Core {

	public static function to_html($string)
	{
		$markdown = new MarkdownExtra_Parser;
		return '<div class="markdown">'.$markdown->transform($string).'</div>';
	}
	
	// Easily create links in markdown
	public static function anchor($uri, $title = NULL, $protocol = NULL)
	{
		if ($uri === '')
		{
			$site_url = url::base(FALSE);
		}
		elseif (strpos($uri, '://') === FALSE AND strpos($uri, '#') !== 0)
		{
			$site_url = url::site($uri, $protocol);
		}
		else
		{
			$site_url = $uri;
		}

		return '['.(($title === NULL) ? $site_url : $title).']('.$site_url.')';
	}
}