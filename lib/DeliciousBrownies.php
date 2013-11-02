<?php
/*
 * DeliciousBrownies - PHP 5 class to play with del.icio.us API
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author 	  Nashruddin Amin <me@nashruddin.com>
 * @copyright Nashruddin Amin 2008
 * @license	  GNU General Public License 3.0
 * @package   DeliciousBrownies
 * @version   1.1
 */
class DeliciousBrownies
{
	private $_api_posts;
	private $_api_tags;
	private $_api_bundles;
	private $_delicious_user;
	private $_delicious_pass;
	private $_proxy_host;
	private $_proxy_port;
	private $_proxy_user;
	private $_proxy_pass;
	private $_info;
	private $_delicious_msg;
	private $_user_agent;
	
	/* curl handle */
	private $_ch;
	
	/**
	 * class constructor
	 */
	public function __construct()
	{
		/* check if curl is available */
		if (!function_exists("curl_init")) {
			print "I cannot continue because Curl doesn't exist. Sorry.\n";
			exit;
		}
		
		/* check if simplexml is available */
		if (!function_exists("simplexml_load_string")) {
			print "I cannot continue because SimpleXML doesn't exist. Sorry.\n";
			exit;
		}
		
		/* setup del.icio.us web service URLs */
		$this->_api_posts   = "https://api.pinboard.in/v1/posts";
		$this->_api_tags    = "https://api.pinboard.in/v1/tags";
		$this->_api_bundles = "https://api.pinboard.in/v1/tags/bundles";
		
		/* setup User-Agent */
		$this->_user_agent = "DeliciousBrownies.php/1.1";
		
		/* setup curl */
		$curl_opts = array
		(
			CURLOPT_SSL_VERIFYPEER 	=> false, 
			CURLOPT_SSL_VERIFYHOST 	=> 2, 
			CURLOPT_HEADER 			=> false,
			CURLOPT_RETURNTRANSFER 	=> 1,
			CURLOPT_USERAGENT 		=> $this->_user_agent
		);		
		$this->_ch = curl_init();
		curl_setopt_array($this->_ch, $curl_opts);
	}
	
	/**
	 * class destructor
	 */
	public function __destruct()
	{
		if (function_exists("curl_close")) {
			curl_close($this->_ch);
		}
	}
	
	/**
	 * set host and port for proxy
	 *
	 * @param string $hostport host and port with format: 'host:port'
	 */
	public function setProxyHost($hostport)
	{
		curl_setopt($this->_ch, CURLOPT_PROXY, $hostport);
		list($this->_proxy_host, $this->_proxy_port) = explode(':', $hostport);
	}
	
	/**
	 * set user and password for proxy
	 *
	 * @param string $userpass username and password for proxy. format: 'username:password'
	 */
	public function setProxyUser($userpass)
	{
		curl_setopt($this->_ch, CURLOPT_PROXYUSERPWD, $userpass);
		list($this->_proxy_user, $this->_proxy_pass) = explode(':', $userpass);
	}
	
	/**
	 * Set username for del.icio.us login
	 *
	 * @param string $username
	 */
	public function setUsername($username)
	{
		$this->_delicious_user = $username;
		
		if (!empty($this->_delicious_pass)) {
			curl_setopt($this->_ch, CURLOPT_USERPWD, "$username:$this->_delicious_user");
		}
	}
	
	/**
	 * set password for del.icio.us login
	 *
	 * @param string $password
	 */
	public function setPassword($password)
	{
		$this->_delicious_pass = $password;
		
		if (!empty($this->_delicious_user)) {
			curl_setopt($this->_ch, CURLOPT_USERPWD, "$this->_delicious_user:$password");
		}
	}
	
	/**
	 * perform HTTPS request with Curl
	 *
	 * @param string $url the url of del.icio.us API
	 *
	 * @return mixed XML string on success
	 *				 boolean false on failure
	 */
	private function get($url)
	{
		/* delay for 1 second. DON'T change this, since del.icio.us required all 
		   clients to set delay AT LEAST 1 SECOND between requests */
		sleep(1);

// echo $url;

		curl_setopt($this->_ch, CURLOPT_URL, $url);
		$res = curl_exec($this->_ch); 
		$this->_info = curl_getinfo($this->_ch);
		
		/* $res now contains XML string or boolean false */
		return($res);
	}

	/**
	 * get all posts
	 *
	 * @return mixed array of posts on success,
	 *				 boolean false on failure
	 */
	public function getAllPosts()
	{
		$res = $this->get("$this->_api_posts/all");
		if ($res == false) {
			return(false);
		}
		return($this->xml2array($res));	
	}
	
	/**
	 * get posts matching the arguments. if no arguments if given, most
	 * recent date will be used.
	 *
	 * @param  string	$tag	filter by this tag
	 * @param  string	$url	filter by this url
	 *
	 * @return mixed array of posts on success,
	 *				 boolean false on failure
	 */
	public function getPosts($tag = '', $url = '', $date = '')
	{
		$params = array 
		(
			"tag" => $tag, 
			"url" => $url,
		);
		if (!empty($date)) {
			$params['date'] = date('Y-m-d', $date).'T'.date('H:i:s', $date).'Z';
		}
		$qry = http_build_query($params);

		$res = $this->get("$this->_api_posts/get?$qry");

		if ($res == false) {
			return(false);
		}		
		return($this->xml2array($res));
	}
	
	/**
	 * Return a list of the most recent post
	 *
	 * @param string $tag   filter by this tag
	 * @param int    $count number of items to retrieve
	 *
	 * @return mixed array of posts on success,
	 *				 boolean false on failure
	 */
	public function getRecentPosts($tag = '', $count = 15)
	{
		$params = array 
		(
			"tag" 	=> $tag, 
			"count" => ($count > 100) ? 100 : $count
		);
		$qry = http_build_query($params);
		$res = $this->get("$this->_api_posts/recent?$qry");
		
		if ($res == false) {
			return(false);
		}		
		return($this->xml2array($res));
	}
	
	/**
	 * add a post to del.icio.us
	 *
	 * @param string  $url 	  the url of the item
	 * @param string  $desc   the description of the item
	 * @param string  $tags   tags for the item (space separated)
	 * @param string  $notes  notes for the item
	 * @param boolean $shared make the item private
	 *
	 * @return boolean	true on success,
	 *					false on failure
	 */
	public function addPost($url, $desc, $tags = '', $notes = '', $shared = true)
	{
		$params = array 
		(
			"url" 	  	  => $url, 
			"description" => $desc,
			"tags"    	  => $tags,
			"extended"	  => $notes,
			"shared"  	  => ($shared==true) ? "yes" : "no",
			"replace" 	  => "no"
		);
		$qry = http_build_query($params);
		$res = $this->get("$this->_api_posts/add?$qry");
		
		if ($res == false) {
			return(false);
		}		
		return($this->xml2bool($res));
	}
	
	/**
	 * update a post on del.icio.us
	 *
	 * @param string  $url 	  the url of the item
	 * @param string  $desc   the description of the item
	 * @param string  $tags   tags for the item (space separated)
	 * @param string  $notes  notes for the item
	 * @param boolean $shared make the item private
	 *
	 * @return boolean	true on success,
	 *					false on failure
	 */
	public function updatePost($url, $desc, $tags = '', $notes = '', $shared = true)
	{
		$params = array 
		(
			"url" 	  	  => $url, 
			"description" => $desc,
			"tags"    	  => $tags,
			"extended"    => $notes,
			"shared"  	  => ($shared==true) ? "yes" : "no",
			"replace" 	  => "yes"
		);
		$qry = http_build_query($params);
		$res = $this->get("$this->_api_posts/add?$qry");
		
		if ($res == false) {
			return(false);
		}		
		return($this->xml2bool($res));
	}
	
	/**
	 * delete a post on del.icio.us
	 *
	 * @param string $url the url of the item
	 *
	 * @return boolean	true on success,
	 *					false on failure
	 */
	public function deletePost($url)
	{
		$res = $this->get("$this->_api_posts/delete?url=$url");
		
		if ($res == false) {
			return(false);
		}		
		return($this->xml2bool($res));
	}
	
	/**
	 * Returns a list of tags and number of times used by a user
	 *
	 * @return mixed	array of tags on success,
	 *					boolean false on failure
	 */
	public function getTags()
	{
		$res = $this->get("$this->_api_tags/get");
		
		if ($res == false) {
			return(false);
		}
		return($this->xml2array($res));
	}
	
	/**
	 * Rename an existing tag with a new tag name
	 *
	 * @param string $old tag to rename
	 * @param string $new new name
	 *
	 * @return boolean	true on success,
	 *					false on failure
	 */
	public function renameTag($old, $new)
	{
		$params = array
		(
			"old" => $old,
			"new" => $new
		);
		$qry = http_build_query($params);
		$res = $this->get("$this->_api_tags/rename?$qry");

		if ($res == false) {
			return(false);
		}
		return($this->xml2bool($res));
	}
	
	/**
	 * retrieve all of user's bundles
	 *
	 * @return mixed array on success, FALSE otherwise
	 */
	public function getAllBundles()
	{
		$res = $this->get("$this->_api_bundles/all");
		if ($res == false) {
			return(false);
		}
		return($this->xml2array($res));
	}

	/**
	 * Assign a set of tags to a single bundle, wipes away previous settings for bundle
	 *
	 * @param string $bundle the bundle name
	 * @param string $tags   list of tags (space separated)
	 *
	 * @return boolean true on success,
	 *				   false on failure
	 */
	public function setBundle($bundle, $tags)
	{
		$params = array
		(
			"bundle" => $bundle,
			"tags"   => $tags
		);
		$qry = http_build_query($params);
		$res = $this->get("$this->_api_bundles/set?$qry");
		
		if ($res == false) {
			return(false);
		}
		return($this->xml2bool($res));
	}

	/**
	 * delete a bundle
	 *
	 * @param string $bundle the bundle name
	 *
	 * @return boolean true on success,
	 *				   false on failure
	 */
	public function deleteBundle($bundle)
	{
		$res = $this->get("$this->_api_bundles/delete");
		if ($res == false) {
			return(false);
		}
		return($this->xml2bool($res));
	}
	
	/**
	 * returns the last update time for the user
	 *
	 * @return mixed datetime in string on success,
	 *				 boolean false on failure
	 */
	public function getLastUpdate()
	{
		$res = $this->get("$this->_api_posts/update");
		
		$xml = simplexml_load_string($res);
		$att = $xml->attributes();
		
		return((string)$att);
	}
	
	/**
	 * convert XML result to array
	 *
	 * @param  string $xmlstr the XML string
	 * @return array
	 */
	private function xml2array($xmlstr)
	{
		$children = array();
		$i = 0;
		
		$xml = @simplexml_load_string($xmlstr);
		if (is_object($xml)) {
			foreach($xml->children() as $child) {
				foreach($child->attributes() as $key=>$val) {
					$children[$i][$key] = (string)$val;
				}
				$i++;
			}
		}
		return($children);
	}
	
	/**
	 * convert XML code to boolean
	 *
	 * @param  string  $xmlcode the XML string
	 * @return boolean
	 */
	private function xml2bool($xmlcode)
	{
		$xml = simplexml_load_string($xmlcode);
		$att = $xml->attributes();
		
		if (empty($att)) {
			$code = (string)$xml;
		} else {
			$code = (string)$att;
		}
		$this->_delicious_msg = $code;
		
		switch(strtolower($code)) {
			case 'done':
				return true;
			default:
				return false;
		}
	}

	/**
	 * get info from Curl's last exec
	 *
	 * @return array
	 */
	public function getInfo()
	{
		return($this->_info);
	}
	
	/**
	 * get last del.icio.us message
	 *
	 * @return string
	 */
	public function getDeliciousMsg()
	{
		return($this->_delicious_msg);
	}
}
