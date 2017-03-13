<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

showlibc.php

目的
録画ライブラリ番組を個別表示します。

引数
tid:タイトルID

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

$tid = getgetnumform(tid);

if ($tid == "") {
    header("Status: 404 Not Found" ,TRUE, 404);
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php
if (file_exists  ( "./iui/iui.css"  )) {
    $useragent = $_SERVER['HTTP_USER_AGENT'];
}
if (preg_match("/iPhone/", $useragent)) {
    print "<meta name=\"viewport\" content=\"width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=no;\"/>
        <link rel=\"apple-touch-icon\" type=\"image/png\" href=\"./img/icon.png\" />
        <style type=\"text/css\" media=\"screen\">@import \"./iui/iui.css\";</style>
        <script type=\"application/x-javascript\" src=\"./iui/iui.js\"></script>";
} else {
    print "<meta http-equiv=\"Content-Style-Type\" content=\"text/css\">
        <link rel=\"stylesheet\" type=\"text/css\" href=\"graytable.css\">
        <script src=\"http://images.apple.com/main/js/ac_quicktime.js\" language=\"JavaScript\" type=\"text/javascript\"></script>
        <link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS\" href=\"./folcast.php?tid=$tid\" />";
}
if ($tid == "") {
    print "<title>foltia:Lib</title></head><body BGCOLOR=\"#ffffff\" TEXT=\"#494949\" LINK=\"#0047ff\" VLINK=\"#000000\" ALINK=\"#c6edff\" > \n";
    printhtmlpageheader();
    die_exit("再生可能番組がありません<BR>");
}

?>


<?php
//////////////////////////////////////////////////////////
//１ページの表示レコード数
$lim = 35;
//クエリ取得
$p = getgetnumform(p);
//ページ取得の計算
list($st, $p, $p2) = number_page($p, $lim);
///////////////////////////////////////////////////////////

$now = date("YmdHi");

$query = "
  SELECT
    foltia_program.title
    FROM  foltia_program
    WHERE foltia_program.tid = ?
";

$rs = sql_query($con, $query, "DBクエリに失敗しました", array($tid));
$rowdata = $rs->fetch();
if (! $rowdata) {
    $syobocaldb = `curl "http://cal.syoboi.jp/db?Command=TitleLookup&TID=$tid" | head -2 `;
    $syobocaldb = mb_convert_encoding($syobocaldb, "UTF-8");
    $syobocaldb = preg_match("/<Title>.*<\/Title>/", $syobocaldb, $title);
    $title = $title[0];
    $title = strip_tags($title);
    $title =  htmlspecialchars($title) ;
} else {
    $title = $rowdata[0];
    $title =  htmlspecialchars($title) ;
}
//ヘッダ続き
print "<title>foltia:Lib $tid:$title</title></head>";
$serveruri = getserveruri();


if (preg_match("/iPhone/", $useragent)) {
    print "<body onclick=\"console.log('Hello', event.target);\">
        <div class=\"toolbar\">
            <h1 id=\"pageTitle\"></h1>
            <a id=\"backButton\" class=\"button\" href=\"#\"></a>
        </div>";
} else {
    print "<body BGCOLOR=\"#ffffff\" TEXT=\"#494949\" LINK=\"#0047ff\" VLINK=\"#000000\" ALINK=\"#c6edff\" ><div align=\"center\">";
    printhtmlpageheader();
    print "  <p align=\"left\"><font color=\"#494949\" size=\"6\">録画ライブラリ番組個別表示</font></p>
        <hr size=\"4\">
        <p align=\"left\">再生可能ムービーを表示します。<br>";
    if ($tid == 0) {
        print "$title 【<A HREF = \"./folcast.php?tid=$tid\">この番組のFolcast</A> ［<a href=\"itpc://$serveruri/folcast.php?tid=$tid\">iTunesに登録</a>］】 <br>\n";
    } else {
        print "<a href=\"http://cal.syoboi.jp/tid/" .
            htmlspecialchars($tid)  . "\" target=\"_blank\">$title</a>
            【<A HREF = \"./folcast.php?tid=$tid\">この番組のFolcast</A>
            ［<a href=\"itpc://$serveruri/folcast.php?tid=$tid\">iTunesに登録</a>］】 <br>\n";
    }
} // endif if (preg_match("/iPhone/", $useragent))

//確認
if (file_exists ("$recfolderpath/$tid.localized")) {
    //  print "ディレクトリは存在します\n";
} else {
    //print "ディレクトリはありません\n";
    print "再生可能番組がありません<BR>\n</body></html>";
    exit;
}

//新仕様/* 2006/10/26 */
if (file_exists("./selectcaptureimage.php") ) {
    $sbpluginexist = 1;
}
$serverfqdn = getserverfqdn();


//Autopager
echo "<div id=contents class=autopagerize_page_element />";
?>

<form name="deletemovie" method="POST" action="./deletemovie.php">
<p align="left"><input type="submit" value="項目削除" ></p>

<?php

/////////////////////////////////////////////////////////
//レコード総数取得
$query = "
  SELECT
    COUNT(*) AS cnt
  FROM foltia_mp4files
    LEFT JOIN foltia_subtitle ON foltia_mp4files.mp4filename = foltia_subtitle.pspfilename AND foltia_mp4files.tid = foltia_subtitle.tid
    LEFT JOIN foltia_program  ON foltia_mp4files.tid         = foltia_program.tid
  WHERE foltia_mp4files.tid = ?
  ORDER BY foltia_subtitle.startdatetime DESC
";

$rs = sql_query($con, $query, "DBクエリに失敗しました", array($tid));
$rowdata = $rs->fetch();
$dtcnt = htmlspecialchars($rowdata[0]);
//echo $dtcnt;

if (! $rowdata) {
    die_exit("番組データがありません。<BR>");
} //endif

//クエリ代入
$query_st =  $tid;
page_display($query_st, $p, $p2, $lim, $dtcnt, "");

//////////////////////////////////////////////////////////
//レコード表示
$query = "
  SELECT
    foltia_program.tid,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_subtitle.startdatetime,
    foltia_subtitle.m2pfilename,
    foltia_subtitle.pid,
    foltia_mp4files.mp4filename,
    foltia_subtitle.lengthmin
  FROM foltia_mp4files
    LEFT JOIN foltia_subtitle ON foltia_mp4files.mp4filename = foltia_subtitle.pspfilename AND foltia_mp4files.tid = foltia_subtitle.tid
    LEFT JOIN foltia_program  ON foltia_mp4files.tid         = foltia_program.tid
  WHERE foltia_mp4files.tid = ?
  GROUP BY foltia_mp4files.mp4filename
  ORDER BY foltia_subtitle.startdatetime DESC
";
    //ORDER BY \"startdatetime\" DESC
    //LIMIT $lim OFFSET $st

$rs = "";
$rs = sql_query($con, $query, "DBクエリに失敗しました", array($tid));
$rowdataAll = $rs->fetchAll(PDO::FETCH_ASSOC);

$rowSort = array();
foreach ($rowdataAll as $key => $row) {
    $date          = explode('-', $row['mp4filename']);
    $rowSort[$key] = $date[3];
}
array_multisort($rowSort, SORT_DESC, SORT_NATURAL, $rowdataAll);
$rowdataAll = array_slice($rowdataAll, $st, $lim);

if ($rowdataAll) {
    if (preg_match("/iPhone/", $useragent)) {
        print "<ul id=\"home\" title=\"$title\" selected=\"true\">";
    } else {
        print "<table BORDER=\"0\" CELLPADDING=\"0\" CELLSPACING=\"2\" WIDTH=\"100%\"><tbody>";
    }

    foreach ($rowdataAll as $rowdata) {
        $title = $rowdata['title'];

        if ($rowdata['countno'] == "" ) {
            $count = "[話数]";
        } else {
            $count = $rowdata['countno'];
        }
        if ($rowdata['subtitle'] == "" ) {
            $subtitle = "[サブタイトル]";
        } else {
            $subtitle = $rowdata['subtitle'];
        }
        $onairdate =  $rowdata['startdatetime'];

        $tid        = htmlspecialchars($rowdata['tid']);
        $title      = htmlspecialchars($title);
        $count      = htmlspecialchars($count);
        $subtitle   = htmlspecialchars($subtitle);
        $onairdate  = htmlspecialchars($onairdate);
        $pid        = htmlspecialchars($rowdata['pid']);
        $fName      = htmlspecialchars($rowdata['mp4filename']);
        $lengthmin  = htmlspecialchars($rowdata['lengthmin']);

        $mp4path   = "$recfolderpath/$tid.localized/mp4/$fName" ;
        $mp4Exists = false;
        if (file_exists($mp4path) && is_file($mp4path)) {
            $mp4Exists  = true;
            $mp4size    = filesize($mp4path);
            $mp4size    = round($mp4size / 1024 / 1024);
        }

        if (preg_match("/\.MP4/", $fName)) {
            $thumbnail = $fName;
            $thumbnail = preg_replace("/\.MP4/", ".THM", $thumbnail);
        }
        if ($onairdate == "") {
            $onairdate = "[放送日]";
        } else {
            $onairdate = foldate2print($onairdate);
        }
        //Starlight Breaker向け拡張
        //$debug_pg_num_rows = $rs ->rowCount();
        $caplink = "";

        if ($sbpluginexist == 1) {
            $capimgpath = htmlspecialchars(preg_replace("/.m2./", "", $rowdata['m2pfilename']));

            if (($capimgpath != "") && (file_exists("$recfolderpath/$tid.localized/img/$capimgpath") )) {
                $caplink = " / <a href = \"./selectcaptureimage.php?pid=" . $rowdata['pid'] . "\">キャプ</a>";
            } else {
                $caplink = " / <a href = \"./selectcaptureimage.php?f=" . $rowdata['mp4filename'] . "\">キャプ</a>";
            }
        } else {
            $caplink = "";
        } //end if sb

        if (file_exists("$recfolderpath/$tid.localized/mp4/$thumbnail") ) {
            $imgsrcuri = "$httpmediamappath/$tid.localized/mp4/$thumbnail\" alt=\"$title $count $subtitle";
        } else {
            $imgsrcuri = "./img/no-thumbnail-img.png\" alt=\"NO IMAGE";
        }

        if (preg_match("/iPhone/", $useragent)) {
            print "<li><a href=\"http://$serverfqdn/$httpmediamappath/$tid.localized/mp4/$fName\" target=\"_self\">$count $subtitle $onairdate</a></li>\n";

        } else {
            print "
                <tr >
                    <td rowspan=\"4\" width=\"170\" style=\"border-bottom-color: #222;\"><a href=\"./mp4player.php?p=$pid\" target=\"_blank\"><img src = \"$imgsrcuri\" width=\"160\" height=\"120\"></a></td>
                    <td>$count</td>
                </tr>
                <tr>";
                if ($tid == 0) {
                    print "\n    <td>$subtitle</td>";
                } else {
                    print "\n    <td><a href = \"http://cal.syoboi.jp/tid/$tid/time#$pid\" target=\"_blank\">$subtitle</a></td>";
                } //if

            print "
                </tr>
                <tr>
                    <td>$onairdate</td>
                </tr>
                <tr>
                    <td style=\"border-bottom-color: #222;\"><input type='checkbox' name='delete[]' value='$fName'>削除 /
                    <a href =\"$httpmediamappath/$tid.localized/mp4/$fName\" target=\"_blank\">$fName</A> / ";

            if ($mp4Exists) {
                if ($pid) {
                    print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a> [${mp4size}MB] [${lengthmin}分]/ ";
                } else {
                    print "<a href=\"./mp4player.php?f=$fName\" target=\"_blank\">Player</a> [${mp4size}MB] [${lengthmin}分] / ";
                }
            } else {
            }
            print "
                <script language=\"JavaScript\" type=\"text/javascript\">QT_WriteOBJECT_XHTML('http://g.hatena.ne.jp/images/podcasting.gif','16','16','','controller','FALSE','href','http://$serverfqdn/$httpmediamappath/$tid.localized/mp4/$fName','target','QuickTimePlayer','type','video/mp4');</script> $caplink</td>
                </tr>";

        } //endif iPhone

    }
} else {
    print "録画ファイルがありません<br>\n";
} //if

if (preg_match("/iPhone/", $useragent)) {
    print "<li><a href=\"http://$serveruri/showlib.php\" target=\"_self\">一覧へ戻る</a></li>\n";
    print "</ul>\n";
} else {
    print "</tbody></table>\n";
}

//////////////////////////////////////////////
//Autopager処理とページのリンク表示
page_display($query_st, $p, $p2, $lim, $dtcnt, "");
//////////////////////////////////////////////
?>

</body>
</html>

