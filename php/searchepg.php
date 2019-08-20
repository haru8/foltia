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
header('Cache-Control: no-cache');
header('Expires: "Mon, 26 Jul 1997 05:00:00 GMT"');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="Content-Style-Type" content="text/css">
  <link rel="stylesheet" type="text/css" href="graytable.css">
  <title>foltia:番組検索</title>
</head>

<body TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff">

<?php
printhtmlpageheader();

$word = getgetform('word');
$word = trim($word);
$nowdate = date('YmdHi');
$strdate = date('YmdHi', strtotime('-1 week'));
$enddate = date('YmdHi', strtotime('+1 week'));

if ($word != '') {
    $split_words = split_word($word);
    $numsql = 'count(*)';
    $selsql = '*';
    $query = "
        SELECT
          %COL%
          FROM foltia_epg
             LEFT JOIN foltia_station ON foltia_epg.ontvchannel = foltia_station.ontvcode
          WHERE (startdatetime >= ?)
            AND (startdatetime < enddatetime)
            AND ((";
    $epgtitle = [];
    $epgdesc  = [];
    $like_str = [];
    foreach ($split_words as $split_word) {
        $epgtitle[] = 'epgtitle LIKE ?';
        $epgdesc[]  = 'epgdesc LIKE ?';
        $like_str[] = '%' . $split_word . '%';
    }
    $epgtitle_imp = implode(' AND ', $epgtitle);
    $epgdesc_imp  = implode(' AND ', $epgdesc);
    $query .= $epgtitle_imp . ') OR (';
    $query .= $epgdesc_imp  . '))';
    $query .= "\n" . '      ORDER BY startdatetime';
    $query_param = array($strdate);
    $query_param = array_merge($query_param, $like_str);
    $query_param = array_merge($query_param, $like_str);
    $numsql = str_replace('%COL%', $numsql, $query);
    $selsql = str_replace('%COL%', $selsql, $query);
    $rows = sql_query($con, $numsql, 'DBクエリに失敗しました', $query_param);
    $row  = $rows->fetchColumn();
    $rs   = sql_query($con, $selsql, 'DBクエリに失敗しました', $query_param);
}

function reserveCheckClass($con, $startdatetime, $enddatetime, $stationid, $nowdate)
{
    $reserve      = reserveCheck($con, $startdatetime, $enddatetime, $stationid);
    $reservecheck = searchStartEndTime($reserve, $startdatetime, $enddatetime);
    $reserved     = array();
    if ($nowdate < $startdatetime) {
        if ($reservecheck == 1 || $reservecheck == 2) {
            $reserved['class'] = ' class="reservedtitle"';
        }
    } else {
        if ($reservecheck == 1 || $reservecheck == 2) {
            $reserved['class'] = ' class="pastreservedtitle"';
            $reserved['pid']   = $reserve[0]['pid'];
        } else {
            $reserved['class'] = ' class="pasttitle"';
        }
    }
    return $reserved;
}

?>

<div id="searchepg">
  <p align="left"><font color="#494949" size="6">番組検索</font></p>
  <hr size="4">

  <form name="searchepg" method="GET" action="searchepg.php#resulttop" style="margin-bottom:20px;">
    検索: <input name="word" type="text" id="word" size="60" value="<?php echo "$word"; ?>"/><br><br>
    <input type="submit" value="検索">
  </form>
<?php
$words = array();
$n     = 0;
foreach($searc_words as $val) {
  $val  = trim($val);
  if ($val == '') {
    $n++;
    continue;
  }
  $words[$n][] = $val;
}
?>
  <?php $currentsearchhit = false; ?>
  <?php foreach($words as $wordsVal1): ?>
    <div class="stationss">
    <?php foreach($wordsVal1 as $wordsVal2): ?>
    <?php
    $searchhit     = 'proguram';
    $currentsearch = '';
    if ($wordsVal2 === $word) {
      $searchhit     = 'searchhit-proguram';
      $currentsearch = 'currentsearch';
      $currentsearchhit = true;
    }
    ?>
      <div class="<?php echo $searchhit ?>" id="<?php echo $currentsearch; ?>"><a href="./searchepg.php?word=<?php echo urlencode($wordsVal2)?>#currenttr"><?php echo $wordsVal2 ?></a></div>
    <?php endforeach ?>
    </div>
  <?php endforeach ?>
  </p>

  <?php if ($word): ?>
    <div id="resulttop" style="float:left;">
      <?php  echo $row ?> 件ヒットしました
    </div>
    <div style="text-align:right;" <?php if ($row == 0): ?>id="currenttr"<?php else: ?>id="backtotop1"<?php endif ?>>
      <a href="./searchepg.php?word=<?php echo urlencode($word)?><?php if ($currentsearchhit): ?>#currentsearch<?php else: ?>#word<?php endif ?>">上に戻る△</a>
    </div>
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
    <?php $currenttr = ''; ?>
    <?php while($epg = $rs->fetch(PDO::FETCH_ASSOC)): ?>
    <?php
            $reserved             = reserveCheckClass($con, $epg['startdatetime'], $epg['enddatetime'], $epg['stationid'], $nowdate);
            $epg['epgid']         = htmlspecialchars($epg['epgid']);
            $epg['stationname']   = htmlspecialchars($epg['stationname']);
            $epg['stationid']     = htmlspecialchars($epg['stationid']);
            $epg['startdatetime'] = htmlspecialchars($epg['startdatetime']);
            $epg['lengthmin']     = htmlspecialchars($epg['lengthmin']);
            $epg['epgtitle']      = htmlspecialchars($epg['epgtitle']);
            $epg['epgdesc']       = htmlspecialchars($epg['epgdesc']);
            $epg['enddatetime']   = htmlspecialchars($epg['enddatetime']);
            if ($epg['startdatetime'] > $nowdate) {
                if (empty($currenttr)) {
                    $currenttr = 'currenttr';
                } else {
                    $currenttr = $epg['epgid'];
                }
            }

            foreach ($split_words as $split_word) {
                $epg['epgtitle'] = str_replace($split_word, '<span class="searchhit">' . $split_word . '</span>', $epg['epgtitle']);
                $epg['epgdesc']  = str_replace($split_word, '<span class="searchhit">' . $split_word . '</span>', $epg['epgdesc']);
            }
    ?>
      <tr <?php echo $reserved['class']; ?> id="<?php echo $currenttr; ?>">
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><a href="./reserveepg.php?epgid=<?php echo $epg['epgid'] ?>"><?php echo $epg['epgid'] ?></a></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo $epg['stationname'] ?>(<?php echo $epg['stationid'] ?>)</td>
        <td><?php echo foldate2print($epg['startdatetime']) ?></td>
        <td rowspan="2" style="text-align: center; vertical-align: middle;"><?php echo $epg['lengthmin'] ?></td>
        <td rowspan="2"><?php echo $epg['epgtitle'] ?><br><?php if (isset($reserved['pid'])): ?><a href="./mp4player.php?p=<?php echo $reserved['pid']?>" target="_blank">[Player]</a> <a href="./selectcaptureimage.php?pid=<?php echo $reserved['pid']?>">[キャプ]</a><?php endif ?><a href="./searchplaylist.php?word=<?php echo urlencode($word)?>#result">[録画一覧検索]</a></td>
        <td rowspan="2"><?php echo $epg['epgdesc'] ?></td>
      </tr>
      <tr <?php echo $reserved['class'] ?>>
        <td><?php echo foldate2print($epg['enddatetime']) ?></td>
      </tr>
    <?php endwhile ?>
    </table>
    <div style="text-align:right;" <?php if (empty($currenttr)): ?>id="currenttr"<?php else: ?>id="backtotop2"<?php endif ?>>
      <a href="./searchepg.php?word=<?php echo urlencode($word)?><?php if ($currentsearchhit): ?>#currentsearch<?php else: ?>#word<?php endif ?>">上に戻る△</a>
    </div>
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
    </div>
</div>
  <?php endif ?>

</body>
</html>

