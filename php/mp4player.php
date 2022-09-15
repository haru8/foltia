<?php
/*
 Anime recording system foltia
 http://www.dcc-jpl.com/soft/foltia/

mp4player.php

目的
HTML5 Video Playerを使ってMP4の再生をします

based HTML5 Video Player | VideoJS http://videojs.com/

引数
f:再生ファイル名

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

$pid  = getgetnumform('p');
$file = getgetform('f');

if ($pid != "") {
    $query = "
      SELECT
        title,
        countno,
        subtitle,
        foltia_subtitle.tid,
        PSPfilename
      FROM
        foltia_subtitle,
        foltia_program
      WHERE pid = ?
        AND foltia_subtitle.tid = foltia_program.tid
    ";

    $rs = sql_query($con, $query, "DBクエリに失敗しました", array($pid));
    $rowdata = $rs->fetch();

    //$title = htmlspecialchars(mb_convert_encoding($rowdata[0], "UTF-8"));
    $title = htmlspecialchars($rowdata[0]);
    if ($rowdata[1] == "") {
        $countno = "";
    } else {
        $countno = "第".htmlspecialchars($rowdata[1])."話";
    }
    //$subtitle = htmlspecialchars(mb_convert_encoding($rowdata[2], "UTF-8"));
    $subtitle = htmlspecialchars($rowdata[2]);
    $tid      = htmlspecialchars($rowdata[3]);
    $filename = htmlspecialchars($rowdata[4]);

} else if ($file != "") {
    $filesplit = explode('-', htmlspecialchars($file));
    $title     = '';
    $subtitle  = '';
    $countno   = "第".htmlspecialchars($filesplit[2])."話";
    $tid       = htmlspecialchars($filesplit[1]);
    $filename  = htmlspecialchars($file);
    //echo "<pre>";
    //var_dump($filesplit);
    //echo "</pre>";
} else {
    // 引数なしエラー処理

    header("Status: 404 Not Found",TRUE,404);
    print "<!DOCTYPE html>
      <html>
      <head>
      <meta charset=\"UTF-8\" />\n";
    print "  <title>foltia HTML5 Video Player</title></head><body>No pid.</body></html>";
    exit ;
}

if ($filename == "") {
    // ファイルなしなしエラー処理
    header("Status: 404 Not Found",TRUE,404);
    print "<!DOCTYPE html>
      <html>
      <head>
        <meta charset=\"UTF-8\" />\n";
    print "  <title>foltia HTML5 Video Player</title></head><body>File not found.</body></html>";
    exit ;
}

print "<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\" />\n\n
<title>foltia HTML5 Video Player / $title $countno $subtitle</title>\n";
//$mp4videofileurl =  "https://". getserverfqdn() ."$httpmediamappath/$tid.localized/mp4/$filename";
$mp4videofileurl = "$httpmediamappath/$tid.localized/mp4/$filename";

?>

  <!-- Include the VideoJS Library -->
  <!--
  <script src="./video-js/video.js" type="text/javascript" charset="UTF-8"></script>
  -->

  <script type="text/javascript" charset="UTF-8">
    // Run the script on page load.

    // If using jQuery
    // $(function(){
    //   VideoJS.setup();
    // })

    // If using Prototype
    // document.observe("dom:loaded", function() {
    //   VideoJS.setup();
    // });

    // If not using a JS library
    //window.onload = function(){
    //  VideoJS.setup();
    //}

  </script>
  <!-- Include the VideoJS Stylesheet -->
  <!--
  <link rel="stylesheet" href="./video-js/video-js.css" type="text/css" media="screen" title="Video JS" charset="UTF-8">
  -->
</head>
<body>

<?php
print "
  <div class=\"video-js-box\">
    <video class=\"video-js\" width=\"640\" height=\"360\" poster=\"./img/videoplayer.png\" controls preload>
      <source src=\"$mp4videofileurl\" type='video/mp4; codecs=\"avc1.42E01E, mp4a.40.2\"'>
    </video>
  </div>
"
?>
</body>
</html>

