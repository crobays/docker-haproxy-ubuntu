#!/usr/bin/php
<?php

$debug = !is_dir('/etc/service/haproxy');
print_r('DEBUG: '.($debug ? 'TRUE' : 'FALSE'));

if ($debug) {
	// $_SERVER['BACKEND_PORT_8000_TCP_ADDR'] = '172.0.1.22';
	// $_SERVER['BACKEND_ENV_DOMAIN'] = 'fdsa.dev.example.com';
	// $_SERVER['DOMAINNAME_PORT_80_TCP_ADDR'] = '172.0.1.23';
	// $_SERVER['DOMAINNAME_ENV_DOMAIN'] = 'test.dev.example.com';
	$_SERVER['MYSQL_PORT_3306_TCP_ADDR'] = '172.0.1.24';
	$_SERVER['MYSQL_PORT_3306_TCP_PORT'] = '3307';
	$_SERVER['MYSQL_ENV_DOMAIN'] = 'mysql.dev.example.com';
	$_SERVER['POSTGRES_PORT_5432_TCP_ADDR'] = '172.0.1.24';
	$_SERVER['POSTGRES_PORT_5432_TCP_PORT'] = '5432';
	$_SERVER['POSTGRES_ENV_DOMAIN'] = 'mysql.dev.example.com';
}

function write_file($file, $data)
{
	$handle = fopen($file, "w");
    $success = fwrite($handle, $data, strlen($data));
    fclose($handle);
    if ( ! $success)
    {
    	throw new Exception("Error writing to file: $file", 1);
    }
}

if ( ! $debug) {
	write_file("/etc/default/haproxy", "ENABLED=1");
}

$uri = array_key_exists('HAPROXY_URI', $_SERVER) ? $_SERVER['HAPROXY_URI'] : '/';
$port = array_key_exists('HAPROXY_PORT', $_SERVER) ? $_SERVER['HAPROXY_PORT'] : '8080';
$forwardfor = TRUE;
if(array_key_exists('ENABLE_FORWARD_FOR', $_SERVER))
{
	$forwardfor = in_array(strtoupper($_SERVER['ENABLE_FORWARD_FOR']), array('FALSE', 'NO', 'NONE')) ? FALSE : !!$_SERVER['ENABLE_FORWARD_FOR'];
}

$sections["listen"]["stats"] = [
	"bind :$port",
	"stats enable",
	"stats uri $uri",
	"stats refresh 5s",
];

if (array_key_exists('HAPROXY_PASSWORD', $_SERVER))
{
	$username = array_key_exists('HAPROXY_USERNAME', $_SERVER) ? $_SERVER['HAPROXY_USERNAME'] : 'haproxy';
	$password = $_SERVER['HAPROXY_PASSWORD'];
	$sections["listen"]["stats"][] = "stats auth $username:$password";
}
else
{
	$sections["listen"]["stats"][] = "stats hide-version";
}

$sections["frontend"]["http-in"] = [
	'bind :80',
	"redirect" => [],
	"acl" => [],
	"use_backend" => [],
];

$sections["frontend"]["https-in"] = [
	'bind :443',
	"redirect" => [],
	"acl" => [],
	"use_backend" => [],
];

$domains = array();

foreach($_SERVER as $k => $v)
{
	if (substr($k, -11, 11) != "_ENV_DOMAIN")
	{
		continue;
	}
	
	$k = strtolower(substr($k, 0, strlen($k)-11));
	$v = json_decode(str_replace("'", '"', $v)) ?: array($v);
	
	foreach($v as $d)
	{
		$domains[$d] = $k;
	}
}

$sorted_domains = array();
foreach ($domains as $d => $k)
{
	$sorted_domains[implode('.', array_reverse(explode('.', $d))).'~'] = $k;
}
ksort($sorted_domains);
foreach($sorted_domains as $domain => $name)
{
	$domain = trim($domain, '~');
	$title = str_replace('.', '_', $domain);
	$domain = implode('.', array_reverse(explode('.', $domain)));
	
	$d = explode('.', $domain);
	if ((count($d) < 3 || $d[0] === 'www') && $domain !== 'localhost')
	{
		$naked_domain = substr($domain, strlen($d[0])+1);
		$sections["frontend"]["http-in"]['redirect'][] = "redirect prefix http://$domain code 301 if { hdr(host) -i $naked_domain }";
		$sections["frontend"]["https-in"]['redirect'][] = "redirect prefix https://$domain code 301 if { hdr(host) -i $naked_domain }";
	}
	
	$acl = "acl is_http-$title hdr_end(host) -i $domain";
	$sections["frontend"]["http-in"]['acl'][] = $acl;
	$sections["frontend"]["https-in"]['acl'][] = $acl;

	$use_backend = "use_backend http-$title if is_http-$title";
	$sections["frontend"]["http-in"]['use_backend'][] = $use_backend;
	$sections["frontend"]["https-in"]['use_backend'][] = $use_backend;

	foreach([4200, 35729, 3000, 80] as $port)
	{
		if(array_key_exists($key = strtoupper("${name}_PORT_${port}_TCP_ADDR"), $_SERVER))
		{
			$addr_port = $_SERVER[$key].":$port";
			$sections["backend"]["http-$title"] = [
				"balance roundrobin",
				"option httpclose",
				
				"http-request set-header X-Forwarded-Port %[dst_port]",
    			"http-request add-header X-Forwarded-Proto https if { ssl_fc }",
				"server s1 $addr_port check",
			];
			if($forwardfor)
			{
				array_splice($sections["backend"]["http-$title"], 2, 0, array("option forwardfor"));
			}
			break;
		}
	}
}

$cfg = "/conf/haproxy.cfg";
if (is_file("/project/haproxy.cfg"))
{
	$cfg = "/project/haproxy.cfg";
}

$haproxy[] = file_get_contents($cfg);
foreach($sections as $s => $blocks)
{
	foreach($blocks as $title => $block)
	{
		$haproxy[] = $s." ".$title;
		foreach($block as $row)
		{
			$haproxy[] = "\t".(is_array($row) ? implode("\n\t", array_unique($row)) : $row);
		}
		$haproxy[] = "\n";
	}
}

$haproxy_cfg = implode("\n", $haproxy);
print_r($haproxy_cfg);
if ( ! $debug) {
	write_file("/conf/haproxy-output.cfg", $haproxy_cfg);
}