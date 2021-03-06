<?php
# Program: System-Snapshot.php
# Programmer: Remo Rickli

$printable = 1;
$exportxls = 0;

include_once ("inc/header.php");

$_GET = sanitize($_GET);
$_POST = sanitize($_POST);
$del = isset($_GET['del']) ? preg_replace('/[^\w+\.-]/','',$_GET['del']) : '';
$sel = isset($_GET['sel']) ? preg_replace('/[^\w+\.-]/','',$_GET['sel']) : '';

$add = isset($_POST['add']) ? preg_replace('/[^\w+\.-]/','',$_POST['add']) : '';
$usr = isset($_POST['usr']) ? preg_replace('/[^\w+\.-]/','',$_POST['usr']) : '';
$psw = isset($_POST['psw']) ? preg_replace('/[\s+]/','',$_POST['psw']) : '';

$dbn = explode("_",$dbname);

?>

<h1>System Snapshot</h1>

<?php  if( !isset($_GET['print']) ) { ?>

<form method="post" action="<?= $self ?>.php">
<table class="content" >
<tr class="bgmain">
<td class="ctr s">
	<a href="<?= $self ?>.php"><img src="img/32/<?= $selfi ?>.png" title="<?= $self ?>"></a>
</td>
<td>
	<img src="img/16/abc.png" title=" <?= $namlbl ?>">
	<?= $dbn[0] ?>_<input type="text" name="add" class="m" value="<?= date("YmdHi") ?>">
</td>
<td>
	<img src="img/16/ucfg.png" title="DB Admin"> <input type="text" name="usr" class="m"><p>
	<img src="img/16/loko.png" title="Password"> <input type="password" name="psw" class="m">
</td>
<td class="ctr s">
	<input type="submit" class="button" value="<?= $addlbl ?>">
</td>
</tr>
</table>
</form>
<p>
<?php } ?>

<?php
if($isadmin and $del){
	$link = DbConnect($dbhost, $dbuser, $dbpass, $dbname);
	DbQuery(GenQuery($del,"p"), $link);
	DbClose($link);
}elseif($isadmin and $add){
	echo "<div class=\"textpad code pre txta\">";
	echo "<h3>$coplbl DB $dbname -> $dbn[0]_$add</h3>";
	ob_end_flush();

	$nedihost = $dbhost;#TODO add support for non localhost!
	if( $backend == 'mysql'){#TODO fix/finish....move to native PHP?
		$usa  = ($usr)?"-u$usr":"";
		$pwa  = ($psw)?"-p$psw":"";
		system("mysqladmin create $dbn[0]_$add -h $dbhost $usa $pwa 2>&1 && mysqldump -h $dbhost $usa $pwa $dbname | mysql -h $dbhost $usa $pwa $dbn[0]_$add", $stat);
	}elseif( $backend == 'Pg'){
		$usa  = ($usr)?"-U$usr":"";
		$pwa  = ($psw)?"export PGPASSWORD=$psw;":"";
		system("$pwa createdb -O $dbuser -T $dbname $dbn[0]_$add -h $dbhost $usa 2>&1", $stat);
	}
	if($stat){
		echo "<h4>$errlbl $coplbl</h4>\n";
		if( $backend == 'Pg') echo "Syslog & Monitoring $stco[100]?";
	}else{
		echo "<h5>$coplbl OK</h5>\n";
		ob_end_flush();
		if( $backend == 'mysql'){
			$dbpw = ($dbpass)?"IDENTIFIED BY '$dbpass'":'';
			system("echo \"GRANT ALL PRIVILEGES ON $dbn[0]_$add.* TO '$dbuser'@'$nedihost' $dbpw\" | mysql $usa $pwa 2>&1", $stat);
		}
		if($stat){
			echo "<h4>$errlbl $acslbl</h4>\n";
		}else{
			echo "<h5>$acslbl OK</h5>\n";
		}
	}
	echo "</div>";
}elseif($sel){
	if( strpos($sel,"_") ){
		$_SESSION['snap'] = $sel;								# Used in ReadConf() to select DB and in header to change N-logo
		$_SESSION['gsiz'] = 0;
		$_SESSION['vol']  = 0;
	}else{
		$link = DbConnect($dbhost, $dbuser, $dbpass, $sel);
		$qry = GenQuery('users','s','*','','',array('usrname'),array('='),array($_SESSION['user']) );
		$res = DbQuery($qry,$link);
		$usr = DbFetchRow($res);
		$_SESSION['vol']  = ($usr[10] & 3)*33;
		$_SESSION['gsiz'] = $usr[13] & 7;
		unset($_SESSION['snap']);								# Base DB, reset session
		DbFreeResult($res);
		DbClose($link);
	}
	$dbname = $sel;
}
?>
<h2>Snapshot <?= $lstlbl ?></h2>

<table class="content" >
	<tr class="bgsub">
		<th colspan="2">
			<img src="img/16/db.png"><br>
			DB <?= $namlbl ?>
		</th>
		<th>
			<img src="img/16/dev.png"><br>
			Devices
		</th>
		<th>
			<img src="img/16/conf.png"><br>
			<?= $cfglbl ?></th>
		<th>
			<img src="img/16/nods.png"><br>
			Nodes</th>
		<th>
			<img src="img/16/radr.png"><br>
			<?= $laslbl ?> <?= $dsclbl ?>
		</th>
		<th class="s">
			<img src="img/16/cog.png"><br>
			<?= $cmdlbl ?>
		</th>
	</tr>
<?php

$row = 0;
$link = DbConnect($dbhost, $dbuser, $dbpass, $dbname);
$res  = DbQuery(GenQuery("$dbn[0]%","b","LIKE"), $link);
while($ss = DbFetchRow($res)){
	if ($row % 2){$bg = "txta"; $bi = "imga";}else{$bg = "txtb"; $bi = "imgb";}
	$row++;
	if($dbname == $ss[0]){
		$inactive = 0;
	}else{
		$inactive = 1;
	}
	$slnk = DbConnect($dbhost, $dbuser, $dbpass, $ss[0]);
	$devs = DbFetchRow(DbQuery(GenQuery('devices','s','count(*)'), $slnk));
	$cfgs = DbFetchRow(DbQuery(GenQuery('configs','s','count(*)'), $slnk));
	$nods = DbFetchRow(DbQuery(GenQuery('nodes','s','count(*)'), $slnk));
	$fdis = DbFetchRow(DbQuery(GenQuery('system','s','value','','',array('name'),array('='),array('first') ), $slnk));
	DbClose($slnk);

	TblRow($bg);
	echo "\t\t<td class=\"$bi ctr xs\">\n";
	if($inactive){											# Only allow activate inactive DBs
?>
			<a href="?sel=<?= urlencode($ss[0]) ?>"><img src="img/16/bcls.png" title="DB <?= $sellbl ?>"></a>
<?php
	}else{
		echo "\t\t\t<img src=\"img/16/walk.png\" title=\"DB $stco[100]\">\n";
	}
?>
		</td>
		<td class="b">
			<?= $ss[0] ?>

		</td>
		<td>
			<?= Bar($devs[0],'lvl100','mi') ?> <?= $devs[0] ?>

		</td>
		<td>
			<?= Bar($cfgs[0],'lvl150','mi') ?> <?= $cfgs[0] ?>

		</td>
		<td>
			<?= Bar($nods[0],'lvl50','mi') ?> <?= $nods[0] ?>

		</td>
		<td>
			<?= date($_SESSION['timf'],$fdis[0]) ?>

		</td>
		<td class="ctr s">
<?php
	if( $isadmin and $inactive and strpos($ss[0],"_") ){						# Only allow to delete inactive snapshots
?>
			<a href="?del=<?= urlencode($ss[0]) ?>"><img src="img/16/bcnl.png" onclick="return confirm('<?= $dellbl ?>, <?= $cfmmsg ?>')" title="<?= (($verb1)?"$dellbl Snapshot":"Snapshot $dellbl") ?>"></a>
<?php
	}
	echo "\t\t</td>\n\t</tr>\n";
}
TblFoot("bgsub", 7, "$row DBs".(($ord)?", $srtlbl: $ord":"").(($lim)?", $limlbl: $lim":"") );

include_once ("inc/footer.php");
?>
