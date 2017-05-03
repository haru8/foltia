<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

showplaylist.php

目的
録画したmpeg2の番組一覧を表示します。


オプション
list:
　省略時、録画順にソートされる。
　titleのときに、番組順ソートされる。
　rawのときに、DBに記録されている番組録画情報ではなくディレクトリにあるm2p/m2tファイルを全て表示する。

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
<?php

// Syabas 判定
$useragent = $_SERVER['HTTP_USER_AGENT'];

//ディスク空き容量によって背景色表示変更
warndiskfreearea();

print "<title>foltia:recorded file list</title>
</head>";


/////////////////////////////////////////////////??????
//１ページの表示レコード数
$lim = 300;
//クエリ取得
$p = getgetnumform(p);
//ページ取得の計算
list($st, $p, $p2) = number_page($p, $lim);
//////////////////////////////////////////////////????

$now = date("YmdHi");
?>
<body BGCOLOR="#ffffff" TEXT="#494949" LINK="#0047ff" VLINK="#000000" ALINK="#c6edff" >
<div align="center">

<?php
printhtmlpageheader();
?>

  <p align="left"><font color="#494949" size="6">録画一覧表示</font></p>
  <hr size="4">
<p align="left">再生可能番組リストを表示します。<br>

<?php
if ($demomode) {
} else {
    printdiskusage();
    printtrcnprocesses();
    printrecpt1processes();
}

//////////////////////////////////////////
//クエリ取得
$list = getgetform('list');
//Autopager
echo "<div id=contents class=autopagerize_page_element />";
//////////////////////////////////////////


?>
<form name="deletemovie" method="POST" action="./deletemovie.php">
<p align="left"><input type="submit" value="項目削除" ></p>

  <table border="0" cellpadding="0" cellspacing="2" width="100%" style="table-layout: fixed;">
    <thead>
        <tr>
            <th rowspan="2" align="left" style="width:20px;">削除</th>
            <th rowspan="1" align="left" style="width:250px;">日時</th>
            <th rowspan="2" align="left" style="width:300px;"><A HREF="./showplaylist.php?list=title">タイトル</A></th>
            <th rowspan="2" align="left" style="width:50px;">話数</th>
            <th rowspan="2" align="left" style="">サブタイ</th>
            <th rowspan="2" align="left" style="width:50px;">Player</th>

<?php
if (file_exists("./selectcaptureimage.php") ) {
  print "         <th rowspan='2' align=\"left\" style=\"width:20px;\">キャプ</th>\n";
}
?>
        </tr>
        <tr>
            <th align="left" style="width:250px;"><A HREF="./showplaylist.php">ファイル名</A></th>
        </tr>
    </thead>

    <tbody>



<?php

//$list = getgetform('list');

//旧仕様
if ($list == "raw") {
  exec ("ls -1t  $recfolderpath/*.???", $m2pfiles);

  page_display($list, $p, $p2, $lim, count($m2pfiles), "");

  $m2pfilesP  = array_chunk($m2pfiles, $lim);
  $datas      = array();
  $nodata     = array();
  $mp4_exists = array();
  foreach($m2pfilesP[$p - 1] as $pathfName) {

    $fNametmp = explode("/", $pathfName);
    $fName = array_pop($fNametmp);
    //print "FILENAME:$fName<BR>\n";

    if (($fName == ".") or ($fName == "..") ) {
      continue;
    }
    if ((preg_match("/\.m2.+/", $fName)) || (preg_match("/\.aac/", $fName))) {
      $filesplit = explode("-", $fName);

      if (preg_match("/^\d+$/", $filesplit[0])) {
        if ($filesplit[1] == "") { // 話数, EPG録画
          $query = "
          SELECT
            foltia_program.tid,
            foltia_program.title,
            foltia_subtitle.countno,
            foltia_subtitle.subtitle,
            foltia_subtitle.pid,
            foltia_subtitle.m2pfilename,
            foltia_subtitle.pspfilename,
            foltia_subtitle.startdatetime
           FROM foltia_subtitle , foltia_program
           WHERE foltia_program.tid = foltia_subtitle.tid
             AND foltia_subtitle.tid = ?
             AND foltia_subtitle.m2pfilename = ?
          ";
          $rs = sql_query($con, $query, "DBクエリに失敗しました", array($filesplit[0], $fName));
        } else {
          $query = "
          SELECT
            foltia_program.tid,
            foltia_program.title,
            foltia_subtitle.countno,
            foltia_subtitle.subtitle,
            foltia_subtitle.pid,
            foltia_subtitle.m2pfilename,
            foltia_subtitle.pspfilename,
            foltia_subtitle.startdatetime
           FROM foltia_subtitle , foltia_program
           WHERE foltia_program.tid = foltia_subtitle.tid
             AND foltia_subtitle.tid = ?
             AND foltia_subtitle.countno = ?
             AND foltia_subtitle.m2pfilename = ?
          ";
          $rs = sql_query($con, $query, "DBクエリに失敗しました", array($filesplit[0], $filesplit[1], $fName));
        } //if 話数あるかどうか

        $rall          = $rs->fetchAll(PDO::FETCH_ASSOC);
        $rowdata       = $rall[0];
        $tid           = htmlspecialchars($rowdata['tid']);
        $title         = htmlspecialchars($rowdata['title']);
        $count         = htmlspecialchars($rowdata['countno']);
        $subtitle      = htmlspecialchars($rowdata['subtitle']);
        $pid           = htmlspecialchars($rowdata['pid']);
        $m2pfilename   = htmlspecialchars($rowdata['m2pfilename']);
        $mp4filename   = htmlspecialchars($rowdata['PSPfilename']);
        $startdatetime = htmlspecialchars($rowdata['startdatetime']);

        $m2pExists = false;
        $m2pUrl    = $httpmediamappath . '/' . $fName;
        $m2ppath   = $recfolderpath . '/' . $fName;
        if (file_exists($m2ppath) && is_file($m2ppath) && filesize($m2ppath)) {
            $m2pExists = true;
        }

        $mp4Url    = $httpmediamappath . '/' . $tid . '.localized/mp4/' . $mp4filename;
        $mp4path   = "$recfolderpath/$tid.localized/mp4/$mp4filename" ;
        $mp4Exists = false;
        if (file_exists($mp4path) && is_file($mp4path)) {
          $mp4Exists = true;
          $mp4size = filesize($mp4path);
          $mp4size = round($mp4size / 1024 / 1024);
          $mp4_exists[] = $mp4path;
        } else {
          $nodata[] = $fName;
        }
        $datas[$fName]['mp4'] = $mp4path;
        $datas[$fName]['mp4filename'] = $mp4filename;
        $datas[$fName]['tid'] = $tid;
        $datas[$fName]['pid'] = $pid;

        //--
        print "
        <tr>
          <td rowspan='2'><INPUT TYPE='checkbox' NAME='delete[]' VALUE='$fName'><br></td>
          <td rowspan='1'>" . foldate2print($startdatetime) . "</td>
          <td rowspan='2'><a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a><br><a href=\"./showlibc.php?tid=$tid\">[ライブラリ]</a></td>
          <td rowspan='2'>$count<br></td>
          <td rowspan='2'>$subtitle<br></td>\n";

        print "          <td rowspan='2'>";
        if ($mp4Exists) {
          print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a><br />${mp4size}MB";
        }
        print "</td>\n";

        if (file_exists("./selectcaptureimage.php") ) {
          print "          <td rowspan='2' align=\"left\"><a href=\"./selectcaptureimage.php?pid=$pid\">キャプ</a></td>\n";
        }

        print "        </tr>\n";
        print "        <tr>";
        print "
          <td rowspan='1'>";
        if ($m2pExists) {
            echo '<a href="'. $m2pUrl . '">' . $fName . '</a><br>';
        }
        if ($mp4Exists) {
            echo '<a href="'. $mp4Url . '" target="_blank">' . $mp4filename . '</a>';
        }
        print "</td>\n";
        print "        </tr>\n";
      } else {
        //print "File is looks like BAD:preg<br>\n";
      }
    } //preg_match
  } //foreach
  print "   </tbody>\n</table>\n</FORM>\n";
  echo '<p align="left">';
  echo '$datas=' . count($datas) . '  ';
  echo '$nodata=' . count($nodata, COUNT_RECURSIVE) . '  ';
  echo '$mp4_exists=' . count($mp4_exists) .  '<br />';
  echo '</p>';
  page_display($list, $p, $p2, $lim, count($m2pfiles), "");
  print "</body>\n</html>\n";
  exit;

} else if ($list== 'mp4') {

  $head = getgetform('head');
  if (!$head) {
    $head = 300;
  }
  exec ("ls -1t $recfolderpath/*.localized/mp4/*.MP4", $mp4files);

  $lim = $head;
  page_display($list, $p, $p2, $lim, count($mp4files), "");

  $mp4filesP  = array_chunk($mp4files, $lim);
  $datas      = array();
  $nodata     = array();
  $mp4_exists = array();
  foreach($mp4filesP[$p - 1] as $pathfName) {
    //echo "<pre>$pathfName</pre>";
    $fNametmp = explode('/', $pathfName);
    $fName    = array_pop($fNametmp);

    if (($fName == '.') || ($fName == '..') ) {
      continue;
    }
    if (preg_match('/\.MP4/', $fName)) {
      $filesplit = explode('-', $fName);
      $tid       = $filesplit[1];
      $num       = $filesplit[2];
      $tid       = htmlspecialchars($tid);
      if (preg_match("/^\d+$/", $tid)) {
        if ($num == '') { // 話数無し、EPG録画
          $query = ' SELECT
                       foltia_program.tid,
                       foltia_program.title,
                       foltia_subtitle.countno,
                       foltia_subtitle.subtitle,
                       foltia_subtitle.pid,
                       foltia_subtitle.m2pfilename,
                       foltia_subtitle.pspfilename,
                       foltia_subtitle.startdatetime
                      FROM foltia_subtitle, foltia_program
                      WHERE foltia_program.tid = foltia_subtitle.tid
                       AND foltia_subtitle.tid = ?
                       AND foltia_subtitle.PSPfilename = ? ';
          $rs      = sql_query($con, $query, 'DBクエリに失敗しました', array($tid, $fName));
        } else {
          $query = ' SELECT
                       foltia_program.tid,
                       foltia_program.title,
                       foltia_subtitle.countno,
                       foltia_subtitle.subtitle,
                       foltia_subtitle.pid,
                       foltia_subtitle.m2pfilename,
                       foltia_subtitle.pspfilename,
                       foltia_subtitle.startdatetime
                      FROM foltia_subtitle, foltia_program
                      WHERE foltia_program.tid = foltia_subtitle.tid
                        AND foltia_subtitle.tid = ?
                        AND foltia_subtitle.countno = ?
                        AND foltia_subtitle.PSPfilename = ? ';
          $rs      = sql_query($con, $query, "DBクエリに失敗しました", array($tid, $num, $fName));
        }

        $rall          = $rs->fetchAll(PDO::FETCH_ASSOC);
        $rowdata       = $rall[0];
        $title         = htmlspecialchars($rowdata['title']);
        $count         = htmlspecialchars($rowdata['countno']);
        $subtitle      = htmlspecialchars($rowdata['subtitle']);
        $pid           = htmlspecialchars($rowdata['pid']);
        //$mp4filename = htmlspecialchars($rowdata['PSPfilename']);
        $mp4filename   = htmlspecialchars($fName);
        $startdatetime = htmlspecialchars($rowdata['startdatetime']);

        $mp4path   = "$recfolderpath/$tid.localized/mp4/$mp4filename" ;
        $mp4Exists = false;
        if (file_exists($mp4path) && is_file($mp4path)) {
          $mp4Exists = true;
          $mp4size   = filesize($mp4path);
          $mp4size   = round($mp4size / 1024 / 1024);
          if ($pid) {
            $mp4_exists[] = $mp4path;
          } else {
          $nodata[] = $mp4path;
          }
        } else {
          $nodata[] = $mp4path;
        }

        $ind   = explode('-', $fName);
        if (!isset($ind[5])) {
          $ind[5] = '';
        }
        $index = $ind[0] .'-'. sprintf("%05d", $ind[1]) .'-'. sprintf("%04d", $ind[2]) .'-'. $ind[3] .'-'. $ind[4];
        if ($ind[5] != '') {
          $index .= '-' . $ind[5];
        }

        $datas[$index]['fName']         = $fName;
        $datas[$index]['tid']           = $tid;
        $datas[$index]['title']         = $title;
        $datas[$index]['count']         = $count;
        $datas[$index]['subtitle']      = $subtitle;
        $datas[$index]['mp4Exists']     = $mp4Exists;
        $datas[$index]['pid']           = $pid;
        $datas[$index]['mp4size']       = $mp4size;
        $datas[$index]['startdatetime'] = $startdatetime;
      }
    }
  }

  //$sort = array();
  //foreach ($datas as $file => $data) {
  //  //$sort[$data['tid']]   = $data['tid'];
  //  $sort[$data['tid']][] = $data;
  //}

  //natsort($datas);
  foreach ($datas as $file => $data) {
    //printf("<pre>%s</pre>", $file);
    //--
    print "
    <tr>
    <td rowspan='2'><INPUT TYPE='checkbox' NAME='delete[]' VALUE='${data['fName']}'><br></td>
    <td rowspan='1'>".  foldate2print($data['startdatetime']) ."</td>
    <td rowspan='2'><a href=\"http://cal.syoboi.jp/tid/${data['tid']}\" target=\"_blank\">${data['title']}</a><br><a href=\"./showlibc.php?tid=${data['tid']}\">[ライブラリ]</a></td>
    ";
    //if ($data['count']) {
      echo "<td rowspan='2'>${data['count']}<br></td>";
    //} else {
    //  echo '<td rowspan='2'>' . abs($data['pid']) . '<br></td>';
    //}
    echo "<td rowspan='2'>${data['subtitle']}<br></td>";

    print "<td rowspan='2'>";
    if ($data['mp4Exists']) {
      if ($data['pid']) {
        print "<a href=\"./mp4player.php?p=${data['pid']}\" target=\"_blank\">Player</a><br />${data[mp4size]}MB";
      } else {
        print "<a href=\"./mp4player.php?f=${data['fName']}\" target=\"_blank\">Player</a><br />${data[mp4size]}MB";
      }
    }
    print "</td>";

    if (file_exists("./selectcaptureimage.php") ) {
        if ($data['pid']) {
            print "<td rowspan='2' align=\"left\"><a href=\"./selectcaptureimage.php?pid=${data['pid']}\">キャプ</a></td>\n";
        } else {
            print "<td rowspan='2' align=\"left\"><a href=\"./selectcaptureimage.php?f=${data['fName']}\">キャプ</a></td>\n";
        }
    }

    print "</tr>\n ";
    print "<tr>\n ";
    print "<td rowspan='1'><A HREF=\"$httpmediamappath/${data['tid']}.localized/mp4/${data['fName']}\">${data['fName']}</A><br></td>";
    print "</tr>\n ";
  }
  print "   </tbody>\n</table>\n</FORM>";

  echo '<p align="left">';
  echo '$datas=' . count($datas) . '  ';
  echo '$nodata=' . count($nodata, COUNT_RECURSIVE) . '  ';
  echo '$mp4_exists=' . count($mp4_exists) .  '<br />';
  echo '</p>';
  page_display($list, $p, $p2, $lim, count($mp4files), "");

  print "\n</body>\n</html>\n";
  //$cnt = 0;
  //foreach ($sort as $k => $v) {
  //  $cnt += count($v);
  //  echo $k . '<br />';
  //}
  //echo $cnt;
  //echo '<pre>';
  //echo var_dump($nodata);
  //echo '</pre>';
  exit;

} else if ($list== "title") { //新仕様
  $query = "
  SELECT
    foltia_program.tid,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_m2pfiles.m2pfilename,
    foltia_subtitle.pid,
    foltia_subtitle.pspfilename,
    foltia_subtitle.startdatetime
  FROM foltia_subtitle, foltia_program, foltia_m2pfiles
  WHERE foltia_program.tid = foltia_subtitle.tid
    AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename
  ORDER BY foltia_subtitle.tid DESC, foltia_subtitle.startdatetime ASC
  LIMIT $lim OFFSET $st

  ";
} else {
  $query = "
  SELECT
    foltia_program.tid,
    foltia_program.title,
    foltia_subtitle.countno,
    foltia_subtitle.subtitle,
    foltia_m2pfiles.m2pfilename,
    foltia_subtitle.pid,
    foltia_subtitle.pspfilename,
    foltia_subtitle.lengthmin,
    foltia_subtitle.startdatetime
  FROM foltia_subtitle, foltia_program, foltia_m2pfiles
  WHERE foltia_program.tid = foltia_subtitle.tid
    AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename
  ORDER BY foltia_subtitle.startdatetime DESC
  LIMIT $lim OFFSET $st
  ";
}

$rs = sql_query($con, $query, "DBクエリに失敗しました");
$rowdata = $rs->fetch(PDO::FETCH_ASSOC);

/////////////////////////////////////////
//テーブルの総数取得
$query2 = "
  SELECT COUNT(*) AS cnt FROM foltia_subtitle , foltia_program , foltia_m2pfiles
    WHERE foltia_program.tid = foltia_subtitle.tid
      AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename
";
$rs2 = sql_query($con, $query2, "DBクエリに失敗しました");
$rowdata2 = $rs2->fetch(PDO::FETCH_ASSOC);
if (! $rowdata2) {
  die_exit("番組データがありません<br>");
}
$dtcnt =  $rowdata2['cnt'];
//クエリ代入
$query_st = $list;
page_display($query_st, $p, $p2, $lim, $dtcnt, "");

/////////////////////////////////////////

if ($rowdata) {

    do {
        $tid           = htmlspecialchars($rowdata['tid']);
        $title         = htmlspecialchars($rowdata['title']);
        $count         = htmlspecialchars($rowdata['countno']);
        $subtitle      = htmlspecialchars($rowdata['subtitle']);
        $fName         = htmlspecialchars($rowdata['m2pfilename']);
        $pid           = htmlspecialchars($rowdata['pid']);
        $mp4filename   = htmlspecialchars($rowdata['PSPfilename']);
        $lengthmin     = htmlspecialchars($rowdata['lengthmin']);
        $startdatetime = htmlspecialchars($rowdata['startdatetime']);

        $m2pExists = false;
        $m2pUrl    = $httpmediamappath . '/' . $fName;
        $m2ppath   = $recfolderpath . '/' . $fName;
        if (file_exists($m2ppath) && is_file($m2ppath) && filesize($m2ppath)) {
            $m2pExists = true;
        }

        $mp4Exists = false;
        $mp4Url    = $httpmediamappath . '/' . $tid . '.localized/mp4/' . $mp4filename;
        $mp4path   = $recfolderpath .'/' . $tid . '.localized/mp4/' . $mp4filename ;
        if (file_exists($mp4path) && is_file($mp4path)) {
            $mp4Exists = true;
            $mp4size = filesize($mp4path);
            $mp4size = round($mp4size / 1024 / 1024);
        }
        print "
        <tr>
        <td rowspan='2'><INPUT TYPE='checkbox' NAME='delete[]' VALUE='$fName'><br></td>";
        print "
        <td rowspan='1'>" . foldate2print($startdatetime) . "</td>\n";

        if ($tid > 0) {
            print"<td rowspan='2'><a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a><br><a href=\"./showlibc.php?tid=$tid\">[ライブラリ]</a></td>
            <td rowspan='2'>$count<br></td>
            <td rowspan='2'><a href = \"http://cal.syoboi.jp/tid/$tid/time#$pid\" target=\"_blank\">$subtitle</a><br></td>";
        } else {
            print"<td rowspan='2'>$title<br><a href=\"./showlibc.php?tid=$tid\">[ライブラリ]</a></td>
            <td rowspan='2'>$count<br></td>
            <td rowspan='2'>$subtitle<br></td>";
        }

        print "<td rowspan='2'>";
        if ($mp4Exists) {
            print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a><br />${mp4size}MB<br>${lengthmin}分";
        }
        print "</td>";

        if (file_exists("./selectcaptureimage.php") ) {
            $capimgpath = preg_replace("/.m2.+/", "", $fName);
            print "         <td rowspan='2' align=\"left\"><a href=\"./selectcaptureimage.php?pid=$pid\">キャプ</a></td>\n";
        }

        print "</tr>\n";
        print "<tr>\n";
        if (preg_match("/syabas/", $useragent)) {
            print "<td rowspan='1'><A HREF=\"./view_syabas.php?pid=$pid\" vod=playlist>$fName</td>";
        } else {
            echo '<td rowspan="1">';
            if ($m2pExists) {
                echo '<a href="'. $m2pUrl . '">' . $fName . '</a><br>';
            }
            if ($mp4Exists) {
                echo '<a href="'. $mp4Url . '" target="_blank">' . $mp4filename . '</a>';
            }
            echo '</td>';
        }
        print "</tr>\n";

    } while ($rowdata = $rs->fetch());
} else {

print "
<tr>
<td COLSPAN=\"5\">ファイルがありません</td>
</tr>
";

} //end if

print "</tbody>
</table>
</FORM>\n";

//////////////////////////////////////////////////////////////////////
//Autopageing処理とページのリンクを表示
list($p2, $page) = page_display($query_st, $p, $p2, $lim, $dtcnt, "");
//////////////////////////////////////////////////////////////////////
print "</div>"; //Auto pager終わり


//番組ソートの時、未読番組のタイトルだけ表示
//if ($list== "title" && $p2 > $page) {
if ($list== "title") {
    $query = "
      SELECT
        distinct foltia_program.tid,
        foltia_program.title
      FROM
        foltia_subtitle,
        foltia_program,
        foltia_m2pfiles
      WHERE foltia_program.tid = foltia_subtitle.tid
        AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename
      ORDER BY foltia_program.tid DESC
    ";

    //$rs = m_query($con, $query, "DBクエリに失敗しました");
    $rs = sql_query($con, $query, "DBクエリに失敗しました");
    $rowdata = $rs->fetch();
    if ($rowdata) {
        print "<hr>
          <p align=\"left\">未読タイトルを表示します。<br>
          <table BORDER=\"0\" CELLPADDING=\"0\" CELLSPACING=\"2\" WIDTH=\"100%\">
            <thead>
              <tr>
                <th align=\"left\">TID</th>
                <th align=\"left\">タイトル</th>
              </tr>
            </thead>
          <tbody>
        ";

        do {
            $tid = htmlspecialchars($rowdata[0]);
            $title = htmlspecialchars($rowdata[1]);
            print "<tr><td>$tid</td><td>$title</td></tr>\n";

        } while ($rowdata = $rs->fetch());
        print "</tbody></table>\n";
    } // if maxrows
} // if title

?>

</body>
</html>

