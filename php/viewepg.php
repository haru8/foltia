<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

viewepg.php

目的
番組録画予約ページを表示します。

オプション
start:表示タイムスタンプ(Ex.200512281558)
  省略時、現在時刻。

 DCC-JPL Japan/foltia project

*/

include("./foltialib.php");
$con = m_connect();
$epgviewstyle = 1;// 0だと終了時刻も表示
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
<title>foltia:EPG番組表</title>
</head>
<?php
$start = getgetnumform("start");
if ($start == "") {
    $start =  date("YmdHi");
} else {
    $start = preg_replace("/[^0-9]/", "", $start);
}
$endh = getgetnumform("e");
if (!ctype_digit($endh)) {
    $endh = 8;
}


?>
<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
<div align="center">
<?php
printhtmlpageheader();
?>
<p align="left"><font color="#494949" size="6">EPG番組表</font></p>
<p align="left"><a href="./m.php">番組手動予約</a ></p>
<p align="left"><a href="./searchepg.php">番組検索</a ></p>
<hr size="4">
<p align="left">EPG番組表を表示します。
<?php

///////////////////////////////////////////////////////////////////////////
//現在の日付情報取得
$begin      = date("YmdHi");
$beginyear  = substr($begin,  0, 4);
$beginmonth = substr($begin,  4, 2);
$beginday   = substr($begin,  6, 2);
$beginhour  = substr($begin,  8, 2);
$beginmin   = substr($begin, 10, 2);
///////////////////////////////////////////////////////////////////////////

$startyear  = substr($start,  0, 4);
$startmonth = substr($start,  4, 2);
$startday   = substr($start,  6, 2);
$starthour  = substr($start,  8, 2);
$startmin   = substr($start, 10, 2);
$day_of_the_week = date ("(D)", mktime($starthour, 0, 0, $startmonth, $startday, $startyear));
$day_of_the_week = weekDaysRep($day_of_the_week);

print "<span style=\"font-weight: bold\";>($startyear/$startmonth/$startday $day_of_the_week $starthour:$startmin - {$endh}h)</span><BR>\n";


$yesterday      = date("YmdHi" , mktime($starthour , 0 , 0, $startmonth, $startday - 1, $startyear));
$dayyesterday   = date("m/d(D)", mktime($starthour , 0 , 0, $startmonth, $startday - 1, $startyear));
$dayyesterday   = weekDaysRep($dayyesterday);

///////////////////////////////////////////////////////////
//時刻の隣の【翌日】の変数
$tomorrow  = date ("YmdHi", mktime($starthour , 0 , 0, $startmonth , $startday + 1 , $startyear));
///////////////////////////////////////////////////////////
//EPG番組表を取得しますのとなりの日付の【曜日】の変数
$daytomorrow  = date ("m/d(D)", mktime($starthour , 0 , 0, $startmonth , $startday + 1 , $startyear));
$daytomorrow = weekDaysRep($daytomorrow);
///////////////////////////////////////////////////////////


$today0400 = date ("YmdHi", mktime( 4,  0 , 0, $startmonth, $startday, $startyear));
$today0800 = date ("YmdHi", mktime( 8,  0 , 0, $startmonth, $startday, $startyear));
$today1200 = date ("YmdHi", mktime(12,  0 , 0, $startmonth, $startday, $startyear));
$today1600 = date ("YmdHi", mktime(16,  0 , 0, $startmonth, $startday, $startyear));
$today2000 = date ("YmdHi", mktime(20,  0 , 0, $startmonth, $startday, $startyear));
$today2359 = date ("YmdHi", mktime(23, 59 , 0, $startmonth, $startday, $startyear));

function weekDaysRep($str)
{
    $enDays = array('/Sun/', '/Mon/', '/Tue/', '/Wed/', '/Thu/', '/Fri/', '/Sat/');
    $jaDays = array('日', '月', '火', '水', '木', '金', '土');

    return preg_replace($enDays, $jaDays, $str);
}

///////////////////////////////////////////////////////////////////
//１週間分のページのリンクの変数
$day0after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday  , $beginyear));
$day0       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday  , $beginyear));
$day0       = weekDaysRep($day0);
$day1after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 1 , $beginyear));
$day1       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 1 , $beginyear));
$day1       = weekDaysRep($day1);
$day2after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 2 , $beginyear));
$day2       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 2 , $beginyear));
$day2       = weekDaysRep($day2);
$day3after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 3 , $beginyear));
$day3       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 3 , $beginyear));
$day3       = weekDaysRep($day3);
$day4after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 4 , $beginyear));
$day4       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 4 , $beginyear));
$day4       = weekDaysRep($day4);
$day5after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 5 , $beginyear));
$day5       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 5 , $beginyear));
$day5       = weekDaysRep($day5);
$day6after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 6 , $beginyear));
$day6       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 6 , $beginyear));
$day6       = weekDaysRep($day6);
$day7after  = date ("YmdHi" , mktime($starthour , 0 , 0, $beginmonth , $beginday + 7 , $beginyear));
$day7       = date ("m/d(D)", mktime($starthour , 0 , 0, $beginmonth , $beginday + 7 , $beginyear));
$day7       = weekDaysRep($day7);
///////////////////////////////////////////////////////////////////


// 表示局選定
// $page = 1
$maxdisplay = getgetnumform("st");
if (!ctype_digit($maxdisplay)) {
    $maxdisplay = 8;
}

$query = "SELECT count(*) FROM foltia_station WHERE \"ontvcode\" LIKE '%ontvjapan%'";
$rs = sql_query($con, $query, "DBクエリに失敗しました");
$maxrows = $rs->fetchColumn(0);
if ($maxrows > $maxdisplay) {
    $pages = ceil($maxrows / $maxdisplay) ;
}

$page = getgetnumform("p");

if (($page == "")|| ($page <= 0) ) {
    $page = 1 ;
    $offset = 0  ;
} else {
    $page = preg_replace("/[^0-9]/", "", $page);
    if ($page > $pages) {
        $page = $pages ;
    } elseif ($page <= 0) {
        $page = 1 ;
    }
    $offset = ($page * $maxdisplay ) - $maxdisplay;
}


/////////////////////////////////////////////////////////////////
//表示部分
$navigationbar =  "

[<A HREF=\"./viewepg.php\">現在</A>] |
<A HREF=\"./viewepg.php?p=$page&start=$yesterday\">$dayyesterday [前日]</A> |
当日(
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today0400&e=$endh\">4:00</A>　
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today0800&e=$endh\">8:00</A>　
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today1200&e=$endh\">12:00</A>　
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today1600&e=$endh\">16:00</A>　
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today2000&e=$endh\">20:00</A>　
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$today2359&e=$endh\">24:00</A>) |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$tomorrow&e=$endh\">$daytomorrow [翌日]</A>
<br>
 |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day0after&e=$endh\">$day0</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day1after&e=$endh\">$day1</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day2after&e=$endh\">$day2</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day3after&e=$endh\">$day3</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day4after&e=$endh\">$day4</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day5after&e=$endh\">$day5</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day6after&e=$endh\">$day6</A> |
<A HREF=\"./viewepg.php?p=$page&st=$maxdisplay&start=$day7after&e=$endh\">$day7</A> | <BR>\n";
print "$navigationbar";
///////////////////////////////////////////////////////////////////

if ($maxrows > $maxdisplay) {
    //複数ページ
    //$pages = ceil($maxrows / $maxdisplay) ;
    if ($page > 1) {
        $beforepage = $page - 1;
        print "<a href = \"./viewepg.php?p=$beforepage&start=$start\">←</A>";
    }

    print " $page / $pages (放送局) ";
    for ($i=1;$i<=$pages;$i++) {
        print "<a href = \"./viewepg.php?p=$i&start=$start\">$i</a>・";
    }

    if ($page < $pages) {
        $nextpage = $page + 1;
        print "<a href = \"./viewepg.php?p=$nextpage&start=$start\">→</a>";
    }
}
// ココから新コード
// 局リスト
$query = "
    SELECT
      stationid,
      stationname,
      stationrecch,
      ontvcode,
      digitalch
    FROM foltia_station
    WHERE ontvcode LIKE '%ontvjapan%'
    ORDER BY stationid ASC, stationrecch
    LIMIT ? OFFSET ?
";

$slistrs = sql_query($con, $query, "DBクエリに失敗しました", array($maxdisplay, $offset));
$stationinfo = array();
while ($rowdata = $slistrs->fetch()) {
    $stationhash[]  = $rowdata['ontvcode'];
    $snames[]       = $rowdata['stationname'] . '(' . $rowdata['stationid'] . ':' . $rowdata['digitalch'] . ')'; // headder
    $stationinfo[$rowdata['ontvcode']] = array('stationid' => $rowdata['stationid'], 'stationname' => $rowdata['stationname'], 'digitalch' => $rowdata['digitalch']);
}

// 時間と全順番のハッシュ作る
$epgstart = $start;

$epgstart2 = strtotime($epgstart); // 予約検索用
// 番組表の開始時刻よりも先に番組が始まっている可能性があるので6時間分引く
$epgstart2 = date('YmdHi', ($epgstart2 - (60 * 60 * 6)));

$epgend = calcendtime($start, ($endh * 60));

// 番組毎の放送開始時間の一覧を抽出
$query = "
    SELECT DISTINCT startdatetime
    FROM foltia_epg
    WHERE foltia_epg.ontvchannel in (
      SELECT ontvcode
        FROM foltia_station
        WHERE \"ontvcode\" LIKE '%ontvjapan%'
        ORDER BY stationid ASC , stationrecch
      LIMIT ? OFFSET ?
    )
    AND startdatetime  >= ?
    AND startdatetime  < ?
    ORDER BY foltia_epg.startdatetime  ASC";

$rs = sql_query($con, $query, "DBクエリに失敗しました",array($maxdisplay, $offset, $start, $epgend));

//print "$query<br>\n";

$rowdata = $rs->fetch();
if (! $rowdata) {
    // 番組データがない
    $colmnums = 2;
} else {
    $colmnums = 0;
    do {
        $colmnums++;
        $timetablehash[$rowdata[0]] = $colmnums;
        //print "$rowdata[0]:$i+1 <br>\n";
    } while ($rowdata = $rs->fetch());
}
//print "colmnums $colmnums <br>\n";

//・局ごとに縦に配列入れていく
foreach ($stationhash as $stationname) {
    $stationid = $stationinfo[$stationname]['stationid'];
    $reserve = reserveCheck($con, $epgstart2, $epgend, $stationid);
    $query = "
      SELECT
        startdatetime,
        enddatetime,
        lengthmin,
        epgtitle,
        epgdesc,
        epgcategory,
        ontvchannel,
        epgid,
        epgcategory
      FROM foltia_epg
      WHERE foltia_epg.ontvchannel = ?
        AND enddatetime  > ?
        AND startdatetime  < ?
      ORDER BY foltia_epg.startdatetime  ASC";

    $statiodh = sql_query($con, $query, "DBクエリに失敗しました", array($stationname, $epgstart, $epgend));
    $stationrowdata = $statiodh->fetch();
    if (! $stationrowdata) {
        $item[0]["$stationname"] =  ">番組データがありません";
    } else {
        do {
            $startdatetime  = $stationrowdata['startdatetime'];
            $enddatetime    = $stationrowdata['enddatetime'];

            $printstarttime = substr($startdatetime, 8, 2) . ':' .  substr($startdatetime, 10, 2);
            $tdclass        = 't' . substr($startdatetime, 8, 2) .  substr($startdatetime, 10, 2);
            $title          = $stationrowdata['epgtitle'];
            $title          = htmlspecialchars(z2h($title));
            $desc           = $stationrowdata['epgdesc'];
            $desc           = htmlspecialchars(z2h($desc));

            if ($epgviewstyle) {
                $desc .= "<br><br>\n";
                $desc .= "        <!-- " . htmlspecialchars(foldate2print($enddatetime)) . "-->";
            } else {
                $desc .= "<br><br>\n";
                $desc .= "        " . htmlspecialchars(foldate2print($enddatetime)) ;
            }

            $height         = htmlspecialchars($stationrowdata['lengthmin']) * 3;
            $epgid          = htmlspecialchars($stationrowdata['epgid']);
            $epgcategory    = htmlspecialchars($stationrowdata['epgcategory']);

            if (isset($timetablehash[$startdatetime])) {
                $number = $timetablehash[$startdatetime];
                //print "$stationname $stationrowdata[0] [$number] $printstarttime $title $desc<br>\n";
            } else {
                $number = 0;
                //print "$stationname $stationrowdata[0] 現在番組 $printstarttime $title $desc<br>\n";
            }
            $reservedClass = '';
            $reserveSearch = searchStartEndTime2($reserve, $startdatetime, $enddatetime);
            if ($reserve[$reserveSearch[1]]['tid'] != 0) {
              // 番組予約
              if ($reserveSearch[0] == 1) {
                  // 予約済み
                  $reservedClass = 'class="reserved"';
              } else if($reserveSearch[0] == 2) {
                  // 部分的に予約済み
                  $reservedClass = 'class="partiallyReserved"';
              }
            } else {
              // EPG予約
              if ($reserveSearch[0] == 1) {
                  // 予約済み
                  $reservedClass = 'class="reservedEpg"';
              } else if($reserveSearch[0] == 2) {
                  // 部分的に予約済み
                  $reservedClass = 'class="partiallyReservedEpg"';
              }
            }
            $program  = " onClick=\"location = './reserveepg.php?epgid=$epgid'\" $reservedClass>\n";
            $program .= "      <span id=\"epgstarttime\">$printstarttime</span>\n";
            $program .= "      <A HREF=\"./reserveepg.php?epgid=$epgid\"><span id=\"epgtitle\">$title</span></A>\n";
            $program .= "      <span id=\"epgdesc\">\n";
            $program .= "        $desc\n";
            $program .= "      </span>";
            if ($epgcategory == "") {
                $item["$number"]["$stationname"] = $program;
            } else {
                $item["$number"]["$stationname"]  = " id=\"$epgcategory\" ";
                $item["$number"]["$stationname"] .= $program;
            }

        } while ($stationrowdata = $statiodh->fetch());
    }

    // ・局ごとに間隔決定
    // $item[$i][NHK] はヌルかどうか判定
    $dataplace  = 0; // 初期化
    $rowspan    = 0;

    for ($i = 1; $i <= $colmnums; $i++) {
        if ($i === ($colmnums)) { // 最終行
            $rowspan = $i - $dataplace ;
            // そして自分自身にタグを
            if (!isset($item[$i][$stationname])) {
                $item[$i][$stationname]  = null ;
            } else {
                $item[$i][$stationname]  = "    <td ". $item[$i][$stationname] . "\n";
                $item[$i][$stationname] .= "    </td>\n";
                $rowspan--;
            }
            // ROWSPAN
            if ($rowspan === 1 ) {
                $item[$dataplace][$stationname]  = "    <td ". $item[$dataplace][$stationname] . "\n";
                $item[$dataplace][$stationname] .= "    </td>\n";
            } else {
                $item[$dataplace][$stationname]  = "    <td rowspan = $rowspan ". $item[$dataplace][$stationname] . "\n";
                $item[$dataplace][$stationname] .= "   </td>\n";
            }

        } elseif (!isset($item[$i][$stationname])) {
            // ヌルなら
            $item[$i][$stationname]  =  null ;
        } else {
            // なんか入ってるなら
            $rowspan = $i - $dataplace;
            $itemDataplaceStationname = null;
            if (isset($item[$dataplace][$stationname])) {
                $itemDataplaceStationname = $item[$dataplace][$stationname];
            }
            if ($itemDataplaceStationname == null) {
                $itemDataplaceStationname .= '>';
            }
            if ($rowspan === 1 ) {
                $item[$dataplace][$stationname]  = "    <td ". $itemDataplaceStationname . "\n";
                $item[$dataplace][$stationname] .= "    </td>\n";
            } else {
                $item[$dataplace][$stationname]  = "    <td rowspan = $rowspan ". $itemDataplaceStationname . "\n";
                $item[$dataplace][$stationname] .= "    </td>\n";
            }
            $dataplace = $i;

        }
    } // for
} // end of for://・局ごとに縦に配列入れていく

//・テーブルレンダリング
print "\n<table>
<tr> ";

//ヘッダ
foreach ($snames as $s) {
    print "\n  <th>\n    " . htmlspecialchars($s) . "\n  </th>" ;
}
//本体
for ($l = 0 ;$l <  $colmnums; $l++) {
    print "\n  <tr>\n";
    foreach ($stationhash as $stationname) {
        print_r($item[$l]["$stationname"]);
    }
    print "  </tr>\n";
}
print "</table>\n";

print "<p align=\"left\"> $navigationbar </p>";
?>
<hr>
凡例
<table>
<tr>
<td id="information">情報</td>
<td id="anime">アニメ・特撮</td>
<td id="news">ニュース・報道</td>
<td id="drama">ドラマ</td>
<td id="variety">バラエティ</td>
<td id="documentary">ドキュメンタリー・教養</td>
<td id="education">教育</td>
<td id="music">音楽</td>
<td id="cinema">映画</td>
<td id="hobby">趣味・実用</td>
<td id="kids">キッズ</td>
<td id="sports">スポーツ</td>
<td id="etc">その他</td>
<td id="stage">演劇</td>

</tr>
</table>
</body>
</html>

