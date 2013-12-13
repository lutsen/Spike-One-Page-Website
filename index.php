<?php

// This script uses the Spike API to create a 1 page website which is editable in Spike.
// Create a Spike account here: http://app.spikecms.com/user/subscribe.php
// More about the Spike API here: http://www.spikecms.com/docs/

// This script is open-source and licensed under the MIT license.
// This script is not a part of the Spike CMS, but only uses it's API.
// Spike CMS is a hosted CMS and has it's own terms: http://app.spikecms.com/terms/

// Copyright (c) 2013 Lútsen Stellingwerff

// Permission is hereby granted, free of charge, to any person
// obtaining a copy of this software and associated documentation
// files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use,
// copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following
// conditions:

// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
// OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
// FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
// OTHER DEALINGS IN THE SOFTWARE.

// Variables
// ---------

// The Spike API endpoint URL
define('API_URL', 'http://api.spikecms.com');

// Your Spike API key, available in the creator overview page of your website
define('API_KEY', '');

// The URL of the website: http://www.mydomain.com
define('WEBSITE_URL', '');

// You can use the URL_PLACEHOLDER value (in this case [[WEBSITE-URL]]) in your Spike templates 
// if you want to be able to change the URL without changing the template code, for example when
// testing the website on another domain.
define('URL_PLACEHOLDER', '[[WEBSITE-URL]]');

// You can use the CONTENT_PLACEHOLDER value (in this case <!--[[CHILD-PAGES]]-->) in your Spike templates
// to add the content of child-pages on that location in the template when the site is served as a one page website.
define('CONTENT_PLACEHOLDER', '<!--[[CHILD-PAGES]]-->');

// You can use the MENU_PLACEHOLDER value (in this case <!--[[ANCHOR-MENU]]-->) in your Spike templates 
// to insert the anchor menu generated in this script. This menu allows you to scroll to the right position
// in the page if used in combintion with id's containing the PERMALINK_PLACEHOLDER value.
define('MENU_PLACEHOLDER', '<!--[[ANCHOR-MENU]]-->');

// You can use the PERMALINK_PLACEHOLDER value (in this case [[PERMALINK]]) in your
// Spike templates to create ID's to scroll to from the achor menu.
define('PERMALINK_PLACEHOLDER', '[[PERMALINK]]');

// The anchor you want to use for your homepage: http://www.mydomain.com/#!/home
define('HOME_ANCHOR', 'home');

// You can use the HOME_ANCHOR_PLACEHOLDER value (in this case [[HOME_ANCHOR]]) in your
// Spike templates to put the home-anchor in an id.
define('HOME_ANCHOR_PLACEHOLDER', '[[HOME_ANCHOR]]');

// Use the splitter to chop off the header and footer of your pages when included in the one-page website.
// The header and footer are needed when the page is serverd as a single page too Google using _escaped_fragment_
define('SPLITTER', '<!-- SPLITTER -->');


// The code
// --------

// Set right characterset
header('Content-Type: text/html; charset=utf-8');

// Get content for single page
// @param $page_permalink	string
// @param $strip_header 	boolean which indicates whether or not the header should be stripped of the content of an individual page
// @param $find				array containing placeholders to replace
// @param $replace			array containing content to replace placeholders
function getPageContent($content, $strip_header, $find=false, $replace=false) {
	if ($find && $replace) {
		$content = str_replace($find, $replace, $content);
	}
	if ($strip_header && strpos($content, SPLITTER) !== FALSE) {
		$split = explode(SPLITTER, $content);
		return $split[1];
	} else {
		return $content;
	}
}

// Replace placeholder with children content
// @param $page HTML code of page
// @param $children HTML code of all child pages in 1 string 
// @param $palceholder string to replace with HTML content
function placeChildren($page, $children, $palceholder) {
	$content = '';
	if (strpos($page, $palceholder) !== FALSE) {
		$content .= str_replace($palceholder, $children, $page); // Put children in parent page
	} else {
		$content .= $page;
		$content .= $children;
	}
	return $content;
}

// Get page and children content
// @param $page_permalink string
// @param $site_array array with complete structure and content of (part of) the site
function getAllContent($parent_content, $site_array, $strip_header=true, $find=false, $replace=false) {
	if (!$site_array['error']) {
		foreach ($site_array as $value) {
			if ($value['children']) {
				$child_pages .= getAllContent($value['content'], $value['children'], true, array(PERMALINK_PLACEHOLDER), array($value['page_permalink']));
			} else {
				$child_pages .= getPageContent($value['content'], true, array(PERMALINK_PLACEHOLDER), array($value['page_permalink']));
			}
		}
		
		// Put children in parent
		$parent_page = getPageContent($parent_content, $strip_header, $find, $replace);
		return placeChildren($parent_page, $child_pages, CONTENT_PLACEHOLDER);
	}
}

if ($_GET['_escaped_fragment_']) {
	
	// Show single page depending on page_permalink
	// --------------------------------------------

	$page_permalink = $_GET['_escaped_fragment_'];
	
	if ($page_permalink == HOME_ANCHOR) {
		
		// Get only homepage without content of children
		$url = API_URL.'/page.html?api_key='.API_KEY;
		$output = file_get_contents($url);

	} else {
		
		// Get parent content
		$url = API_URL.'/page.html?permalink='.$page_permalink.'&api_key='.API_KEY;
		$output = file_get_contents($url);
		
		// Get site structure: all children of this page
		$url = API_URL.'/children?permalink='.$page_permalink.'&api_key='.API_KEY.'&content=1';
		$structure_json = file_get_contents($url);
		$structure_array = json_decode($structure_json, true);
		if (!$structure_array['error']) {
			$output = getAllContent($output, $structure_array, false);
		}

	}

} else {

	// Show entire 1-page website
	// --------------------------
	
	// Get site structure: all children of homepage
	$url = API_URL.'/siblings.json?api_key='.API_KEY.'&content=1';
	$site_json = file_get_contents($url);
	$site_array = json_decode($site_json, true);
	
	// Create anchor memu
	// In this version, a menu is created only for the children of the homepage.
	$anchor_menu .= '<li class="active"><a href="'.WEBSITE_URL.'/#!/'.HOME_ANCHOR.'">home</a></li>'."\n"; // Home link
	if (!$site_array['error']) {
		foreach ($site_array[0]['children'] as $key => $value) {
			if ($value['page_permalink'] != '') {
				$anchor_menu .= '<li><a href="'.WEBSITE_URL.'/#!/'.$value['page_permalink'].'">'.$value['title'].'</a></li>'."\n";
			}
		}
	}
	
	// Compose 1 page website
	$one_page_website = getAllContent('', $site_array, false);
	
	// Place menu code and URL
	$find = array(MENU_PLACEHOLDER, URL_PLACEHOLDER, HOME_ANCHOR_PLACEHOLDER);
	$replace = array($anchor_menu, WEBSITE_URL, HOME_ANCHOR);
	echo str_replace($find, $replace, $one_page_website);

}

?>