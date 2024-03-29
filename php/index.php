<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

index.php

目的
全番組放映予定を表示します。
録画予約されている番組は別色でわかりやすく表現されています。


オプション
mode:"new"を指定すると、新番組(第1話)のみの表示となる。
now:YmdHi形式で日付を指定するとその日からの番組表が表示される。

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

$now = getgetnumform('date');
if(($now < 200001010000 ) || ($now > 209912342353 )) {
    $now = date("YmdHi");
}

function printtitle() {
    print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">
    <html lang=\"ja\">
    <head>
    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
    <meta http-equiv=\"Content-Style-Type\" content=\"text/css\">
    <link rel=\"stylesheet\" type=\"text/css\" href=\"graytable.css\"> ";
    // ディスク空き容量によって背景色表示変更
    warndiskfreearea();
    print "<title>foltia:放映予定</title>
    </head>";
} // end function printtitle()


//////////////////////////

// ページの表示レコード数
$lim = 300;

// クエリ取得
$p = getgetnumform('p');

// ページ取得の計算
list($st, $p, $p2) = number_page($p, $lim);

////////////////////////////

// 同一番組他局検索
$query = "
  SELECT
    foltia_program.tid,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_subtitle.startdatetime,
    foltia_subtitle.lengthmin,
    foltia_tvrecord.bitrate,
    foltia_subtitle.pid
  FROM foltia_subtitle, foltia_program, foltia_tvrecord
    WHERE foltia_tvrecord.tid = foltia_program.tid
      AND foltia_program.tid  = foltia_subtitle.tid
      AND foltia_subtitle.enddatetime >= ?
  ORDER BY \"startdatetime\" ASC
  LIMIT 1000
";

$reservedrssametid = sql_query($con, $query, "DBクエリに失敗しました", array($now));
$rowdata = $reservedrssametid->fetch(PDO::FETCH_ASSOC);
if ($rowdata) {
    do {
        $reservedpidsametid[] = $rowdata['pid'];
    } while ($rowdata = $reservedrssametid->fetch(PDO::FETCH_ASSOC));

    $rowdata = "";
} else {
    $reservedpidsametid = array();
} // end if
$reservedrssametid->closeCursor();

// 録画番組検索
$query = "
  SELECT
    foltia_program.tid,stationname,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_subtitle.startdatetime as x,
    foltia_subtitle.lengthmin,
    foltia_tvrecord.bitrate,
    foltia_subtitle.pid
  FROM foltia_subtitle, foltia_program, foltia_station ,foltia_tvrecord
    WHERE foltia_tvrecord.tid         = foltia_program.tid
      AND foltia_tvrecord.stationid   = foltia_station .stationid
      AND foltia_program.tid          = foltia_subtitle.tid
      AND foltia_station.stationid    = foltia_subtitle.stationid
      AND foltia_subtitle.enddatetime >= ?
  UNION
  SELECT
    foltia_program.tid,
    stationname,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_subtitle.startdatetime,
    foltia_subtitle.lengthmin,
    foltia_tvrecord.bitrate,
    foltia_subtitle.pid
  FROM foltia_tvrecord
    LEFT OUTER JOIN foltia_subtitle on (foltia_tvrecord.tid       = foltia_subtitle.tid )
    LEFT OUTER JOIN foltia_program  on (foltia_tvrecord.tid       = foltia_program.tid )
    LEFT OUTER JOIN foltia_station  on (foltia_subtitle.stationid = foltia_station.stationid )
  WHERE foltia_tvrecord.stationid   = 0
    AND foltia_subtitle.enddatetime >= ? ORDER BY x ASC
";
$reservedrs = sql_query($con, $query, "DBクエリに失敗しました",array($now, $now));

$rowdata = $reservedrs->fetch(PDO::FETCH_ASSOC);
if ($rowdata) {
    do {
        $reservedpid[] = $rowdata['pid'];
    } while ($rowdata = $reservedrs->fetch(PDO::FETCH_ASSOC));
} else {
    $reservedpid = array();
} // end if

$mode       = getgetform('mode');
$receivOnly = getgetform('r');

// 受信局のみを表示
if ($receivOnly == "1") {
    // 受信局 を取得
    $query = "
    SELECT
      stationid,
      stationname,
      digitalch,
      digitalstationband
    FROM foltia_station
    WHERE stationrecch > 0
    ";
    $receivChsq = sql_query($con, $query, "DBクエリに失敗しました");
    $rowdata  = $receivChsq->fetchAll(PDO::FETCH_ASSOC);
    $receivStationid = array();
    foreach ($rowdata as $row) {
        $receivStationid[] = $row['stationid'];
    }
}

if ($mode == "new") {
    // 新番組表示モード
    $query = "
      SELECT
        foltia_program.tid,
        foltia_station.stationname,
        foltia_station.digitalch,
        foltia_station.stationid,
        foltia_program.title,
        foltia_subtitle.countno,
        foltia_subtitle.subtitle,
        foltia_subtitle.startdatetime,
        foltia_subtitle.lengthmin,
        foltia_subtitle.pid,
        foltia_subtitle.startoffset
      FROM foltia_subtitle, foltia_program, foltia_station
      WHERE foltia_program.tid          = foltia_subtitle.tid
        AND foltia_station.stationid    = foltia_subtitle.stationid
        AND foltia_subtitle.enddatetime >= ?
        AND foltia_subtitle.countno     = '1'
      ORDER BY foltia_subtitle.startdatetime ASC
      LIMIT 1000
    ";

} else {
    // レコード総数取得
    $query = "
      SELECT
        COUNT(*) AS cnt
      FROM foltia_subtitle, foltia_program, foltia_station
      WHERE foltia_program.tid          = foltia_subtitle.tid
        AND foltia_station.stationid    = foltia_subtitle.stationid
        AND foltia_subtitle.enddatetime >= ?
      LIMIT 1000
    ";

    $rs = sql_query($con, $query, "DBクエリに失敗しました",array($now));
    $rowdata = $rs->fetch(PDO::FETCH_ASSOC);

    $dtcnt = htmlspecialchars($rowdata['cnt']);

    if (! $rowdata) {
        die_exit("番組データがありません<BR>");
    } // endif
    ////////////////////////////////////////////////////////////

    //レコード表示
    $query = "
      SELECT
        foltia_program.tid,
        foltia_station.stationname,
        foltia_station.digitalch,
        foltia_station.stationid,
        foltia_program.title,
        foltia_subtitle.countno,
        foltia_subtitle.subtitle,
        foltia_subtitle.startdatetime,
        foltia_subtitle.lengthmin,
        foltia_subtitle.pid,
        foltia_subtitle.startoffset
      FROM foltia_subtitle, foltia_program, foltia_station
      WHERE foltia_program.tid          = foltia_subtitle.tid
        AND foltia_station.stationid    = foltia_subtitle.stationid
        AND foltia_subtitle.enddatetime >= ?
      ORDER BY foltia_subtitle.startdatetime ASC
      LIMIT $lim OFFSET $st
    ";

    /////////////////////////////////////////////////////////////////
} // end if

$rs = sql_query($con, $query, "DBクエリに失敗しました", array($now));
$rowdata = $rs->fetch(PDO::FETCH_ASSOC);

if (! $rowdata) {
    header("Status: 404 Not Found", TRUE, 404);
    printtitle();
    print "<body BGCOLOR=\"#ffffff\" TEXT=\"#494949\" LINK=\"#0047ff\" VLINK=\"#000000\" ALINK=\"#c6edff\" >
    <div align=\"center\">\n";
    printhtmlpageheader();
    print "<hr size=\"4\">\n";
    die_exit("番組データがありません<BR>");
} // endif

printtitle();
?>

<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
<div align="center">
<?php
printhtmlpageheader();
?>
  <p align="left"><font color="#494949" size="6">
<?php
if ($mode == "new") {
    print "新番組放映予定";
} else {
    print "放映予定";
}
?>

</font></p>
  <hr size="4">
<p align="left">放映番組リストを表示します。</p>
<?php if ($receivOnly == "1"): ?>
<p align="left"><a href="index.php?mode=<?php echo $mode; ?>">全ての局を表示する</a></p>
<?php else: ?>
<p align="left"><a href="index.php?r=1&mode=<?php echo $mode; ?>">受信局のみを表示する</a></p>
<?php endif ?>

<?php
$dtcnt = 0;
page_display('r=' . $receivOnly, $p, $p2, $lim, $dtcnt, $mode);
// フィールド数
$maxcols = $rs->columnCount();

// Autopager
echo "<div id=contents class=autopagerize_page_element />";

?>

 <table BORDER="0" CELLPADDING="0" CELLSPACING="2" WIDTH="100%">
   <thead>
     <tr>
       <th align="left">TID</th>
       <th align="left">放映局</th>
       <th align="left">タイトル</th>
       <th align="left">話数</th>
       <th align="left" style="width: 500px;">サブタイトル</th>
       <th align="left" style="width: 190px;">開始時刻(ズレ)</th>
       <th align="left">総尺</th>
     </tr>
   </thead>
   <tbody>

<?php
// テーブルのデータを出力
do {
    // 他局で同一番組録画済みなら色変え
    if (in_array($rowdata['pid'], $reservedpidsametid)) {
        $rclass = "reservedtitle";
    } else {
        $rclass = "";
    }
    // 録画予約済みなら色変え
    if (in_array($rowdata['pid'], $reservedpid)) {
        $rclass = "reserved";
    }
    $pid      = htmlspecialchars($rowdata['pid']);
    $tid      = htmlspecialchars($rowdata['tid']);
    $title    = htmlspecialchars($rowdata['title']);
    $subtitle = htmlspecialchars($rowdata['subtitle']);

    if ($receivOnly == "1") {
        if (!in_array($rowdata['stationid'], $receivStationid, true)) {
            continue;
        }
    }

    echo("<tr class=\"$rclass\">\n");
    // TID
    print "<td>";
    if ($tid == 0 ) {
        print "$tid";
    } else {
        print "<a href=\"reserveprogram.php?tid=$tid\">$tid</a>";
    }
    print "</td>\n";

    // 放映局
    echo("<td>" . $rowdata['stationname'] . "<br></td>\n");

    // タイトル
    print "<td>";
    if ($tid == 0 ) {
        print "$title";
    } else {
        print "<a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a>";
    }
    print "</td>\n";

     // 話数
    echo("<td>" . htmlspecialchars($rowdata['countno']) . "<br></td>\n");

    // サブタイ
    if ($pid > 0 ) {
        print "<td><a href=\"http://cal.syoboi.jp/tid/$tid/time#$pid\" target=\"_blank\">$subtitle<br></td>\n";
    } else {
        print "<td>$subtitle<br></td>\n";
    }
    // 開始時刻(ズレ)
    echo("<td>" . htmlspecialchars(foldate2print($rowdata['startdatetime'])) . " (" . htmlspecialchars($rowdata['startoffset']).")</td>\n");

    // 総尺
    echo("<td>" . htmlspecialchars($rowdata['lengthmin']) . "<br></td>\n");

    echo("</tr>\n");

} while ($rowdata = $rs->fetch(PDO::FETCH_ASSOC));

?>
    </tbody>
</table>

<?php
/////////////////////////////////////////////////
// Autopageing処理とページのリンクを表示
page_display('r=' . $receivOnly, $p, $p2, $lim, $dtcnt, $mode);
/////////////////////////////////////////////////
?>

</body>
</html>

