<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

titlelist.php

目的
全番組一覧を表示します。
録画有無にかかわらず情報を保持しているもの全てを表示します

引数
なし

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
} // end if login

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Content-Style-Type" content="text/css">
<link rel="stylesheet" type="text/css" href="graytable.css">
<title>foltia</title>
</head>

<?php

//////////////////////////////////////////////////////////
// 1ページの表示レコード数
$lim = 1000;

// クエリ取得
$p = getgetnumform('p');

// ページ取得の計算
list($st, $p, $p2) = number_page($p, $lim);

///////////////////////////////////////////////////////////

$now = date("YmdHi");

$query = "
  SELECT
    foltia_program.tid,
    foltia_program.title,
    foltia_tvrecord.tid AS rec
  FROM foltia_program
    LEFT JOIN foltia_tvrecord ON foltia_tvrecord.tid = foltia_program.tid
  GROUP BY foltia_program.tid
  ORDER BY foltia_program.tid DESC
  LIMIT $lim OFFSET $st
";

$rs = sql_query($con, $query, "DBクエリに失敗しました");
$rowdata = $rs->fetch();
if (! $rowdata) {
    die_exit("番組データがありません<BR>");
}

$query2 = "
    SELECT COUNT(*) AS cnt FROM foltia_program
";

$rs2 = sql_query($con, $query2, "DBクエリに失敗しました");
$rowdata2 = $rs2->fetch();
if (! $rowdata2) {
    die_exit("番組データがありません<BR>");
}

// 行数取得
$dtcnt =  $rowdata2[0];
?>

<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
<div align="center">

<?php
printhtmlpageheader();
?>
  <p align="left"><font color="#494949" size="6">番組一覧</font></p>
  <hr size="4">
<p align="left">全番組リストを表示します。</p>

<?php
page_display("", $p, $p2, $lim, $dtcnt, "");

// フィールド数
$maxcols = $rs->columnCount();

// Autopager
echo "<div id=contents class=autopagerize_page_element />";
?>

  <table BORDER="0" CELLPADDING="0" CELLSPACING="2" WIDTH="100%">
    <thead>
        <tr>
            <th align="left">TID</th>
            <th align="left">タイトル</th>
            <th align="left">MPEG4リンク</th>
        </tr>
    </thead>

    <tbody>

<?php
// テーブルのデータを出力
do {
    if ($rowdata['rec'] != '') {
        echo("<tr class=\"reservedtitle\">\n");
    } else {
        echo("<tr>\n");
    }

    // TID
    echo("<td><a href=\"reserveprogram.php?tid=" .
    htmlspecialchars($rowdata['tid'])  . "\">" .
    htmlspecialchars($rowdata['tid']) . "</a></td>\n");

    // タイトル
    echo("<td><a href=\"http://cal.syoboi.jp/progedit.php?TID=" .
    htmlspecialchars($rowdata['tid'])  . "\" target=\"_blank\">" .
    htmlspecialchars($rowdata['title']) . "</a></td>\n");
    print "<td><A HREF = \"showlibc.php?tid=".htmlspecialchars($rowdata['tid'])."\">mp4</A></td>\n";

    echo("</tr>\n");
} while ($rowdata = $rs->fetch());

?>

    </tbody>
</table>

<?php

/////////////////////////////////////////////////////////
// Autopageing処理とページのリンクを表示
page_display("", $p, $p2, $lim, $dtcnt, "");
////////////////////////////////////////////////////////

?>
</body>
</html>

