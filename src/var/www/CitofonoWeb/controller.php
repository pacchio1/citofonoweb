<?php
require_once ("framework.php");
require_once ("functions.php");

function checkIP($x){
	return filter_var($x,FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

$head='<meta http-equiv="refresh" content="3; url='.$_SERVER['HTTP_REFERER'].'" />';

switch ($_REQUEST['act']){
	case 'net-if':
		if (!checkIP($_POST['ip']))
			die('The specified IP address is invalid');
		if(!checkIP($_POST['nmask']))
			die('The specified netmask is invalid');
		if (!checkIP($_POST['gw']))
			die('The specified gateway is invalid');
		
		$ip=$_POST['ip'];
		$nmask=$_POST['nmask'];
		$gw=$_POST['gw'];
		
		$iface=config::iface;
		$data=<<<EOF
# Used by ifup(8) and ifdown(8). See the interfaces(5) manpage or
# /usr/share/doc/ifupdown/examples for more information.

auto lo
iface lo inet loopback

auto $iface
allow-hotplug eth0
	iface $iface inet static
	address $ip
	netmask $nmask
	gateway $gw
EOF;

		file_put_contents('/etc/network/interfaces', $data);
		
		#Se fallisce l'attivazione, riavvia tutto
		#Tira giù la rete
		if (tools::exec("ifdown --force $iface; ifup --force $iface",'retval')!=0){
			`reboot`;
		}
		$page='<p>Configuration completed.</p>';
	break;

	case 'net-dns':
		if (!checkIP($_POST['dns1']))
			die('The specified dns1 address is invalid');
		if(!checkIP($_POST['dns2'])&&$_POST['dns2']!='')
			die('The specified dns2 is invalid');
		if (!checkIP($_POST['dns3'])&&$_POST['dns3']!='')
			die('The specified dns3 is invalid');
			
		if (!preg_match('/[a-z]+\.[a-z]+(\.[a-z]+)*/',$_POST['search'])&&$_POST['search']|='')
			die('Search not valid');
		if (!preg_match('/[a-z]+\.[a-z]+(\.[a-z]+)*/',$_POST['domain'])&&$_POST['domain']|='')
			die('Domain not valid');

		$data="# Generated by web interface\n";
		if ($_POST['domain'])
			$data.='domain '.$_POST['domain']."\n";
		if ($_POST['search'])
			$data.='search '.$_POST['search']."\n";
			
		$data.='nameserver '.$_POST['dns1']."\n";
		if ($_POST['dns2'])
			$data.='nameserver '.$_POST['dns2']."\n";
		if ($_POST['dns3'])
			$data.='nameserver '.$_POST['dns3']."\n";

		file_put_contents('/etc/resolv.conf', $data);
		
		$page='<p>Configuration completed.</p>';
	break;
		
	case 'changepw':
		$page='<h1>Change password</h1>';
		if ($_POST['pw']==$_POST['rpw']){
			if (trim(@$_POST['user'])=='')
				$user=$_SERVER['PHP_AUTH_USER'];
			else
				$user=$_POST['user'];
				
			$file=`cat /etc/lighttpd/lighttpd-plain.user | egrep -v '^$user:'`;
			$file.="$user:".$_POST['pw']."\n";
			
			$p=file_put_contents('/etc/lighttpd/lighttpd-plain.user', $file);
			if ($p)
				$page.='<br/>The password has been changed.';
			else
				$page.="Can't write file.";
		}
		else 
			$page.='The two passwords don\'t match.';
	break;
	
		
	case 'dateset':
		$page='<h1>Change system date</h1>';
		$date=explode('/',$_POST['date']);
		$time=explode(':',$_POST['time']);
		if (checkdate($date[1],$date[0],$date[2]) && preg_match('/[0-5][0-9]:[0-5][0-9]:[0-5][0-9]/',$_POST['time'])){
			$result=tools::exec('date '.$date[1].$date[0].$time[0].$time[1].$date[2].'.'.$time[2],'retval');
			if ($result != 0)
				$page.='Failed to change system date.';
			else
				$page='System date has been set.';
		}
		else
			$page.='Wrong parameters.';
		if (file_exists('/usr/share/zoneinfo/'.$_POST['timezone'])){
			$tz=$_POST['timezone'];
			if (tools::exec("cp -a /usr/share/zoneinfo/$tz /etc/localtime",'retval')!=0)
				$page.='<br/>Error editing "/etc/localtime".';
				
			if (!file_put_contents('/etc/timezone',$tz."\n"))
				$page.='<br/>Error editing "/etc/timezone".';
			else	
				$page.='<br/>Timezone has been set.';
		}
		else
			$page.='<br>Wrong Timezone.';
	break;
	
	case 'ntpget':
		$ntpstring=implode(' || ntpdate ',explode(',',config::ntpservers));
		$result=tools::exec('LC_ALL=C ntpdate '.$ntpstring,'all',1);
		$page="<h1>NTP Date</h1><h2>Info: $result</h2>";
	break;
	
	case 'ntpset':
		$s=array();
		if (preg_match('/^[0-9\.a-zA-Z_\-]+$/', $_POST['ntp1']))
			$s[]=$_POST['ntp1'];
		if (preg_match('/^[0-9\.a-zA-Z_\-]+$/', $_POST['ntp2']))
			$s[]=$_POST['ntp2'];
		if (preg_match('/^[0-9\.a-zA-Z_\-]+$/', $_POST['ntp3']))
			$s[]=$_POST['ntp3'];
			
		if (count($s)==3){
			$servers=implode(',',$s);
			$conf=file_get_contents(dirname(__FILE__).'/config.inc.php');
			$conf=preg_replace('/^(\s+const ntpservers=)(.*)$/m', '${1}'."'$servers';", $conf);
			file_put_contents(dirname(__FILE__).'/config.inc.php', $conf);
			$page='<h1>NTP set</h1><p>Configuration changed.</p>';
		}
		else{
			$page='<h1>NTP set</h1><p>Invalid address.</p>';
		}
	break;
		
	case 'restart':
		$head='<script type="text/javascript">
			var count=60;
			function countdown(){
				if (count==0)
					document.location.href="index.php";
				$("#cdown").html(count);
				if (count>0)
					count--;
			}
			setInterval(countdown,1000);
		</script>';
		tools::exec("echo 'sleep 1; reboot' | at now");
		$page='<h1>Restarting...</h1><h2>You will be redirected to the home page in <span id="cdown">60</span> seconds.</h2>';
		break;
		
	case 'shutdown':
		$head='';
		$result=tools::exec('shutdown -h now');
		$page='<h1>Halting...</h1><h2>Goodbye.</h2><p>'.$result.'</p>';
	break;
	
	case 'pkgupdate':
		$head='';
		$page="<h1>Updated packages list</h1>";
		$page.=tools::exec("apt-get update");
	break;
	
	case 'pkgupgrade':
		$head='';
		$page="<h1>Upgraded packages</h1>";
		$page.=tools::exec("apt-get upgrade --assume-no");
		$page.='<p>Open a console to upgrade the system.</p>';
	break;
		
	case 'fwupdate':
		$head='';
		$page="<h1>Upgrading firmware</h1>";
		$page.=tools::exec("mount /boot");
		$page.=tools::exec(utils::pwd()."scripts/rpi-update");
	break;
	
	case 'fwscript':
		$head='';
		$url="https://raw.github.com/Hexxeh/rpi-update/master/rpi-update";
		$page="<h1>Updated script</h1>";
		@mkdir(utils::pwd()."scripts");
		$page.=tools::exec("wget $url -O ".utils::pwd()."scripts/rpi-update");
		$page.=tools::exec("chmod -v +x ".utils::pwd()."scripts/rpi-update");
	break;
	
	case 'inputedit':
		$s=array();
		if (preg_match('#^/dev/input/by\-id/[a-zA-Z0-9\-\_\.]+$#', $_POST['dev'])){
			if (file_exists($_POST['dev'])){
				$conf=file_get_contents(dirname(__FILE__).'/config.inc.php');
				$conf=preg_replace('/(const device=)(.*)/', '${1}'."'".$_POST['dev']."';", $conf);
				file_put_contents(dirname(__FILE__).'/config.inc.php', $conf);
				$page='<h1>Input device change</h1><p>Device has been set.</p>';
			}
			else{
				$page='<h1>Input device change</h1><p>Device not found.</p>';
			}
		}
		else{
			$page='<h1>Input device change</h1><p>Invalid device.</p>';
		}
	break;
	
	case 'service':
		$page="<h1>Service management</h1>";
		if (@$_POST['signal']=='Start'){
			$page.=tools::exec("/etc/init.d/badge_daemon start");
			$page.='The service has been started.';
		}
		elseif (@$_POST['signal']=='Stop'){
			$page.=tools::exec("/etc/init.d/badge_daemon stop");
			$page.='The service has been stopped.';
		}
		elseif (@$_POST['signal']=='Restart'){
			$page.=tools::exec("/etc/init.d/badge_daemon restart");
			$page.='The service has been restarted.';
		}
		elseif (@$_POST['signal']=='Kill'){
			$page.=tools::exec("kill -9".file_get_contents('/var/run/badge_daemon.pid'));
			$page.=tools::exec("killall badge_listener; killall -9 badge_listener");
			$page.='The service has been killed.';
		}
	break;
	
	case 'addkey':
		$key=$_POST['key'];
		$k=explode(' ', $key);
		if (count($k)<3){
			$page='<h1>Invalid key</h1><p>The key you entered is invalid</p>';
		}
		else{
			@mkdir('/root/.ssh/');
			if (!strstr(file_get_contents('/root/.ssh/authorized_keys'),$k[1])){
				if (file_put_contents('/root/.ssh/authorized_keys', $key."\n", FILE_APPEND))
					$page='<h1>Key added</h1><p>The key has been added succesfuly.</p>';	
				else
					$page='<h1>Unkown error</h1><p>The key can\'t be added.</p>';
			}
			else
				$page='<h1>Duplicate key</h1><p>The key you entered is already present.</p>';
		}
	break;
	
	case 'remkey':
		$key=base64_decode($_GET['key']);
		$k=explode(' ', $key);
		if (count($k)<3){
			$page='<h1>Invalid key</h1><p>The key you entered is invalid</p>';
		}
		else{
			$list=file('/root/.ssh/authorized_keys');
			$handle=fopen('/root/.ssh/authorized_keys','w');
			foreach ($list as $l){
				if ($l!=$key && $l!="\n"){
					fwrite($handle, $l."\n");
				}
			}
			fclose($handle);
			$page='<h1>Key removed</h1><p>The key has removed succesfully.</p>';
		}
	break;
	
	case 'regenerate':
		$head='';
		$page="<h1>Regenerate keys</h1>";
		$page.=tools::exec("rm /etc/ssh/ssh_host_*");
		$page.=tools::exec("ssh_keygen -A");
		$page.=tools::exec("/etc/init.d/ssh restart");
		$page.='The keys have been regenerated.';
	break;
	
	case 'chbinding':
		$head='';
		$page="<h1>Change bindings</h1>";

		$conf=file_get_contents(dirname(__FILE__).'/config.inc.php');
		
		if (in_array($_POST['statusled'],array(4,17,18,21,22,23,24,25)))
			$conf=preg_replace('/^(\s+const =)(.*)$/m', '${1}'."'".$_POST['statusled']."';", $conf);
		else
			$page.='<p>Status LED invalid.</p>';
		if (in_array($_POST['doorpin'],array(4,17,18,21,22,23,24,25)))
			$conf=preg_replace('/^(\s+const =)(.*)$/m', '${1}'."'".$_POST['doorpin']."';", $conf);
		else
			$page.='<p>Door pin invalid.</p>';
		if (in_array($_POST['buzzer'],array(4,17,18,21,22,23,24,25)))
			$conf=preg_replace('/^(\s+const =)(.*)$/m', '${1}'."'".$_POST['buzzer']."';", $conf);
		else
			$page.='<p>Buzzer pin invalid.</p>';
		if (in_array($_POST['redled'],array(4,17,18,21,22,23,24,25)))
			$conf=preg_replace('/^(\s+const =)(.*)$/m', '${1}'."'".$_POST['redled']."';", $conf);
		else
			$page.='<p>Red LED invalid.</p>';
		if (in_array($_POST['buttonpin'],array(4,17,18,21,22,23,24,25)))
			$conf=preg_replace('/^(\s+const =)(.*)$/m', '${1}'."'".$_POST['buttonpin']."';", $conf);
		else
			$page.='<p>Button pin invalid.</p>';
		
		file_put_contents(dirname(__FILE__).'/config.inc.php', $conf);
		$page.='<p>Configuration changed</p><h2>Restart the service to apply the new bindings</h2>';
	break;

	case 'chname':
		$head='<meta http-equiv="refresh" content="1; url='.$_SERVER['HTTP_REFERER'].'" />';
		$page="<h1>Change Device Name</h1>";

		$conf=file_get_contents(dirname(__FILE__).'/config.inc.php');
		
		$conf=preg_replace('/^(\s+const name=)(.*)$/m', '${1}'."'".addslashes($_POST['devname'])."';", $conf);
		
		file_put_contents(dirname(__FILE__).'/config.inc.php', $conf);
		$page.='<p>Configuration changed. The new name is: '.$_POST['devname'].'</p>';
	break;

	default:
		die('<h1>Unknown request</h1><p><a href="'.utils::webroot().'">Back to homepage</a></p>');
	break;
}

$cms=new cms("Settings",$head);
echo $page;
?>
