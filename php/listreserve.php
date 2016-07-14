<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

listreserve.php

目的
録画予約番組放映予定と予約番組名を表示します。

引数
r:録画デバイス数
startdate:特定日付からの予約状況。YYYYmmddHHii形式で。表示数に限定かけてないのでレコード数が大量になると重くなるかも知れません。


 DCC-JPL Japan/foltia project


History

2009/5/1
重複予約検出処理の修正 http://www.dcc-jpl.com/foltia/ticket/7
パッチ適用
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
} //end if login
$userclass = getuserclass($con);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <link rel="stylesheet" type="text/css" href="graytable.css">
    <title>foltia:record plan</title>
  </head>

<?php
$mymemberid = getmymemberid($con);
$now = getgetnumform(startdate);
if ($now == "") {
	$now = getgetnumform(date);
}

if ($now > 200501010000) {
} else {
	$now = date("YmdHi");
}
$query = "
	SELECT
	  foltia_program.tid,
	  stationname,
	  foltia_program.title,
	  foltia_subtitle.countno,
	  foltia_subtitle.subtitle,
	  foltia_subtitle.startdatetime as x,
	  foltia_subtitle.lengthmin,
	  foltia_tvrecord.bitrate,
	  foltia_subtitle.startoffset,
	  foltia_subtitle.pid,
	  foltia_subtitle.epgaddedby,
	  foltia_tvrecord.digital
	FROM
	  foltia_subtitle,
	  foltia_program,
	  foltia_station,
	  foltia_tvrecord
	WHERE foltia_tvrecord.tid         = foltia_program.tid
	  AND foltia_tvrecord.stationid   = foltia_station.stationid
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
	    foltia_subtitle.startoffset,
	    foltia_subtitle.pid,
	    foltia_subtitle.epgaddedby,
	    foltia_tvrecord.digital
	  FROM
	    foltia_tvrecord
	    LEFT OUTER JOIN foltia_subtitle ON (foltia_tvrecord.tid = foltia_subtitle.tid )
	    LEFT OUTER JOIN foltia_program  ON (foltia_tvrecord.tid = foltia_program.tid )
	    LEFT OUTER JOIN foltia_station  ON (foltia_subtitle.stationid = foltia_station.stationid )
	  WHERE foltia_tvrecord.stationid   = 0
	    AND foltia_subtitle.enddatetime >= ? ORDER BY x ASC
";

$rs = sql_query($con, $query, "DBクエリに失敗しました", array($now, $now));

//チューナー数
if (getgetnumform('r') != "") {
	$recunits = getgetnumform('r');
} elseif ($recunits == "") {
	$recunits = 4;
}
$overlapCheck = false;
if (getgetnumform('o') != "") {
	$overlapCheck = getgetnumform('o');
	if ($overlapCheck == 1) {
		$overlapCheck = true;
	} else {
		$overlapCheck = false;
	}
}
?>

  <body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
    <div align="center">

<?php
printhtmlpageheader();
?>
      <p align="left"><font color="#494949" size="6">予約一覧</font></p>
      <hr size="4">
      <p align="left">録画予約番組放映予定と予約番組名を表示します。</p>
      <p align="left"><a href="listreserve.php?o=1">重複チェックをする</a></p>

<?php
$rowdata = $rs->fetch(PDO::FETCH_ASSOC);
if (! $rowdata) {
	print "番組データがありません<BR>\n";
} else {
	/* フィールド数 */
	$maxcols = $rs->columnCount();
?>
      <table BORDER="0" CELLPADDING="0" CELLSPACING="2" WIDTH="100%" style="margin-bottom:5px; table-layout: fixed;">
        <thead>
          <tr>
            <th align="left" rowspan="2" style="width:40px;">TID</th>
            <th align="left" rowspan="2" style="width:85px;">放映局</th>
            <th align="left" rowspan="2" style="width:350px;">タイトル</th>
            <th align="left" rowspan="2" style="width:40px;">話数</th>
            <th align="left" rowspan="2">サブタイトル</th>
            <th align="left" style="width:180px;">開始時刻(ズレ)</th>
            <th align="left" rowspan="2" style="width:40px;">総尺</th>
          </tr>
          <tr>
            <th align="left" style="width:160px;">終了時刻</th>
          </tr>
        </thead>

        <tbody>
<?php
	/* テーブルのデータを出力 */
	do {
		//echo("<tr>\n");

		$pid			= htmlspecialchars($rowdata['pid']);
		$tid			= htmlspecialchars($rowdata['tid']);
		$title			= htmlspecialchars($rowdata['title']);
		$subtitle		= htmlspecialchars($rowdata['subtitle']);
		$dbepgaddedby	= htmlspecialchars($rowdata['epgaddedby']);

		// 重複検出
		// 開始時刻 $rowdata[5]
		// 終了時刻
		$endtime = calcendtime($rowdata['x'], $rowdata['lengthmin']);

		//番組の開始時刻より遅い時刻に終了し、終了時刻より前にはじまる番組があるかどうか

		//オンボードチューナー録画
		$query = "
            SELECT
                foltia_program.tid,
                stationname,
                foltia_program.title,
                foltia_subtitle.countno,
                foltia_subtitle.subtitle,
                foltia_subtitle.startdatetime,
                foltia_subtitle.lengthmin,
                foltia_tvrecord.bitrate,
                foltia_subtitle.startoffset,
                foltia_subtitle.pid,
                foltia_tvrecord.digital
              FROM foltia_subtitle , foltia_program ,foltia_station ,foltia_tvrecord
                WHERE foltia_tvrecord.tid = foltia_program.tid
                  AND foltia_tvrecord.stationid = foltia_station .stationid
                  AND foltia_program.tid = foltia_subtitle.tid
                  AND foltia_station.stationid = foltia_subtitle.stationid
                  AND foltia_subtitle.enddatetime > ?
                  AND foltia_subtitle.startdatetime < ?
                GROUP BY foltia_station.stationid
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
                    foltia_subtitle.startoffset,
                    foltia_subtitle.pid,
                    foltia_tvrecord.digital
                  FROM foltia_tvrecord
                    LEFT OUTER JOIN foltia_subtitle on (foltia_tvrecord.tid = foltia_subtitle.tid )
                    LEFT OUTER JOIN foltia_program on (foltia_tvrecord.tid = foltia_program.tid )
                    LEFT OUTER JOIN foltia_station on (foltia_subtitle.stationid = foltia_station.stationid )
                  WHERE foltia_tvrecord.stationid = 0
                    AND foltia_subtitle.enddatetime > ?
                    AND foltia_subtitle.startdatetime < ?
                  GROUP BY foltia_station.stationid
                 ";

		$rclass = "";
		if ($overlapCheck) {
			$overlap = sql_query($con, $query, "DBクエリに失敗しました", array($rowdata['x'], $endtime, $rowdata['x'], $endtime));
			$owrowall = $overlap->fetchAll();
			$overlapmaxrows = count($owrowall);
			if ($overlapmaxrows > $recunits) {
				$owtimeline = array();

				for ($rrow = 0; $rrow < $overlapmaxrows ; $rrow++) {
					$owrowdata = $owrowall[$rrow];
					$owtimeline[ $owrowdata['startdatetime'] ] = $owtimeline[ $owrowdata['startdatetime'] ] + 1;

					$owrend = calcendtime( $owrowdata['startdatetime'], $owrowdata['lengthmin'] );
					$owtimeline[ $owrend ] = $owtimeline[ $owrend ] -1;
					//注意: NULL に減算子を適用しても何も起こりませんが、NULL に加算子を 適用すると 1 となります。
				}

				ksort ( $owtimeline );

				$owcount = 0;
				foreach ( $owtimeline as $key => $val ) {
					$owcount += $val;

					if ( $owcount > $recunits ) {
						$rclass = "overwraped";
						break;
					}
				}
			}
		}

		//外部チューナー録画
		//$externalinputs = 1; //現状一系統のみ
		//$query = "
		//	SELECT
		//	  foltia_program.tid, stationname, foltia_program.title,
		//	  foltia_subtitle.countno, foltia_subtitle.subtitle,
		//	  foltia_subtitle.startdatetime, foltia_subtitle.lengthmin,
		//	  foltia_tvrecord.bitrate, foltia_subtitle.startoffset,
		//	  foltia_subtitle.pid, foltia_tvrecord.digital
		//	FROM foltia_subtitle , foltia_program ,foltia_station ,foltia_tvrecord
		//	  WHERE foltia_tvrecord.tid = foltia_program.tid AND foltia_tvrecord.stationid = foltia_station .stationid AND foltia_program.tid = foltia_subtitle.tid AND foltia_station.stationid = foltia_subtitle.stationid
		//	    AND foltia_subtitle.enddatetime > ?
		//	    AND foltia_subtitle.startdatetime < ?
		//	    AND (foltia_station.stationrecch = '0' OR  foltia_station.stationrecch = '-1' )
		//	  UNION
		//	  SELECT
		//	    foltia_program.tid, stationname, foltia_program.title,
		//	    foltia_subtitle.countno, foltia_subtitle.subtitle,
		//	    foltia_subtitle.startdatetime, foltia_subtitle.lengthmin,
		//	    foltia_tvrecord.bitrate, foltia_subtitle.startoffset,
		//	    foltia_subtitle.pid, foltia_tvrecord.digital
		//	  FROM foltia_tvrecord
		//	    LEFT OUTER JOIN foltia_subtitle on (foltia_tvrecord.tid = foltia_subtitle.tid )
		//	    LEFT OUTER JOIN foltia_program on (foltia_tvrecord.tid = foltia_program.tid )
		//	    LEFT OUTER JOIN foltia_station on (foltia_subtitle.stationid = foltia_station.stationid )
		//	  WHERE foltia_tvrecord.stationid = 0
		//	    AND foltia_subtitle.enddatetime > ?
		//	    AND foltia_subtitle.startdatetime < ?
		//	    AND (foltia_station.stationrecch = '0' OR  foltia_station.stationrecch = '-1' )
		//	";
		//$eoverlap = sql_query($con, $query, "DBクエリに失敗しました", array($rowdata['x'], $endtime, $rowdata['x'], $endtime));
		//$eowrowall = $eoverlap->fetchAll();
		//$eoverlapmaxrows = count($eowrowall);
		//if ($eoverlapmaxrows > ($externalinputs) ) {

		//	$eowtimeline = array();

		//	for ($erow = 0; $erow < $eoverlapmaxrows ; $erow++) {
		//		$eowrowdata = $eowrowall[$erow];
		//		$eowtimeline[ $eowrowdata['startdatetime'] ] = $eowtimeline[ $eowrowdata['startdatetime'] ] + 1;

		//		$eowrend = calcendtime( $eowrowdata['startdatetime'], $eowrowdata['lengthmin'] );
		//		$eowtimeline[ $eowrend ] = $eowtimeline[ $eowrend ] - 1;
		//	}

		//	ksort ( $eowtimeline );

		//	$eowcount = 0;
		//	foreach ( $eowtimeline as $key => $val ) {
		//		$eowcount += $val;

		//		if ( $eowcount > $externalinputs ) {
		//			$rclass = "exoverwraped";
		//			break;
		//		}
		//	}

		//}
		echo '          <tr class="' . $rclass . '">' . PHP_EOL;
		// TID
		echo '            <td rowspan="2" style="text-align: center; vertical-align: middle;">';
		if ($tid == 0 ) {
			echo $tid;
		} else {
			echo "<a href=\"reserveprogram.php?tid=$tid\">$tid</a>";
		}
		echo '</td>' . PHP_EOL;
		// 放映局
		echo '            <td rowspan="2" style="text-align: center; vertical-align: middle;">' . htmlspecialchars($rowdata['stationname']) . '<br></td>' . PHP_EOL;
		// タイトル
		echo '            <td rowspan="2">';
		if ($tid == 0 ) {
			echo $title;
		} else {
			echo "<a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a>";
		}
		echo  '</td>' . PHP_EOL;
		// 話数
		echo '            <td rowspan="2" style="text-align: center; vertical-align: middle;">' . htmlspecialchars($rowdata['countno']) . '<br></td>' . PHP_EOL;
		// サブタイ
		if ($pid > 0 ) {
			echo '            <td rowspan="2"><a href="http://cal.syoboi.jp/tid/$tid/time#$pid" target="_blank">' . $subtitle . '<br></td>' . PHP_EOL;
		} else {
			if (($mymemberid == $dbepgaddedby)||($userclass <= 1)) {
				if ($userclass <= 1 ) { //管理者なら
					$membername = getmemberid2name($con, $dbepgaddedby);
					$membername = ":" . $membername ;
				} else {
					$membername = "";
				}
				echo '            <td rowspan="2">' . $subtitle . '[<a href="delepgp.php?pid=' . $pid . '">予約解除</a>' . $membername . ']<br></td>' . PHP_EOL;
			} else {
				echo '            <td rowspan="2">' . $subtitle . '[解除不能]<br></td>' . PHP_EOL;
			}
		}
		// 開始時刻(ズレ)
		echo '            <td>' . htmlspecialchars(foldate2print($rowdata['x'])) . ' (' . htmlspecialchars($rowdata['startoffset']) . ')</td>' . PHP_EOL;
		// 総尺
		echo '            <td rowspan="2" style="text-align: center; vertical-align: middle;">' . htmlspecialchars($rowdata['lengthmin']) . '<br></td>' . PHP_EOL;

		echo '          </tr>' . PHP_EOL;
		echo '          <tr class="' . $rclass . '">' . PHP_EOL;
		// 終了時刻
		echo '            <td>'. htmlspecialchars(foldate2print($endtime)) . '</td>' . PHP_EOL;
		echo '          </tr>' . PHP_EOL . PHP_EOL;
	} while ($rowdata = $rs->fetch(PDO::FETCH_ASSOC));
?>
        </tbody>
      </table>

      <table>
        <tr><td>エンコーダ数</td><td><?php print "$recunits"; ?></td></tr>
        <tr class="overwraped"><td>チューナー重複</td><td><br /></td></tr>
        <tr class="exoverwraped"><td>外部入力重複</td><td><br /></td></tr>
      </table>


<?php
} //if ($maxrows == 0)
$query = "
	SELECT
	  foltia_program.tid,
	  stationname,
	  foltia_program.title,
	--  foltia_tvrecord.bitrate,
	  foltia_tvrecord.stationid
	--  foltia_tvrecord.digital
	FROM 
	  foltia_tvrecord,
	  foltia_program,
	  foltia_station
	WHERE foltia_tvrecord.tid       = foltia_program.tid
	  AND foltia_tvrecord.stationid = foltia_station.stationid
	ORDER BY foltia_program.tid DESC
";
$rs = sql_query($con, $query, "DBクエリに失敗しました");
$rowdata = $rs->fetch(PDO::FETCH_ASSOC);
if (! $rowdata) {
	//なければなにもしない
} else {
	$maxcols = $rs->columnCount();

?>
      <p align="left">録画予約番組タイトルを表示します。</p>
      <table BORDER="0" CELLPADDING="0" CELLSPACING="2" WIDTH="100%">
        <thead>
          <tr>
            <th align="left">予約解除</th>
            <th align="left">TID</th>
            <th align="left">放映局</th>
            <th align="left">タイトル</th>
            <th align="left">録画リスト</th>
<?php /*
            <th align="left">画質</th>
            <th align="left">デジタル優先</th>
*/ ?>

          </tr>
        </thead>

        <tbody>
<?php
	/* テーブルのデータを出力 */
	do {
		$tid = htmlspecialchars($rowdata['tid']);

		if ($tid > 0) {
			echo '          <tr>' . PHP_EOL;
			// 予約解除
			if ( $userclass <= 1) {
				echo "            <td><a href=\"delreserve.php?tid=$tid&sid=" . htmlspecialchars($rowdata['stationid']) ."\">解除</a></td>\n";
			} else {
				echo '            <td> - </td>';
			}
			// TID
			echo '            <td><a href="reserveprogram.php?tid=' . $tid . '">' .  $tid . '</a></td>' . PHP_EOL;

			// 放映局
			echo '            <td>' . htmlspecialchars($rowdata['stationname']) . '<br></td>' . PHP_EOL;

			// タイトル
			echo "            <td><a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">" .  htmlspecialchars($rowdata['title']) . '</a></td>' . PHP_EOL;

			// MP4
			echo "            <td><a href=\"showlibc.php?tid=$tid\">mp4</a></td>" . PHP_EOL;

			echo "          </tr>\n\n";
		} else {
			echo '          <tr>' . PHP_EOL;
			echo '            <td> - </td><td>0</td>';
			echo '            <td>[全局]<br></td>';
			echo '            <td>EPG録画</td>';
			echo '            <td><a href="showlibc.php?tid=0">mp4</a></td>';
			//echo '            <td>' . htmlspecialchars($rowdata['bitrate']) . '<br></td>';
			// デジタル優先
			//echo '            <td>';
			//if (htmlspecialchars($rowdata['digital']) == 1) {
			//	echo 'する';
			//} else {
			//	echo 'しない';
			//}
			echo PHP_EOL . '</tr>' . PHP_EOL;
		} // if tid 0
	} while ($rowdata = $rs->fetch(PDO::FETCH_ASSOC));
} // else
?>
      </tbody>
    </table>
  </body>
</html>

