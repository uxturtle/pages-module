<?php defined('SYSPATH') or die('No direct script access.');
/**
 * @package    core
 * @author     Sam Soffes
 */

class markdown_Core {

	public static function to_html($string)
	{
		$markdown = new Markdown_Parser;
		return '<div class="markdown">'.$markdown->transform($string).'</div>';
	}
}