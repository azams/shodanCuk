<?php
################ Config ################
//your username
$user = "put_your_username_here";

//your password
$pass = "put_your_password_here";

// cookie path, you can edit here
$cookies = "/tmp/shodan_cookies.txt";
############## END CONFIG ##############

$scan = true;
$proxy = false;

if(isset($_SERVER['argv'][1])) 
{
	$dork = $_SERVER['argv'][1];
	banner();
	foreach($_SERVER['argv'] as $args) {
		if(stristr($args, "--save=")) {
			$out = str_replace("--save=", "", $args);
			if(empty($out))
				die(banner());	
		} elseif(stristr($args, "--proxy=")) {
			$proxy = str_replace("--proxy=", "", $args);
			if(empty($proxy)) {
				banner(); exit;
			} else {
				$check = check_proxy();
				if(!$check)
					die("Status proxy: Not Alive\n");
			}
		}
	}
	
	$page = login();
	if(!$page) 
	{
		echo "Grabbing data.\n";
		$data = array();
		$i = 0;	$p = 1;
		while($scan) 
		{
			$page = buka("https://www.shodan.io/search?query=$dork&page=$p");
			if(stristr($page, "No results found")) die("No results found.\n");
			
			if(preg_match_all('#<div class="ip"><a href="(http:\/\/|https:\/\/|\/host\/)([0-9\.:]+)">(.*?)<\/a>#', $page, $ips)) $ips = $ips[2];
			elseif(preg_match('#<div class="ip"><a href="(http:\/\/|https:\/\/|\/host\/)([0-9\.:]+)">#', $page, $ips)) $ips = $ips[2];
			preg_match_all('#src="https:\/\/static\.shodan\.io\/shodan\/img\/flags\/(.*?)" title="(.*?)"\/>#', $page, $country);
			preg_match_all('#<span>Added on (.*?)<\/span>#', $page, $added);
			
			$tot = count($country[0]);
			if($tot == 0) die("=============== Grabbing Complete =============\n");
			for($o=0;$o<$tot;$o++) 
			{
				if(!empty($ips[$o])) {
					$ip = $ips[$o];
					$coun = $country[2][$o];
					$add = $added[1][$o];
					echo "[$i]\n        [Host] $ip\n        [COUNTRY] $coun\n        [DATE ADDED] $add\n";
					$data[$i] = array( "ip"=>$ip, "country"=>$coun, "date"=>$add );
					$i++;
				}
			}
			
			$json = json_encode($data, JSON_PRETTY_PRINT);
			if(isset($out)) write($out, $json);
			if(stristr($page, '<p>Result limit reached.</p>')) $scan = false;
			$p++;
		}
		echo "Cleaning json..\n";
		clean_json($out);
	} else {
		die("Login status : FALSE\nProcess terminated.\n");
	}
	echo "\n=============== Grabbing Complete =============\n";
} else {
	banner();
}

function clean_json($out) {
	$file = file_get_contents($out);
	$file = str_replace("\n][", ",", $file);
	unlink($out);
	write($out, $file);
}

function banner() {
	echo "
                                             
     |             |          ,---.     |    
,---.|---.,---.,---|,---.,---.|    .   .|__/ 
`---.|   ||   ||   |,---||   ||    |   ||  \ 
`---'`   '`---'`---'`---^`   '`---'`---'`   `

https://github.com/azams/shodanCuk

Usage: php ".$_SERVER['argv'][0]." [dork] [optional: save results to json] [optional: socks proxy]
Example 1: php shodan.php 'jboss 6657' --save=jboss_data.json
Example 2: php shodan.php 'jboss 6657' --save=jboss_data.json --proxy=127.0.0.1:9050
";
}
function write($file, $data) 
{
	$fp = fopen($file, "a");
	fwrite($fp, $data);
	fclose($fp);
}

function check_proxy() {
	global $proxy;
	echo "Checking proxy $proxy\n";
	$page = buka("https://shodan.io/");
	if(stristr($page, "<title>Shodan</title>")) 
		return true;
	elseif(stristr($page, "<title>Attention Required! | CloudFlare</title>"))
		die("Blocked by CloudFlare\n");
	else
		return false;
}

function login() 
{
	global $user;
	global $pass;
	global $proxy;
	
	echo "Login in progress..\n";
	$page = buka("https://account.shodan.io/login", "username=".urlencode($user)."&password=".urlencode($pass)."&grant_type=password&continue=https%3A%2F%2Fwww.shodan.io%2F&login_submit=Log+in");
	if(stristr($page, '<a href="https://account.shodan.io" class="account">') || stristr($page, "<a href='/logout' class='btn btn-small btn-inverse'>")) return false;
	else return $page;
}

function buka($host,$post = false) 
{
	global $proxy;
	global $cookies;
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $host);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	if($proxy)
	{
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, 6);
    }
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
	if ($post) 
	{
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	$data = curl_exec($ch);
	curl_close($ch);
	if($data) return $data;
	else false;
}
