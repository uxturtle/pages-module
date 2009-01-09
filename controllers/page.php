<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Page Controller - simple controller subclass that loades the
 * pages library.
 *
 * @package	   Pages Module
 * @author     Sam Soffes
 * @copyright  (c) 2007-2009 LifeChurch.tv
 */
abstract class Page_Controller extends Controller {

	public function __construct()
	{
		parent::__construct();
		
		// Load the page class
		$this->page = Pages::instance();
	}

}