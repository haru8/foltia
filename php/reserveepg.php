<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

reserveepg.php

目的
EPG録画予約ページを表示します。

引数
epgid:EPG番組ID

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

<?php
$epgid = getgetnumform(epgid);
if ($epgid == "") {
	print "	<title>foltia:EPG予約:Error</title></head>\n";
	die_exit("登録番組がありません<BR>");
}
print "	<title>foltia:EPG予約:$epgid</title>
</head>\n";


$now = date("YmdHi");

// タイトル取得
$query = "
  SELECT
    epgid,
    startdatetime,
    enddatetime,
    lengthmin,
    ontvchannel,
    epgtitle,
    epgdesc,
    epgcategory,
    stationname,
    stationrecch,
    stationid
  FROM
    foltia_epg,
    foltia_station
  WHERE epgid = ?
    AND foltia_station.ontvcode = foltia_epg.ontvchannel
"; // 4812

$rs = sql_query($con, $query, "DBクエリに失敗しました",array($epgid));
$rowdata = $rs->fetch();
if (! $rowdata) {
	die_exit("登録番組がありません<BR>");
}

?>
<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >

<?php
printhtmlpageheader();
?>

  <p align="left"><font color="#494949" size="6">番組予約</font></p>
  <hr size="4">
EPGから下記番組を録画予約します。 <br><br>


<?php
$stationjname	= htmlspecialchars($rowdata[8]);
$startfoltime	= htmlspecialchars($rowdata[1]);
$startprinttime	= foldate2print($startfoltime);
$endfoltime		= htmlspecialchars($rowdata[2]);
$endprinttime	= foldate2print($endfoltime);
$lengthmin		= htmlspecialchars($rowdata[3]);
$recch			= htmlspecialchars($rowdata[9]);
$progname		= htmlspecialchars($rowdata[5]);
$progname		= z2h($progname);
$progdesc		= htmlspecialchars($rowdata[6]);
$progdesc		= z2h($progdesc);
$progcat		= htmlspecialchars(z2h($rowdata[7]));

if ($progcat == "information") {
	$progcat =  '情報';
} elseif ($progcat == "anime") {
	$progcat =  'アニメ・特撮';
}elseif ($progcat == "news") {
	$progcat =  'ニュース・報道';
} elseif ($progcat == "drama") {
	$progcat =  'ドラマ';
} elseif ($progcat == "variety") {
	$progcat =  'バラエティ';
} elseif ($progcat == "documentary") {
	$progcat =  'ドキュメンタリー・教養';
} elseif ($progcat == "education") {
	$progcat =  '教育';
} elseif ($progcat == "music") {
	$progcat =  '音楽';
} elseif ($progcat == "cinema") {
	$progcat =  '映画';
} elseif ($progcat == "hobby") {
	$progcat =  '趣味・実用';
} elseif ($progcat == "kids") {
	$progcat =  'キッズ';
} elseif ($progcat == "sports") {
	$progcat =  'スポーツ';
} elseif ($progcat == "etc") {
	$progcat =  'その他';
} elseif ($progcat == "stage") {
	$progcat =  '演劇';
}

$epgid = $epgid ;
$stationid = htmlspecialchars($rowdata[10]);

if ($now > $endfoltime) {
	print "この番組はすでに終了しているため、録画されません。<br>";
} elseif($now > $startfoltime) {
	print "この番組はすでに放映開始しているため、録画されません。<br>";
} elseif($now > ($startfoltime - 10) ) {
	print "この番組は放映直前なため、録画されない可能性があります。<br>";
}

print "<form name=\"recordingsetting\" method=\"POST\" action=\"reserveepgcomp.php\">\n";

$chkoverwrap  = reserveCheck($con, $startfoltime, $endfoltime, $stationid);
$reserveCheck = searchStartEndTime($chkoverwrap, $startfoltime, $endfoltime);
if (! $chkoverwrap) {
	// 重複なし
	print "<input type=\"submit\" value=\"予約\" ><br>\n";
} else {
	if ($reserveCheck == 1) {
		print "<strong>この番組は既に予約済みです。</strong><br>\n";
	} else if ($reserveCheck == 2) {
		print "<strong>この番組は既に一部予約済みです。</strong><br>\n";
	}
	print '<table width="60%" style="width: 60%">';
	print '<tr><th>削除</th><th>予約番組名</th><th>開始</th><th>終了</th><th>尺(分)</th></tr>';
	foreach ($chkoverwrap as $item) {
		$prereservedtitle = $item['title'];
		$stationname = $item['stationname'];
		$tid = $item['tid'];
		$pid = $item['pid'];
		print "<tr>";
		if ($tid > 1) {
			echo "<td><a href=\"delreserve.php?tid=$tid&sid=$stationid\">予約削除</a></td><td><a href=\"http://cal.syoboi.jp/tid/$tid/time/#$stationname\" target=\"_blank\">$prereservedtitle</a></td><td>" . foldate2print($item['startdatetime']) . "</td><td>" . foldate2print($item['enddatetime']) . "</td><td>" . $item['lengthmin'] . "</td>\n";
		} else {
			echo "<td><a href=\"delepgp.php?pid=$pid\">予約削除</a></td><td>EPG録画</td><td>" . foldate2print($item['startdatetime']) . "</td><td>" . foldate2print($item['enddatetime']) . "</td><td>" . $item['lengthmin'] . "</td>\n";
		}
		print "</tr>";
	}
	print "</table>";
	print "<br><input type=\"submit\" value=\"それでも予約\" ><br>\n";
}

print "<table width=\"100%\" border=\"0\">
    <tr><th style=\"width:130px\">放送局</th><td>$stationjname</td></tr>
    <tr><th>放送開始</th><td>$startprinttime</td></tr>
    <tr><th>放送終了</th><td>$endprinttime</td></tr>
    <tr><th>尺(分)</th><td>$lengthmin</td></tr>
    <tr><th>放送チャンネル</th><td>$recch</td></tr>
    <tr><th>番組名</th><td><a href=\"./showlibc.php?tid=$tid\">$progname</a></td></tr>
    <tr><th>内容</th><td>$progdesc</td></tr>
    <tr><th>ジャンル</th><td>$progcat</td></tr>
    <tr><th>番組ID</th><td>$epgid</td></tr>
    <tr><th>局コード</th><td>$stationid</td></tr>
    <tr><th>TID</th><td>$tid</td></tr>
</table>

<input type=\"hidden\" name=\"epgid\" value=\"$epgid\" />
<input type=\"hidden\" name=\"stationid\" value=\"$stationid\" />
<input type=\"hidden\" name=\"subtitle\" value=\"$progname $progdesc\" />
<input type=\"hidden\" name=\"startdatetime\" value=\"$startfoltime\" />
<input type=\"hidden\" name=\"enddatetime\" value=\"$endfoltime\" />
<input type=\"hidden\" name=\"lengthmin\" value=\"$lengthmin\" />

";

    
?>

</FORM>


</body>
</html>
