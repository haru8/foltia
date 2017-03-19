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
} // end if login
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="Content-Style-Type" content="text/css">
  <link rel="stylesheet" type="text/css" href="graytable.css">
  <title>foltia:EPG予約:$epgid</title>
</head>

<body TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >

<?php
printhtmlpageheader();

$word = getgetform('word');
$word = trim($word);
$nowdate = date('YmdHi');
$strdate = date('YmdHi', strtotime('-1 week'));
$enddate = date('YmdHi', strtotime('+1 week'));

if ($word != '') {
    $numsql = 'count(*)';
    $selsql = '*';
      //$query = "
      //  SELECT
      //    %COL%
      //    FROM foltia_epg
      //       LEFT JOIN foltia_station ON foltia_epg.ontvchannel = foltia_station.ontvcode
      //    WHERE (startdatetime >= ?
      //      AND enddatetime    <= ?)
      //      AND (startdatetime < enddatetime)
      //      AND ((epgtitle LIKE ?) OR (epgdesc  LIKE ?))
      //    ORDER BY startdatetime
      //";
    $query = "
        SELECT
          %COL%
          FROM foltia_epg
             LEFT JOIN foltia_station ON foltia_epg.ontvchannel = foltia_station.ontvcode
          WHERE (startdatetime >= ?)

            AND (startdatetime < enddatetime)
            AND ((epgtitle LIKE ?) OR (epgdesc  LIKE ?))
          ORDER BY startdatetime
      ";
    $numsql = str_replace('%COL%', $numsql, $query);
    $selsql = str_replace('%COL%', $selsql, $query);
    $searchword = '%' . $word . '%';
    //$rows = sql_query($con, $numsql, 'DBクエリに失敗しました', array($strdate, $enddate, $searchword, $searchword));
    $rows = sql_query($con, $numsql, 'DBクエリに失敗しました', array($strdate, $searchword, $searchword));
    $row  = $rows->fetchColumn();
    //$rs   = sql_query($con, $selsql, 'DBクエリに失敗しました', array($strdate, $enddate, $searchword, $searchword));
    $rs   = sql_query($con, $selsql, 'DBクエリに失敗しました', array($strdate, $searchword, $searchword));
}

function reserveCheckClass($con, $startdatetime, $enddatetime, $stationid, $nowdate)
{
    $reserve      = reserveCheck($con, $startdatetime, $enddatetime, $stationid);
    $reservecheck = searchStartEndTime($reserve, $startdatetime, $enddatetime);
    $reservedClass = '';
    if ($nowdate < $startdatetime) {
        if ($reservecheck == 1 || $reservecheck == 2) {
            $reservedClass = ' class="reservedtitle"';
        }
    } else {
        if ($reservecheck == 1 || $reservecheck == 2) {
            $reservedClass = ' class="pastreservedtitle"';
        } else {
            $reservedClass = ' class="pasttitle"';
        }
    }
    return $reservedClass;
}

?>

  <p align="left"><font color="#494949" size="6">番組検索</font></p>
  <hr size="4">

  <form name="searchepg" method="GET" action="searchepg.php#result" style="margin-bottom:20px;">
    検索: <input name="word" type="text" id="word" size="60" value="<?php echo "$word"; ?>"/><br><br>
    <input type="submit" value="検索">
  </form>
  <p>
  <?php foreach($searc_words as $val): ?>
    <?php
      $val  = trim($val);
      $searchhit = '';
      if ($val === $word) {
        $searchhit = 'searchhit';
      }
    ?>
    <span class="<?php echo $searchhit ?>"><a href="./searchepg.php?word=<?php echo urlencode($val)?>#result"><?php echo $val ?></a></span><br>
  <?php endforeach ?>
  </p>

  <?php if ($word): ?>
    <span id="result"><?php  echo $row ?> 件ヒットしました</span>
  <?php endif ?>
  <?php if($row > 0): ?>
    <table style="margin-bottom:10px; table-layout: fixed;">
      <tr>
        <th style="width:95px;" rowspan="2">epgid</th>
        <th style="width:95px;" rowspan="2">局</th>
        <th style="width:160px;">開始</th>
        <th style="width:60px;" rowspan="2">尺(分)</th>
        <th style="width:350px;" rowspan="2">番組名</th>
        <th rowspan="2">内容</th>
      </tr>
      <tr>
        <th style="width:160px;">終了</th>
      </tr>
    <?php while($epg = $rs->fetch(PDO::FETCH_ASSOC)): ?>
    <?php
            $reservedClass = reserveCheckClass($con, $epg['startdatetime'], $epg['enddatetime'], $epg['stationid'], $nowdate);
            $epg['epgid']         = htmlspecialchars($epg['epgid']);
            $epg['stationname']   = htmlspecialchars($epg['stationname']);
            $epg['stationid']     = htmlspecialchars($epg['stationid']);
            $epg['startdatetime'] = htmlspecialchars($epg['startdatetime']);
            $epg['lengthmin']     = htmlspecialchars($epg['lengthmin']);
            $epg['epgtitle']      = htmlspecialchars($epg['epgtitle']);
            $epg['epgdesc']       = htmlspecialchars($epg['epgdesc']);
            $epg['enddatetime']   = htmlspecialchars($epg['enddatetime']);

            $epg['epgtitle'] = str_replace($word, '<span class="searchhit">' . $word . '</span>', $epg['epgtitle']);
            $epg['epgdesc']  = str_replace($word, '<span class="searchhit">' . $word . '</span>', $epg['epgdesc']);
    ?>
      <tr <?php echo $reservedClass ?>>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><a href="./reserveepg.php?epgid=<?php echo $epg['epgid'] ?>"><?php echo $epg['epgid'] ?></a></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo $epg['stationname'] ?>(<?php echo $epg['stationid'] ?>)</td>
        <td><?php echo foldate2print($epg['startdatetime']) ?></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo $epg['lengthmin'] ?></td>
        <td rowspan="2"><?php echo $epg['epgtitle'] ?></td>
        <td rowspan="2"><?php echo $epg['epgdesc'] ?></td>
      </tr>
      <tr <?php echo $reservedClass ?>>
        <td><?php echo foldate2print($epg['enddatetime']) ?></td>
      </tr>
    <?php endwhile ?>
    </table>
    <hr>
    <div style="margin:10px;">
    凡例
    <table style="margin-bottom:10px; table-layout: fixed;">
      <tr>
        <td class="pastreservedtitle">録画済み</td>
        <td class="pasttitle">未予約で放送済み</td>
        <td class="reservedtitle">予約済み</td>
        <td class="">未予約</td>
      </tr>
    </table>
    </div
  <?php endif ?>

</body>
</html>

