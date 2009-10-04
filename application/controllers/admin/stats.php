<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This controller is used to manage users
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     Admin Users Controller  
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Stats_Controller extends Admin_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->template->this_page = 'stats';
		
		// If this is not a super-user account, redirect to dashboard
		if (!$this->auth->logged_in('admin') && !$this->auth->logged_in('superadmin'))
        {
             url::redirect('admin/dashboard');
		}
	}
	
	function index()
	{	
		$this->template->content = new View('admin/stats');
		$this->template->content->title = 'Statistics';
		
		// Retrieve Current Settings
		$settings = ORM::factory('settings', 1);
		$this->template->content->stat_id = $settings->stat_id;
		
		
		
	}
	
	function hits()
	{
		$this->template->content = new View('admin/stats_hits');
		$this->template->content->title = 'Hit Summary';
		
		// Retrieve Current Settings
		$settings = ORM::factory('settings', 1);
		$this->template->content->stat_id = $settings->stat_id;
		$sitename = $settings->site_name;
		$url = url::base();
		
		if (!empty($_GET['create_account'])){
			$this->template->content->stat_id = $this->_create_site( $sitename, $url );
		}
		
		// Javascript Header
		$this->template->flot_enabled = TRUE;
		$this->template->js = new View('admin/stats_js');
		
		// Graphs, Maps and other Data
		
		$this->template->js->all_graphs = Stats_Model::get_hit_stats();
		$this->template->content->countries = Stats_Model::get_hit_countries();
	}
	
	function country()
	{
		$this->template->content = new View('admin/stats_country');
		$this->template->content->title = 'Country Breakdown';
		
		$this->template->content->countries = Stats_Model::get_hit_countries();
		
		//Set up country map
		$country_total = array();
		foreach($this->template->content->countries as $country){
			foreach($country as $code => $arr) {
				if(!isset($country_total[$code])) $country_total[$code] = 0;
				$country_total[$code] += $arr['uniques'];
			}
		}
		
		arsort($country_total);
		
		$codes = '';
		$values = '';
		$i = 0;
		foreach($country_total as $code => $uniques){
			if($i == 0) $highest = $uniques;
			if($i != 0) $values .= ',';
			$values .= ($uniques / $highest) * 100;
			$codes .= strtoupper($code);
			$i++;
		}
		$this->template->content->visitor_map = "http://chart.apis.google.com/chart?chs=440x220&chf=bg,s,ffffff&cht=t&chtm=world&chco=cccccc,A07B7B,a20000&chld=".$codes."&chd=t:".$values;
	}
	
	/**
	 * Creates a new site in centralized stat tracker
	 * @param sitename - name of the instance
	 * @param url - base url 
	 */
	public function _create_site( $sitename, $url ) 
	{
		$stat_url = 'http://tracker.ushahidi.com/px.php?task=cs&sitename='.urlencode($sitename).'&url='.urlencode($url);
		$this->template->content->test .= '<br>Create Site - '.$stat_url.'<br>';
		
		// FIXME: This method of extracting the stat_id will only work as 
		//        long as we are only returning the id and nothing else. It
		//        is just a quick and dirty implementation for now.
		$stat_id = trim(strip_tags($this->_curl_req($stat_url))); // Create site and get stat_id
		
		$this->template->content->test .= 'New Stat ID: '.$stat_id.'<br>';
		
		if($stat_id > 0){
			$settings = ORM::factory('settings',1);
			$settings->stat_id = $stat_id;
			$settings->save();
			return $stat_id;
		}
		
		return false;
	}
	
	/**
	 * Helper function to send a cURL request
	 * @param url - URL for cURL to hit
	 */
	public function _curl_req( $url )
	{
		// Make sure cURL is installed
		if (!function_exists('curl_exec')) {
			throw new Kohana_Exception('stats.cURL_not_installed');
			return false;
		}
		
		$curl_handle = curl_init();
		curl_setopt($curl_handle,CURLOPT_URL,$url);
		curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,15); // Timeout set to 15 seconds. This is somewhat arbitrary and can be changed.
		curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1); //Set curl to store data in variable instead of print
		$buffer = curl_exec($curl_handle);
		curl_close($curl_handle);
		
		return $buffer;
	}
}