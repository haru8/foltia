<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/


目的
blogツール、スターライトブレイカー用キャプ選択画面サンプル

引数
pid:PID

mplayer -ss 00:00:10 -vo jpeg:outdir=/home/foltia/php/tv/691.localized/img/6/ -vf crop=702:468:6:6,scale=160:120,pp=lb  -ao null -sstep 14  -v 3 /home/foltia/php/tv/691-6-20060216-0130.m2p

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
        login($con, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    }
} // end if login

$pid  = getgetnumform('pid');
$file = getgetform('f');

if ($pid == "") {
    header("Status: 404 Not Found", TRUE, 404);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Content-Style-Type" content="text/css">
<link rel="stylesheet" type="text/css" href="graytable.css">
<title>Starlight Breaker -キャプチャ画像選択</title>
<script src="http://images.apple.com/main/js/ac_quicktime.js" language="JavaScript" type="text/javascript"></script>
</head>
<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
<div align="center">

<?php
printhtmlpageheader();

if ($pid != "") {
    $query = "
      SELECT
        foltia_program.tid,
        stationname,
        foltia_program.title,
        foltia_subtitle.countno,
        foltia_subtitle.subtitle,
        foltia_subtitle.startdatetime,
        foltia_subtitle.lengthmin,
        foltia_subtitle.pid ,
        foltia_subtitle.m2pfilename,
        foltia_subtitle.pspfilename
      FROM
        foltia_subtitle,
        foltia_program,
        foltia_station
      WHERE foltia_program.tid = foltia_subtitle.tid
        AND foltia_station.stationid = foltia_subtitle.stationid
        AND foltia_subtitle.pid = ?
";
    $rs = sql_query($con, $query, "DBクエリに失敗しました", array($pid));
    $rowdata = $rs->fetch();

    if (! $rowdata) {
        $query = "
          SELECT
            foltia_program.tid,
            foltia_program.tid,
            foltia_program.title,
            foltia_subtitle.countno,
            foltia_subtitle.subtitle,
            foltia_subtitle.startdatetime,
            foltia_subtitle.lengthmin,
            foltia_subtitle.pid ,
            foltia_subtitle.m2pfilename,
            foltia_subtitle.pspfilename
          FROM foltia_subtitle , foltia_program
          WHERE foltia_program.tid = foltia_subtitle.tid
            AND foltia_subtitle.pid = ?
        ";

        $rs = sql_query($con, $query, "DBクエリに失敗しました" ,array($pid));
        $rowdata = $rs->fetch();
    }

    $rowdata[1] = "";
} else if ($file != "") {
    $filesplit = explode('-', htmlspecialchars($file));
    $tid       = $filesplit[1];
    $num       = $filesplit[2];
    $date      = $filesplit[3];
    if ($tid != '0') {
        list($time, $ext)   = explode('\.', $filesplit[4]);
    } else {
        $time               = $filesplit[4];
        list($ch, $ext)     = explode('\.', $filesplit[5]);
    }
    $rowdata   = array();
    $rowdata[0]= $tid;
    $rowdata[1]= '';
    $rowdata[2]= 'title';
    $rowdata[3]= $num;
    $rowdata[4]= 'subtitle';
    $rowdata[5]= $filesplit[3] . $time;
    $rowdata[6]= 'min';
    if ($tid != '0') {
        $rowdata[8] = $filesplit[1] . '-' . $num . '-' . $date . '-' . $time . '.m2t';
    } else {
        $rowdata[8]= $filesplit[1] . '-' . $num . '-' . $date . '-' . $time . '-' . $ch . '.m2t';
    }
    $rowdata[9] = htmlspecialchars($file);

} else {
    print "画像がありません。<br></body></html>";
    exit;
} // end if (! $rowdata)

print "  <p align=\"left\"><font color=\"#494949\" size=\"6\">キャプチャ画像</font></p>
  <hr size=\"4\">
<p align=\"left\">";
print "<a href = \"http://cal.syoboi.jp/tid/$rowdata[0]/\" target=\"_blank\">";
print htmlspecialchars($rowdata[2]) . "</a> " ;
print htmlspecialchars($rowdata[3]) . " ";
$tid = $rowdata[0];
if ($tid > 0) {
    print "<a href = \"http://cal.syoboi.jp/tid/$tid/time#$pid\" target=\"_blank\">";
    print htmlspecialchars($rowdata[4]) . "</a> ";
} else {
    print htmlspecialchars($rowdata[4]) . " ";
}
print '<br>';
print htmlspecialchars($rowdata[1]) . " ";
print htmlspecialchars($rowdata[6]) . "分 ";
print htmlspecialchars(foldate2print($rowdata[5]));
print '<br>';

$mp4filename = $rowdata[9];

print "再生:<A HREF=\"$httpmediamappath/$tid.localized/mp4/$mp4filename\" target=\"_blank\">$mp4filename</A> / ";

if ($pid) {
    print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a><br>";
} else {
    print "<a href=\"./mp4player.php?f=$file\" target=\"_blank\">Player</a><br>";
}

$m2pfilename = $rowdata[8];

list($tid, $countno, $date, $time)= explode("-", $m2pfilename );
//  $tid = preg_replace("/[^0-9]/", "", $tid);
$tid       = $rowdata[0];
$countno   = $rowdata[3] ;
$path      = preg_replace("/\.m2p$|\.m2t$/", "", $m2pfilename);

exec ("ls -1F $recfolderpath/$tid.localized/img/$path/", $tids);
foreach($tids as $filetid) {
    if (strpos($filetid, '/') !== false) {
        continue;
    }
    if (file_exists("./sb-edit.php") ) {
        print "<a href=\"./sb-edit.php?pid=$pid&f=$filetid\"><img src='$httpmediamappath/$tid.localized/img/$path/$filetid' alt='$tid:$countno:$filetid'></a>\n";
    } else {
        if (file_exists("$recfolderpath/$tid.localized/img/$path/l/$filetid")) {
            print "<a href='$httpmediamappath/$tid.localized/img/$path/l/$filetid' target='_blank'><img src='$httpmediamappath/$tid.localized/img/$path/$filetid'  alt='$tid:$countno:$filetid'></a>\n";
        } else {
            print "<img src='$httpmediamappath/$tid.localized/img/$path/$filetid'  alt='$tid:$countno:$filetid'>\n";
        }
    }
} // foreach
// タイトル一覧 ここまで


?>

</body>
</html>

