<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

searchepg.php

目的
EPG番組表を検索します

引数
無し

 DCC-JPL Japan/foltia project

*/

include("./foltialib.php");
$con = m_connect();

if ($useenvironmentpolicy == 1) {
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header("WWW-Authenticate: Basic realm=\"foltia\"");
		header("HTTP/1.0 401 Unauthorized");
		redirectlogin();
		exit;
	} else {
		login($con,$_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);
	}
} //end if login
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="Content-Style-Type" content="text/css">
  <link rel="stylesheet" type="text/css" href="graytable.css">
  <title>foltia:EPG予約:$epgid</title>
</head>

<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >

<?php
printhtmlpageheader();

$word = getgetform('word');
$nowdate = date('YmdHi');
$enddate = date('YmdHi', strtotime('+1 week'));

if ($word != '') {
	$numsql = 'count(*)';
	$selsql = '*';
	$query = "
	  SELECT 
  	    %COL%
  	    FROM foltia_epg
		  LEFT JOIN foltia_station ON foltia_epg.ontvchannel = foltia_station.ontvcode
  	    WHERE (startdatetime >= ?
	      AND enddatetime    <= ?)
          AND (startdatetime < enddatetime)
  	      AND ((epgtitle LIKE ?) OR (epgdesc  LIKE ?))
  	    ORDER BY startdatetime
	";
	$numsql = str_replace('%COL%', $numsql, $query);
	$selsql = str_replace('%COL%', $selsql, $query);
	$searchword = '%' . $word . '%';
	$rows = sql_query($con, $numsql, 'DBクエリに失敗しました', array($nowdate, $enddate, $searchword, $searchword));
	$row  = $rows->fetchColumn();
	$rs   = sql_query($con, $selsql, 'DBクエリに失敗しました', array($nowdate, $enddate, $searchword, $searchword));
}

function reserveCheckClass($con, $startdatetime, $enddatetime, $stationid)
{
	$reserve      = reserveCheck($con, $startdatetime, $enddatetime, $stationid);
	$reservecheck = searchStartEndTime($reserve, $startdatetime, $enddatetime);
	$reservedClass = '';
	if ($reservecheck == 1) {
		$reservedClass = ' class="reservedtitle"';
	} else if($reservecheck == 2) {
		$reservedClass = ' class="reservedtitle"';
	}
	return $reservedClass;
}
?>

  <p align="left"><font color="#494949" size="6">番組検索</font></p>
  <hr size="4">

  <form name="searchepg" method="GET" action="searchepg.php" style="margin-bottom:20px;">
    検索: <input name="word" type="text" id="word" size="60" value="<?php echo "$word"; ?>"/><br><br>
    <input type="submit" value="検索">
  </form>

  <?php if($row > 0): ?>
    <?php  echo $row ?> 件ヒットしました
    <table style="margin-bottom:10px; table-layout: fixed;">
      <tr>
        <th style="width:95px;" rowspan="2">epgid</th>
        <th style="width:95px;" rowspan="2">局</th>
        <th style="width:160px;">開始</th>
        <th style="width:60px;" rowspan="2">尺(分)</th>
        <th rowspan="2">番組名</th>
        <th rowspan="2">内容</th>
      </tr>
      <tr>
        <th style="width:160px;">終了</th>
      </tr>
    <?php while($epg = $rs->fetch(PDO::FETCH_ASSOC)): ?>
    <?php $reservedClass = reserveCheckClass($con, $epg['startdatetime'], $epg['enddatetime'], $epg['stationid']); ?>
      <tr <?php echo $reservedClass ?>>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><a href="./reserveepg.php?epgid=<?php echo htmlspecialchars($epg['epgid']) ?>"><?php echo htmlspecialchars($epg['epgid']) ?></a></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($epg['stationname']) ?>(<?php echo htmlspecialchars($epg['stationid']) ?>)</td>
        <td><?php echo htmlspecialchars(foldate2print($epg['startdatetime'])) ?></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo htmlspecialchars($epg['lengthmin']) ?></td>
        <td rowspan="2"><?php echo htmlspecialchars($epg['epgtitle']) ?></td>
        <td rowspan="2"><?php echo htmlspecialchars($epg['epgdesc']) ?></td>
      </tr>
      <tr <?php echo $reservedClass ?>>
        <td><?php echo htmlspecialchars(foldate2print($epg['enddatetime'])) ?></td>
      </tr>
    <?php endwhile ?>
    </table>
  <?php endif ?>

</body>
</html>

