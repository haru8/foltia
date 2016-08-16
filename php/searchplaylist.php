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
$word = trim($word);
$nowdate = date('YmdHi');
$enddate = date('YmdHi', strtotime('+1 week'));

if ($word != '') {
	$numsql = '
      SELECT
        COUNT(foltia_program.tid) AS V
      FROM
        foltia_subtitle,
        foltia_program,
        foltia_m2pfiles
      WHERE (foltia_program.tid = foltia_subtitle.tid
        AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename)
        AND (foltia_program.title LIKE ? OR foltia_subtitle.subtitle LIKE ?)
      UNION
      SELECT
        COUNT(foltia_program.tid) AS V
      FROM foltia_mp4files
        LEFT JOIN foltia_subtitle ON foltia_mp4files.mp4filename = foltia_subtitle.pspfilename AND foltia_mp4files.tid = foltia_subtitle.tid
        LEFT JOIN foltia_program  ON foltia_mp4files.tid         = foltia_program.tid
      WHERE
        foltia_program.title LIKE ? OR foltia_subtitle.subtitle LIKE ?
    ';
	$selsql = '
      SELECT
        foltia_program.tid,
        foltia_program.title,
        foltia_subtitle.countno,
        foltia_subtitle.subtitle,
        foltia_m2pfiles.m2pfilename,
        foltia_subtitle.pid,
        foltia_subtitle.pspfilename,
        foltia_subtitle.startdatetime,
        foltia_subtitle.lengthmin
      FROM
        foltia_subtitle,
        foltia_program,
        foltia_m2pfiles
      WHERE (foltia_program.tid = foltia_subtitle.tid
        AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename)
        AND (foltia_program.title LIKE ? OR foltia_subtitle.subtitle LIKE ?)
      UNION
      SELECT
        foltia_program.tid,
        foltia_program.title,
        foltia_subtitle.countno,
        foltia_subtitle.subtitle,
        foltia_subtitle.m2pfilename,
        foltia_subtitle.pid,
        foltia_mp4files.mp4filename,
        foltia_subtitle.startdatetime,
        foltia_subtitle.lengthmin
      FROM foltia_mp4files
        LEFT JOIN foltia_subtitle ON foltia_mp4files.mp4filename = foltia_subtitle.pspfilename AND foltia_mp4files.tid = foltia_subtitle.tid
        LEFT JOIN foltia_program  ON foltia_mp4files.tid         = foltia_program.tid
      WHERE 
        foltia_program.title LIKE ? OR foltia_subtitle.subtitle LIKE ?
      GROUP BY foltia_mp4files.mp4filename
      ORDER BY foltia_subtitle.startdatetime DESC

    ';
	$searchword = '%' . $word . '%';
	$rows = sql_query($con, $numsql, 'DBクエリに失敗しました', array($searchword, $searchword, $searchword, $searchword));
	$row_sum = array();
	while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
		$row_sum[] += $row['V'];
	}
	$rs   = sql_query($con, $selsql, 'DBクエリに失敗しました', array($searchword, $searchword, $searchword, $searchword));
}

$words = array('EPG録画');
?>

  <p align="left"><font color="#494949" size="6">録画一覧検索</font></p>
  <hr size="4">

  <form name="searchepg" method="GET" action="searchplaylist.php" style="margin-bottom:20px;">
    検索: <input name="word" type="text" id="word" size="60" value="<?php echo "$word"; ?>"/><br><br>
    <input type="submit" value="検索">
  </form>
  <p>
  <?php foreach($words as $val): ?>
    <?php $val = trim($val); ?>
    <?php if ($val == ''): continue; endif ?>
    <a href="./searchplaylist.php?word=<?php echo urlencode($val)?>"><?php echo $val ?></a><br>
  <?php endforeach ?>
  </p>

  <?php if ($word): ?>
    <?php  echo implode(', ', $row_sum) ?> 件ヒットしました
  <?php endif ?>
  <?php if(@array_sum($row_sum) > 0): ?>
    <table border="0" cellpadding="0" cellspacing="2" width="100%" style="table-layout: fixed;">
      <tr>
        <th style="width:270px;">ファイル名</th>
        <th style="width:300px;">タイトル</th>
        <th style="width:50px;">話数</th>
        <th >サブタイ</th>
        <th style="width:60px;">Player</th>
        <th style="width:20px;">キャプ</th>
      </tr>
    <?php while($rowdata = $rs->fetch(PDO::FETCH_ASSOC)): ?>
    <?php
		//d($rowdata);
		$tid          = htmlspecialchars($rowdata['tid']);
		$title        = htmlspecialchars($rowdata['title']);
		$count        = htmlspecialchars($rowdata['countno']);
		$subtitle     = htmlspecialchars($rowdata['subtitle']);
		$fName        = htmlspecialchars($rowdata['m2pfilename']);
		$pid          = htmlspecialchars($rowdata['pid']);
		$mp4filename  = htmlspecialchars($rowdata['PSPfilename']);
		$lengthmin    = htmlspecialchars($rowdata['lengthmin']);

		$m2pExists = false;
		$m2pUrl    = $httpmediamappath . '/' . $fName;
		$m2ppath   = $recfolderpath . '/' . $fName;
		if (file_exists($m2ppath) && is_file($m2ppath) && filesize($m2ppath)) {
			$m2pExists = true;
		}

		$mp4Exists = false;
		$mp4Url    = $httpmediamappath . '/' . $tid . '.localized/mp4/' . $mp4filename;
		$mp4path   = $recfolderpath . '/' . $tid . '.localized/mp4/' . $mp4filename ;
		if (file_exists($mp4path) && is_file($mp4path)) {
			$mp4Exists = true;
			$mp4size = filesize($mp4path);
			$mp4size = round($mp4size / 1024 / 1024);
		}
    ?>
      <tr <?php echo $reservedClass ?>>
        <td><?php if ($m2pExists): ?><a href="<?php echo $m2pUrl ?>"><?php echo $fName ?></a><br><?php endif ?>
            <?php if ($mp4Exists): ?><a href="<?php echo $mp4Url ?>"><?php echo $mp4filename ?></a><?php endif ?></td>
      <?php if ($tid > 0): ?>
        <td><a href="http://cal.syoboi.jp/tid/<?php echo $tid ?>" target="_blank"><?php echo $title ?></a><br><a href="./showlibc.php?tid=<?php echo $tid ?>">[ライブラリ]</a></td>
      <?php else: ?>
        <td><?php echo $title ?><br><a href="./showlibc.php?tid=<?php echo $tid ?>">[ライブラリ]</a></td>
      <?php endif ?>
        <td><?php echo $count ?></td>
      <?php if ($tid > 0): ?>
        <td><a href="<?php echo "http://cal.syoboi.jp/tid/$tid/time#$pid" ?>" target="_blank"><?php echo $subtitle ?></a><br></td>
      <?php else: ?>
        <td><?php echo $subtitle ?><br></td>
      <?php endif ?>
      <?php if($pid) :?>
        <td><?php if ($mp4Exists):?><a href="./mp4player.php?p=<?php echo $pid ?>" target="_blank">Player</a><br><?php echo $mp4size ?>MB<br><?php endif ?><?php echo $lengthmin ?>分</td>
        <td><a href="./selectcaptureimage.php?pid=<?php echo $pid ?>">キャプ</a></td>
      <?php else: ?>
        <td><?php if ($mp4Exists):?><a href="./mp4player.php?f=<?php echo $mp4filename ?>" target="_blank">Player</a><br><?php echo $mp4size ?>MB<br><?php endif ?>
        <td><a href="./selectcaptureimage.php?f=<?php echo $mp4filename ?>">キャプ</a></td>
      <?php endif ?>
      </tr>
    <?php endwhile ?>
    </table>
  <?php endif ?>

</body>
</html>

