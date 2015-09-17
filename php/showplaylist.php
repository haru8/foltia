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

if ($useenvironmentpolicy == 1){
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
	    header("WWW-Authenticate: Basic realm=\"foltia\"");
	    header("HTTP/1.0 401 Unauthorized");
		redirectlogin();
	    exit;
	} else {
	login($con,$_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);
	}
}//end if login


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=EUC-JP">
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
list($st,$p,$p2) = number_page($p,$lim);
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
if ($demomode){
}else{
	printdiskusage();
	printtrcnprocesses();
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

  <table BORDER="0" CELLPADDING="0" CELLSPACING="2" WIDTH="100%" style="">
	<thead> 
		<tr> 
			<th align="left">削除</th>
			<th align="left" style="width:150px;"><A HREF="./showplaylist.php">ファイル名</A></th>
			<th align="left" style="width:350px;"><A HREF="./showplaylist.php?list=title">タイトル</A></th>
			<th align="left">話数</th>
			<th align="left">サブタイ</th>
			<th align="left" style="width:30px;">Player</th>

<?php
if (file_exists("./selectcaptureimage.php") ) {
print "			<th align=\"left\">キャプ</th>\n";
}
?>
		</tr>
	</thead> 

	<tbody>



<?php

//$list = getgetform('list');

//旧仕様
if($list == "raw"){
  exec ("ls -t  $recfolderpath/*.???", $m2pfiles);
  
  foreach($m2pfiles as $pathfName) {
    
    $fNametmp = split("/",$pathfName);
    $fName = array_pop($fNametmp);
    //print "FILENAME:$fName<BR>\n";
    
    if(($fName == ".") or ($fName == "..") ){ 
      continue;
    }
    if ((ereg(".m2.+", $fName))|| (ereg(".aac", $fName))){
      $filesplit = split("-",$fName);
      
      if (preg_match("/^\d+$/", $filesplit[0])) {//	print "File is looks like good:preg<br>\n";
        if ($filesplit[1] == ""){
          $query = "
          SELECT 
            foltia_program.tid,
            foltia_program.title,
            foltia_subtitle.subtitle ,
            foltia_subtitle.pid ,
            foltia_subtitle.m2pfilename ,
            foltia_subtitle.pspfilename
           FROM foltia_subtitle , foltia_program   
           WHERE foltia_program.tid = foltia_subtitle.tid  
             AND foltia_subtitle.tid = ? 
             AND foltia_subtitle.m2pfilename = ?
          ";
          //$rs = m_query($con, $query, "DBクエリに失敗しました");
          $rs = sql_query($con, $query, "DBクエリに失敗しました",array($filesplit[0], $fName));
          $rall = $rs->fetchAll();
          $rowdata = $rall[0];
          //print" $fName./$rowdata[1]//$rowdata[2]<BR>\n";
          $title = $rowdata[1];
          $pid  = htmlspecialchars($rowdata[3]);
          $mp4filename  = htmlspecialchars($rowdata[5]);
          $subtitle = "";
          $count = "";
          
        } else {
          
          $query = "
          SELECT 
            foltia_program.tid,
            foltia_program.title,
            foltia_subtitle.countno,
            foltia_subtitle.subtitle ,
            foltia_subtitle.pid ,
            foltia_subtitle.m2pfilename ,
            foltia_subtitle.pspfilename
           FROM foltia_subtitle , foltia_program   
           WHERE foltia_program.tid = foltia_subtitle.tid  
             AND foltia_subtitle.tid = ? 
             AND foltia_subtitle.countno = ? 
             AND foltia_subtitle.m2pfilename = ?
          ";
          //$rs = m_query($con, $query, "DBクエリに失敗しました");
          $rs = sql_query($con, $query, "DBクエリに失敗しました",array($filesplit[0], $filesplit[1], $fName));
          				$rall = $rs->fetchAll();
          				$rowdata = $rall[0];
          //print" $fName./$rowdata[1]/$rowdata[2]/$rowdata[3]<BR>\n";
          $title = $rowdata[1];
          $count = $rowdata[2];
          $subtitle = $rowdata[3];
          $pid  = htmlspecialchars($rowdata[4]);
          $mp4filename  = htmlspecialchars($rowdata[6]);
        }//if 話数あるかどうか
        
        $tid = htmlspecialchars($rowdata[0]);
        $title = htmlspecialchars($title);
        $count = htmlspecialchars($count);
        $subtitle = htmlspecialchars($subtitle);
        
        $mp4path      = "$recfolderpath/$tid.localized/mp4/$mp4filename" ;
        $mp4Exists = false;
        if (file_exists($mp4path) && is_file($mp4path)) {
          $mp4Exists = true;
          $mp4size = filesize($mp4path);
          $mp4size = round($mp4size / 1024 / 1024);
        }
        
        //--
        print "
        <tr>
        <td><INPUT TYPE='checkbox' NAME='delete[]' VALUE='$fName'><br></td>
        <td><A HREF=\"$httpmediamappath/$fName\">$fName</A><br></td>
        <td><a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a></td>
        <td>$count<br></td>
        <td>$subtitle<br></td>";
        
        print "<td>";
        if ($mp4Exists) {
          print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a><br />${mp4size}MB";
        }
        print "</td>";
        
        if (file_exists("./selectcaptureimage.php") ) {
          //  $capimgpath = preg_replace("/.m2p/", "", $fName);
          print "			<td align=\"left\"> N/A </td>\n";
        }
        
        print "</tr>\n
        ";
      } else {
      	//print "File is looks like BAD:preg<br>\n";
      }//
       
    }//ereg 
  }//foreach
  print "	</tbody>\n</table>\n</FORM>\n</body>\n</html>\n";
  exit;

} else if($list== 'mp4') {
  exec ("ls -t  $recfolderpath/*.localized/mp4/*.MP4 | head -2000", $mp4files);
  //exec ("ls -t  $recfolderpath/*.localized/mp4/*.MP4", $mp4files);

  $datas   = array();
  $nodata = array();
  foreach($mp4files as $pathfName) {
    //echo "<pre>$pathfName</pre>";
    $fNametmp = split('/', $pathfName);
    $fName    = array_pop($fNametmp);
    
    if(($fName == '.') || ($fName == '..') ){ 
      continue;
    }
    if (ereg('.MP4', $fName)) {
      $filesplit = split('-', $fName);
      $tid       = $filesplit[1];
      $num       = $filesplit[2];
      //echo "<pre>$fName, $filesplit[1], $filesplit[2]</pre>";
      if (preg_match("/^\d+$/", $tid)) {
        if ($num == '') {
          $query = ' SELECT 
                       foltia_program.tid,
                       foltia_program.title,
                       foltia_subtitle.subtitle,
                       foltia_subtitle.pid,
                       foltia_subtitle.m2pfilename,
                       foltia_subtitle.pspfilename
                      FROM foltia_subtitle, foltia_program
                      WHERE foltia_program.tid = foltia_subtitle.tid 
                       AND foltia_subtitle.tid = ? 
                       AND foltia_subtitle.PSPfilename = ? ';
          $rs      = sql_query($con, $query, 'DBクエリに失敗しました', array($tid, $fName));
          $rall    = $rs->fetchAll();
          $rowdata = $rall[0];
          //print" $fName./$rowdata[1]//$rowdata[2]<BR>\n";
          $title       = $rowdata[1];
          $pid         = htmlspecialchars($rowdata[3]);
          //$mp4filename = htmlspecialchars($rowdata[5]);
          $mp4filename = htmlspecialchars($fName);
          $subtitle    = $rowdata[2];
          $count       = $num;
        } else {
          $query = ' SELECT 
                       foltia_program.tid,
                       foltia_program.title,
                       foltia_subtitle.countno,
                       foltia_subtitle.subtitle,
                       foltia_subtitle.pid,
                       foltia_subtitle.m2pfilename,
                       foltia_subtitle.pspfilename
                      FROM foltia_subtitle, foltia_program
                      WHERE foltia_program.tid = foltia_subtitle.tid
                        AND foltia_subtitle.tid = ?
                        AND foltia_subtitle.countno = ? 
                        AND foltia_subtitle.PSPfilename = ? ';
          $rs      = sql_query($con, $query, "DBクエリに失敗しました", array($tid, $num, $fName));
          $rall    = $rs->fetchAll();
          $rowdata = $rall[0];
          //print" $fName./$rowdata[1]/$rowdata[2]/$rowdata[3]<BR>\n";
          $title       = $rowdata[1];
          $count       = $rowdata[2];
          $subtitle    = $rowdata[3];
          $pid         = htmlspecialchars($rowdata[4]);
          //$mp4filename = htmlspecialchars($rowdata[6]);
          $mp4filename = htmlspecialchars($fName);
        }

        $tid      = htmlspecialchars($tid);
        $title    = htmlspecialchars($title);
        $count    = $num;
        $subtitle = htmlspecialchars($subtitle);
        
        $mp4path   = "$recfolderpath/$tid.localized/mp4/$mp4filename" ;
        $mp4Exists = false;
        if (file_exists($mp4path) && is_file($mp4path)) {
          $mp4Exists = true;
          $mp4size   = filesize($mp4path);
          $mp4size   = round($mp4size / 1024 / 1024);
          if ($pid) {
            $nodata[$tid]   = $tid;
            $nodata[$tid][$count] = $count;
          }
        }

        $ind   = explode('-', $fName);
        if (!isset($ind[5])) {
          $ind[5] = '';
        }
        $index = $ind[0] .'-'. sprintf("%05d", $ind[1]) .'-'. sprintf("%04d", $ind[2]) .'-'. $ind[3] .'-'. $ind[4] .'-'. $ind[5];
//printf("<pre> %s </pre>\n", $index);

        $datas[$index]['fName']     = $fName;
        $datas[$index]['tid']       = $tid;
        $datas[$index]['title']     = $title;
        $datas[$index]['count']     = $count;
        $datas[$index]['subtitle']  = $subtitle;
        $datas[$index]['mp4Exists'] = $mp4Exists;
        $datas[$index]['pid']       = $pid;
        $datas[$index]['mp4size']   = $mp4size;
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
    <td><INPUT TYPE='checkbox' NAME='delete[]' VALUE='${data['fName']}'><br></td>
    <td><A HREF=\"$httpmediamappath/${data['tid']}.localized/mp4/${data['fName']}\">${data['fName']}</A><br></td>
    <td><a href=\"http://cal.syoboi.jp/tid/${data['tid']}\" target=\"_blank\">${data['title']}</a></td>
    <td>${data['count']}<br></td>
    <td>${data['subtitle']}<br></td>";
    
    print "<td>";
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
	    print "<td align=\"left\"><a href=\"./selectcaptureimage.php?pid=${data['pid']}\">キャプ</a></td>\n";
      } else {
	    print "<td align=\"left\"><a href=\"./selectcaptureimage.php?f=${data['fName']}\">キャプ</a></td>\n";
      }
	}
    
    print "</tr>\n
    ";
  }
  print "	</tbody>\n</table>\n</FORM>\n</body>\n</html>\n";

  echo '$datas=' . count($datas) . '  ';
  echo '$nodata=' . count($nodata) . '<br />';
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

} else if ($list== "title"){//新仕様
  $query = "
  SELECT 
  foltia_program.tid,
  foltia_program.title,
  foltia_subtitle.countno,
  foltia_subtitle.subtitle  ,
  foltia_m2pfiles.m2pfilename  ,
  foltia_subtitle.pid ,
  foltia_subtitle.pspfilename
  FROM foltia_subtitle , foltia_program , foltia_m2pfiles 
  WHERE foltia_program.tid = foltia_subtitle.tid  
   AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename 
  ORDER BY foltia_subtitle.tid  DESC , foltia_subtitle.startdatetime  ASC 
  LIMIT $lim OFFSET $st
  
  ";
} else {
  $query = "
  SELECT 
  foltia_program.tid,
  foltia_program.title,
  foltia_subtitle.countno,
  foltia_subtitle.subtitle  ,
  foltia_m2pfiles.m2pfilename  ,
  foltia_subtitle.pid ,
  foltia_subtitle.pspfilename
  FROM foltia_subtitle , foltia_program , foltia_m2pfiles 
  WHERE foltia_program.tid = foltia_subtitle.tid  
   AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename 
  ORDER BY foltia_subtitle.startdatetime DESC 
  LIMIT $lim OFFSET $st
  ";
}

//$rs = m_query($con, $query, "DBクエリに失敗しました");
$rs = sql_query($con, $query, "DBクエリに失敗しました");
$rowdata = $rs->fetch();

/////////////////////////////////////////
//テーブルの総数取得
       $query2 = "
SELECT COUNT(*) AS cnt FROM foltia_subtitle , foltia_program , foltia_m2pfiles
WHERE foltia_program.tid = foltia_subtitle.tid
 AND foltia_subtitle.m2pfilename = foltia_m2pfiles.m2pfilename
        ";
$rs2 = sql_query($con, $query2, "DB\?\ィ\e?E?oCO???T????");
$rowdata2 = $rs2->fetch();
if (! $rowdata2) {
  die_exit("番組データがありません<BR>");
}
//1O?o?eAA
$dtcnt =  $rowdata2[0];

/////////////////////////////////////////

if ($rowdata) {

	do {
$tid          = htmlspecialchars($rowdata[0]);
$title        = htmlspecialchars($rowdata[1]);
$count        = htmlspecialchars($rowdata[2]);
$subtitle     = htmlspecialchars($rowdata[3]);
$fName        = htmlspecialchars($rowdata[4]);
$pid          = htmlspecialchars($rowdata[5]);
$mp4filename  = htmlspecialchars($rowdata[6]);
$mp4path      = "$recfolderpath/$tid.localized/mp4/$mp4filename" ;
//--


print "
<tr>
<td><INPUT TYPE='checkbox' NAME='delete[]' VALUE='$fName'><br></td>";
$mp4Exists = false;
if (file_exists($mp4path) && is_file($mp4path)) {
  $mp4Exists = true;
  $mp4size = filesize($mp4path);
  $mp4size = round($mp4size / 1024 / 1024);
}
if (ereg("syabas",$useragent)){
print "<td><A HREF=\"./view_syabas.php?pid=$pid\" vod=playlist>$fName</td>";
} else {
print "<td><A HREF=\"$httpmediamappath/$fName\">$fName</A><br></td>";
}
if ($tid > 0){
print"<td><a href=\"http://cal.syoboi.jp/tid/$tid\" target=\"_blank\">$title</a><br><a href=\"./showlibc.php?tid=$tid\">[ライブラリ]</a></td>
<td>$count<br></td>
<td><a href = \"http://cal.syoboi.jp/tid/$tid/time#$pid\" target=\"_blank\">$subtitle</a><br></td>";
}else{
print"<td>$title<br><a href=\"./showlibc.php?tid=$tid\">[ライブラリ]</a></td>
<td>$count<br></td>
<td>$subtitle<br></td>";
}

print "<td>";
if ($mp4Exists) {
  print "<a href=\"./mp4player.php?p=$pid\" target=\"_blank\">Player</a><br />${mp4size}MB";
}
print "</td>";

	if (file_exists("./selectcaptureimage.php") ) {
	  $capimgpath = preg_replace("/.m2.+/", "", $fName);
	  print "			<td align=\"left\"><a href=\"./selectcaptureimage.php?pid=$pid\">キャプ</a></td>\n";
	}

print "</tr>\n
";



	} while ($rowdata = $rs->fetch());
}else{

print "
<tr>
<td COLSPAN=\"5\">ファイルがありません</td>
</tr>
";


}//end if

print "</tbody>
</table>
</FORM>\n";

//////////////////////////////////////////////////////////////////////
//クエリ代入
$query_st = $list;
//Autopageing処理とページのリンクを表示
list($p2,$page) = page_display($query_st,$p,$p2,$lim,$dtcnt,"");
//////////////////////////////////////////////////////////////////////
print "</div>";	//Auto pager終わり


//番組ソートの時、未読番組のタイトルだけ表示
//if ($list== "title" && $p2 > $page){
if ($list== "title"){

$query = "
SELECT distinct
foltia_program.tid,
foltia_program.title 
FROM foltia_subtitle , foltia_program , foltia_m2pfiles 
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
}//if maxrows
}//if title


?>

</body>
</html>
