<?php

include("./foltia_config2.php");

/*
こちらのモジュールは
Apache + PHP + PostgreSQL 実験室
http://www.hizlab.net/app/
のサンプルを使わせていただいております。
ありがとうございます。
*/

/* エラー表示の抑制 */
//error_reporting(0);


function slackSend($head, $mesg='') {
    global $slack_webhook_url;
    if (!$slack_webhook_url) {
        return false;
    }

    $channel    = '';
    $username   = 'foltiaBot';
    $icon_emoji = '';

    $timestump  = date("Y/m/d H:i:s");
    $message    = '`' . $head . "`\n" . '```' . $timestump . "\n" . $mesg . '```';

    $info = array(
        'url'  => $slack_webhook_url,
        'body' => array(
            'payload' => json_encode(array(
                'channel'    => $channel,
                'username'   => $username,
                'icon_emoji' => $icon_emoji,
                'text'       => $message,
                )),
            ),
        );

    $options = array(
            CURLOPT_URL            => $info['url'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $info['body'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 5,
        );

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result      = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header      = substr($result, 0, $header_size);
    $result      = substr($result, $header_size);
    curl_close($ch);

    //var_dump($header);
    //var_dump($result);
    //return array(
    //        'Header' => $header,
    //        'Result' => $result,
    //    );
}

//GET用フォームデコード
function getgetform($key) {
    if ($_GET["{$key}"] != "") {
        $value = $_GET["{$key}"];
        $value = escape_string($value);
        $value = htmlspecialchars($value);
        return ($value);
    }
}

//GET用数字フォームデコード
function getgetnumform($key) {
    if (isset($_GET["{$key}"] )) {
        $value = $_GET["{$key}"];
        $value = preg_replace("/[^-0-9]/", "", $value);
        $value = escape_numeric($value);
        return ($value);
    }
}

//フォームデコード
function getform($key) {
    if ($_POST["{$key}"] != "") {
        $value = $_POST["{$key}"];
        $value = escape_string($value);
        $value = htmlspecialchars($value);
        return ($value);
    }
}

//数字専用フォームデコード
function getnumform($key) {
    if ($_POST["{$key}"] != "") {
        $value = $_POST["{$key}"];
        $value = escape_string($value);
        $value = htmlspecialchars($value);
        $value = preg_replace("/[^0-9]/", "", $value);
        $value = escape_numeric($value);
        return ($value);
    }
}

/* 全角カタカナ化してスペースを削除してインデックス用にする */
function name2read($name) {
    $name = mb_convert_kana($name, "KVC", "UTF-8");
    $name = mb_convert_kana($name, "s", "UTF-8");
    $name = preg_replace("/ /", "", $name);

    return $name;
}

/* 数字を半角化して数字化してインデックス用にする */
function pnum2dnum($num) {
    $num = mb_convert_kana($num, "a", "UTF-8");
    $num = preg_replace("/[^0-9]/", "", $num);

    return $num;
}

/* 終了関数の定義 */
function die_exit($message) {
        ?>
        <p class="error"><?php print "$message"; ?></p>
        <div class="index"><a href="./">トップ</a></div>
    </body>
</html><?php
        exit;
}

/* 入力した値のサイズをチェック */
function check_length($str, $maxlen, $must, $name) {
    $len = strlen($str);
    if ($must && $len == 0) {
        die_exit("$name が入力されてません。必須項目です。");
    }
    if ($len > $maxlen) {
        die_exit("$name は $len 文字以下で入力して下さい。全角文字は、一文字で二文字分と計算されます。");
    }
}

/* SQL 文字列のエスケープ */
function escape_string($sql, $quote = FALSE) {
    if ($quote && strlen($sql) == 0) {
        return "null";
    }
    if (preg_match("/^pgsql/", DSN)) {
        return ($quote ? "'" : "") .
            pg_escape_string($sql) .
            ($quote ? "'" : "");
    } else if (preg_match("/^sqlite/", DSN)) {
        /*  return ($quote ? "'" : "") .
                sqlite_escape_string($sql) .
                ($quote ? "'" : "");
        */
        return($sql);
    } else {
            return "null";
    }
}

/* SQL 数値のエスケープ */
function escape_numeric($sql) {
    if (strlen($sql) == 0) {
        return "null";
    }
    if (!is_numeric($sql)) {
        die_exit("$sql は数値ではありません。");
    }
    return $sql;
}

/* DBに接続 */
function m_connect() {
    try {
        $dbh = new PDO(DSN);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return($dbh);
    } catch (PDOException $e) {
        die_exit($e->getMessage() . ": データベースに接続出来ませんでした。");
    }
    /* データベースと、PHP の内部文字コードが違う場合 */
}

/* データベースとの接続を切り離す */
function m_close($dbh) {
    return null;
}

//旧関数　sql_queryに置き換え
function m_query($dbh, $query, $errmessage) {
    try {
        $rtn = $dbh->query($query);
        return($rtn);
    } catch (PDOException $e) {
        /* エラーメッセージに SQL 文を出すのはセキュリティ上良くない！！ */
        $msg = $errmessage . "<br>\n" .
        $e->getMessage() . "<br>\n" .
        var_export($e->errorInfo, true) . "<br>\n" .
            "<small><code>" . htmlspecialchars($query) .
            "</code></small>\n";
        //$dbh->rollBack();
        $dbh = null;
        die_exit($msg);
    }
}

/* SQL 文を実行 */
function sql_query($dbh, $query, $errmessage, $paramarray=null) {
    try {
        $rtn = $dbh->prepare("$query");
        $rtn->execute($paramarray);
        return($rtn);
    } catch (PDOException $e) {
        /* エラーメッセージに SQL 文を出すのはセキュリティ上良くない！！ */
        $msg = $errmessage . "<br>\n" .
        $e->getMessage() . "<br>\n" .
            var_export($e->errorInfo, true) . "<br>\n" .
            "<small><code>" . htmlspecialchars($query) .
            "</code></small>\n";
        //$dbh->rollBack();
        $dbh = null;
        die_exit($msg);
    }
}

    /* select した結果をテーブルで表示 */
function m_showtable($rs) {
    /* 検索件数 */
    $maxrows = 0;

    $rowdata = $rs->fetch();
    if (! $rowdata) {
        echo("<p class=\"msg\">データが存在しません</p>\n");
        return 0;
    }

    /* フィールド数 */
    $maxcols = $rs->columnCount();
    ?>
        <table class="list" summary="データ検索結果を表示" border="1">
        <thead>
        <tr>
        <?php
        /* テーブルのヘッダーを出力 */
        for ($col = 1; $col < $maxcols; $col++) {
            /* pg_field_name() はフィールド名を返す */
            $meta = $rs->getColumnMeta($col);
            $f_name = htmlspecialchars($meta["name"]);
            echo("<th abbr=\"$f_name\">$f_name</th>\n");
        }
    ?>
        </tr>
        </thead>
        <tbody>
        <?php
        /* テーブルのデータを出力 */
        do {
            $maxrows++;

            echo("<tr>\n");
            /* １列目にリンクを張る */
            echo("<td><a href=\"edit.php?q_code=" .
                    urlencode($rowdata[0]) . "\">" .
                    htmlspecialchars($rowdata[1]) . "</a></td>\n");
            for ($col = 2; $col < $maxcols; $col++) { /* 列に対応 */
                echo("<td>".htmlspecialchars($rowdata[$col])."<br></td>\n");
            }
            echo("</tr>\n");
        } while ($rowdata = $rs->fetch());
    ?>
        </tbody>
        </table>
        <?php
        return $maxrows;
}

function m_viewdata($dbh, $code) {
    /*これ使ってないよね?*/
}

function printhtmlpageheader() {

    global $useenvironmentpolicy;

    $serveruri = getserveruri();
    $username = $_SERVER['PHP_AUTH_USER'];

    print "
  <p align=\"left\">
    <font color='#494949'>
    <a href = 'http://www.dcc-jpl.com/soft/foltia/' target=\"_blank\">foltia</a>　|
    <a href = './index.php'>放映予定</a> |
    <a href = './index.php?mode=new'>新番組</a> |
    <a href = './listreserve.php'>予約一覧</a> |
    <a href = './titlelist.php'>番組一覧</a> |
    <a href = './viewepg.php'>番組表</a>(<a href = './searchepg.php'>検索</a>) |
    録画一覧(<a href = './showplaylist.php'>録画順</a>・
    <a hreF = './showplaylist.php?list=title'>番組順</a>・
    <a href = './showplaylist.php?list=raw'>全</a>・
    <a href = './showplaylist.php?list=mp4&head=300'>全(mp4)</a>・
    <a href=\"./searchplaylist.php\">検索</a>) |
    <a href = './showlib.php'>録画ライブラリ</a> |
    <a href = './folcast.php'>Folcast</a>[<a href=\"itpc://$serveruri/folcast.php\">iTunesに登録</a>]\n";
    if ($useenvironmentpolicy == 1) {
        print "【 $username 】";
    }

    print "    </font>
  </p>\n\n";
}

function renderepgstation($con,$stationname,$start) { //戻り値 なし EPGの局表示

    $now = date("YmdHi");
    $today = date("Ymd");
    $tomorrow = date ("Ymd",mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")));
    //$today = "20051013";
    //$tomorrow = "20051014";
    //$epgstart = $today . "2000";
    $epgstart = $start ;
    //$epgend = $tomorrow . "0400";
    $epgend = calcendtime($start , (8*60));
    $query = "
        SELECT startdatetime , enddatetime , lengthmin , epgtitle , epgdesc , epgcategory  ,ontvchannel  ,epgid
        FROM foltia_epg
        WHERE foltia_epg.ontvchannel = '$stationname' AND
        enddatetime  > $epgstart  AND
        startdatetime  < $epgend
        ORDER BY foltia_epg.startdatetime  ASC
        ";
    $rs = m_query($con, $query, "DBクエリに失敗しました");
    $rowdata = $rs->fetch();
    if (! $rowdata) {
        print("番組データがありません<BR>");
    } else {
        print "<table width=\"100%\"  border=\"0\">\n";
        //print "<ul><!-- ($maxrows) $query -->\n";

        do {
            $printstarttime = substr($rowdata[0],8,2) . ":" .  substr($rowdata[0],10,2);
            $tdclass = "t".substr($rowdata[0],8,2) .  substr($rowdata[0],10,2);
            $title = htmlspecialchars($rowdata[3]);
            $title = z2h($title);
            $desc = htmlspecialchars($rowdata[4]);
            $desc = z2h($desc);
            $height =  htmlspecialchars($rowdata[2]) * 3;
            $epgid =  htmlspecialchars($rowdata[7]);

            print"
                <tr>
                <td height = \"$height\" >$printstarttime  <A HREF=\"./reserveepg.php?epgid=$epgid\">$title</A> $desc <!-- $rowdata[0] - $rowdata[1] --></td>
                </tr>
                ";
            /*print"<li style=\"height:" . $height ."px;\" class=\"$tdclass\">
              $printstarttime  <A HREF=\"./reserveepg.php?epgid=$epgid\">$title</A> $desc($rowdata[0] - $rowdata[1])
              </li>\n";
             */
        } while ($rowdata = $rs->fetch());//do
        //print "</ul>\n";
        print "</table>\n";

    }//if
}//end function

function calcendtime($start, $lengthmin) { // 戻り値 終了時刻(Ex:200510170130)
    $startyear  = substr($start,  0, 4);
    $startmonth = substr($start,  4, 2);
    $startday   = substr($start,  6, 2);
    $starthour  = substr($start,  8, 2);
    $startmin   = substr($start, 10, 2);
    //int mktime ( [int hour [, int minute [, int second [, int month [, int day [, int year [, int is_dst]]]]]]] )
    $endtime = date ("YmdHi", mktime($starthour, $startmin + $lengthmin, 0, $startmonth, $startday, $startyear));

    return ($endtime );
} // end function


function z2h($string) { //戻り値　半角化した文字
    $stringh = mb_convert_kana($string, "a", "UTF-8");
    return ($stringh );
}

function foldate2rfc822($start) {//戻り値　RFC822スタイルの時刻表記
    $startyear  = substr($start,0,4);
    $startmonth = substr($start,4,2);
    $startday   = substr($start,6,2);
    $starthour  = substr($start,8,2);
    $startmin   = substr($start,10,2);

    $rfc822 = date ("r",mktime($starthour  , $startmin , 0, $startmonth  , $startday, $startyear));

    return ($rfc822);
} //end sub

function foldate2print($start) { //戻り値 日本語風時刻表記
    $startyear  = substr($start,  0, 4);
    $startmonth = substr($start,  4, 2);
    $startday   = substr($start,  6, 2);
    $starthour  = substr($start,  8, 2);
    $startmin   = substr($start, 10, 2);

    $timestamp = mktime($starthour, $startmin, 0, $startmonth, $startday, (int)$startyear);
    $weekjp = '(' . dateW2kanji(date("w", $timestamp)) . ')';
    $printDate = date("Y/m/d", $timestamp);
    $printTime = date("H:i", $timestamp);
    $printabledate = $printDate . $weekjp . ' ' . $printTime;
    return ($printabledate);
} //end sub

function dateW2kanji ($w) {
    $weekjp = array(
        '日', // 0
        '月', // 1
        '火', // 2
        '水', // 3
        '木', // 4
        '金', // 5
        '土'  // 6
    );

    return isset($weekjp[$w]) ? $weekjp[$w] : '';
}

function getserveruri() { //戻り値　サーバアドレス Ex.www.dcc-jpl.com:8800/soft/foltia/

    //リンクURI組み立て
    $sv6 = $_SERVER['SCRIPT_NAME'];///dameNews/sarasorjyu/archives.php
    $sv8 = $_SERVER['SERVER_NAME'];//sync.dcc-jpl.com
    $sv9 = $_SERVER['SERVER_PORT'];
    if ($sv9 == 80) {
        $port = "";
    } else {
        $port = ":$sv9";
    }
    $a = explode("/", $sv6);
    array_pop($a);

    $scriptpath = implode("/", $a);

    $serveruri = "$sv8$port$scriptpath";
    return ($serveruri );
} //end sub

function getserverfqdn() { //戻り値　サーバアドレス Ex.www.dcc-jpl.com:8800

    //リンクURI組み立て
    $sv6 = $_SERVER['SCRIPT_NAME'];///dameNews/sarasorjyu/archives.php
    $sv8 = $_SERVER['SERVER_NAME'];//sync.dcc-jpl.com
    $sv9 = $_SERVER['SERVER_PORT'];
    if ($sv9 == 80) {
        $port = "";
    } else {
        $port = ":$sv9";
    }
    $a = explode("/", $sv6);
    array_pop($a);

    $scriptpath = implode("/", $a);

    $serveruri = "$sv8$port";
    return ($serveruri );
} //end sub


function printdiskusage() {//戻り値　なし
    list (, $all, $use , $free, $usepercent) =  getdiskusage();

    print "
        <div style=\"width:100%;border:1px solid black;text-align:left;\"><span style=\"float:right;\">$free</span>
        <div style=\"width:$usepercent;border:1px solid black;background:white;\">$use/$all($usepercent)</div>
        </div>
        ";
    //exec('ps ax | grep ffmpeg |grep MP4 ' ,$ffmpegprocesses);
} //end sub


function getdiskusage() {//戻り値　配列　[,全体容量, 使用容量 , 空き容量, 利用割合]

    global $recfolderpath,$recfolderpath;

    //  exec ( "df -h  $recfolderpath | grep $recfolderpath", $hdfreearea);
    //  $freearea = preg_split ("/[\s,]+/", $hdfreearea[0]);
    exec ( "df -hP  $recfolderpath", $hdfreearea);
    $freearea = preg_split ("/[\s,]+/", $hdfreearea[count($hdfreearea)-1]);

    return $freearea;

} //endsub


function printtrcnprocesses() {
    $ffmpegprocesses = `ps ax | grep ffmpeg | grep -v grep |  wc -l`;
    $uptime = exec('uptime');

    print "<div style=\"text-align:left;\">";
    print "$uptime<br>\n";
    print "トラコン稼働数 : $ffmpegprocesses<br>\n";
    print "</div>";
} //endsub

function printrecpt1processes() {
    $recpt1processes = `ps ax | grep recpt1 | grep -v grep |  wc -l`;

    print "<div style=\"text-align:left;\">";
    print "録画稼働数 : $recpt1processes<br>\n";
    print "</div>";
}


function warndiskfreearea() {

    global $demomode;

    if ($demomode) {
        print "<!-- demo mode -->";
    } else {

        global $recfolderpath,$hdfreearea ;

        exec ( "df   $recfolderpath | grep $recfolderpath", $hdfreearea);
        $freearea = preg_split ("/[\s,]+/", $hdfreearea[0]);
        $freebytes = $freearea[3];
        if ($freebytes == "" ){
            //
            //print "<!-- err:\$freebytes is null -->";
        }elseif($freebytes > 1024*1024*100 ){// 100GB以上あいてれば
            //なにもしない
            print "<style type=\"text/css\"><!-- --></style>";
        }elseif($freebytes > 1024*1024*50 ){// 100GB以下
            print "<style type=\"text/css\"><!--
                body {
                    background-color: #CCCC99;
                }
            -->
                </style>
                ";
        }elseif($freebytes > 1024*1024*30 ){// 50GB以下
            print "<style type=\"text/css\"><!--
                body {
                    background-color:#CC6666;
                }
            -->
                </style>
                ";
        }elseif($freebytes > 0 ){// 30GB以下
            print "<style type=\"text/css\"><!--
                body {
                    background-color:#FF0000;
                }
            -->
                </style>
                ";
        }else{ //空き容量 0バイト
            print "<style type=\"text/css\"><!--
                body {
                    background-color:#000000;
                }
            -->
                </style>
                ";
        }//endif freebytess

    } //endif demomode

} //endsub



function foldatevalidation($foldate) {

    if (strlen($foldate) == 12 ) {
        $startyear  = substr($foldate,  0, 4);
        $startmonth = substr($foldate,  4, 2);
        $startday   = substr($foldate,  6, 2);
        $starthour  = substr($foldate,  8, 2);
        $startmin   = substr($foldate, 10, 2);

        $startepoch = date ("U", mktime($starthour, $startmin, 0, $startmonth, $startday, $startyear));
        $nowe = time();
        if ($startepoch > $nowe) {
            //print "$foldate:$startepoch:$nowe";
            return TRUE;
        } else {
            return FALSE;
        }   //end if $startepoch > $nowe
    } else {
        return FALSE;
    }//end if ($foldate) == 12

} //end function



function login($con,$name,$passwd) {
    global $environmentpolicytoken;

    //入力内容確認
    if (((mb_ereg('[^0-9a-zA-Z]', $name)) ||(mb_ereg('[^0-9a-zA-Z]', $passwd) ))) {

        //print "エラー処理\n";
        //print "<!-- DEBUG name/passwd format error-->";
        redirectlogin();

    } else {
        //print "正常処理\n";
        //db検索
        escape_string($name);
        escape_string($passwd);

        $query = "
            SELECT memberid, userclass, name, passwd1
            FROM foltia_envpolicy
            WHERE foltia_envpolicy.name  = '$name'
            ";
        $useraccount = m_query($con, $query, "DBクエリに失敗しました");
        $rowdata = $useraccount->fetch();
        if (! $rowdata) {
            header("HTTP/1.0 401 Unauthorized");
            redirectlogin();
        }

        $memberid   = $rowdata[0];
        $userclass  = $rowdata[1];
        $username   = $rowdata[2];
        $dbpasswd   = $rowdata[3];

        $rowdata = $useraccount->fetch();
        if ($rowdata) {
            header("HTTP/1.0 401 Unauthorized");
            redirectlogin();
        }

        // passwdをdbから取りだし
        if ($userclass == 0) {
            $dbpasswd = "$dbpasswd";
        } else {
            // db passwdとトークンを連結し
            $dbpasswd = "$dbpasswd" . "$environmentpolicytoken";
        }
        //それが入力と一致すれば認証
        if ($passwd == $dbpasswd) {
            //print "認証成功<br>$dbpasswd  $passwd\n";
        } else {
            //print "認証失敗<br>$dbpasswd  $passwd\n";
            header("HTTP/1.0 401 Unauthorized");
            //print "<!-- DEBUG passwd unmatch error>";
            redirectlogin();
        }
    } //end if mb_ereg
} //end function login


function redirectlogin() {
    global $environmentpolicytoken;

    print "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n";
    print "<html><head>\n";
    print "<title>foltia:Invalid login</title>\n";
    print "</head><body>\n";
    print "<h1>Invalid login</h1>";
    print "<p>foltiaヘのアクセスにはログインが必要です。再ログインはリロードやブラウザ再起動で、新規アカウント登録は<a href=\"./accountregist.php\">こちらから。</a></p>";
    if ($environmentpolicytoken == "") {
    } else {
        print "<p>突然この画面が表示された場合にはセキュリティコードが変更されたかも知れません。</p>";
    }
    print "</p><hr>\n";
    print "<address>foltia by DCC-JPL Japan/foltia Project.  <a href = \"http://www.dcc-jpl.com/soft/foltia/\">http://www.dcc-jpl.com/soft/foltia/</a></address>\n";
    print "</body></html>\n";

    exit;
} //end function redirectlogin

function getuserclass($con) {
    global $useenvironmentpolicy;
    $username = $_SERVER['PHP_AUTH_USER'];

    if ($useenvironmentpolicy == 1) {
        $query = "
            SELECT memberid ,userclass,name,passwd1
            FROM foltia_envpolicy
            WHERE foltia_envpolicy.name  = '$username'
            ";
        $useraccount = m_query($con, $query, "DBクエリに失敗しました");
        $rowdata = $useraccount->fetch();
        if (! $rowdata) {
            return (99);
        }

        $userclass = $rowdata[1];

        $rowdata = $useraccount->fetch();
        if ($rowdata) {
            return (99);
        }

        return ($userclass);

    } else {
        return (0);//環境ポリシー使わないときはつねに特権モード
    } //end if
} //end function getuserclass



function getmymemberid($con) {
    global $useenvironmentpolicy;
    $username = $_SERVER['PHP_AUTH_USER'];

    if ($useenvironmentpolicy == 1) {
        $query = "
            SELECT memberid ,userclass,name,passwd1
            FROM foltia_envpolicy
            WHERE foltia_envpolicy.name  = '$username'
            ";
        $useraccount = m_query($con, $query, "DBクエリに失敗しました");
        $rowdata = $useraccount->fetch();
        if (! $rowdata) {
            return (-1);//エラー
        }

        $memberid = $rowdata[0];

        $rowdata = $useraccount->fetch();
        if ($rowdata) {
            return (-1);
        }

        return ($memberid);

    } else {
        return (0);//環境ポリシー使わないときはつねに特権モード
    }//end if
}//end function getuserclass


function getmemberid2name($con,$memberid) {
    global $useenvironmentpolicy;
    //$username = $_SERVER['PHP_AUTH_USER'];

    if ($useenvironmentpolicy == 1) {
        $query = "
            SELECT memberid ,userclass,name,passwd1
            FROM foltia_envpolicy
            WHERE foltia_envpolicy.memberid  = '$memberid'
            ";
        $useraccount = m_query($con, $query, "DBクエリに失敗しました");
        $rowdata = $useraccount->fetch();
        if (! $rowdata) {
            return ("");//エラー
        }

        $name = $rowdata[2];

        $rowdata = $useraccount->fetch();
        if ($rowdata) {
            return ("");
        }

        return ($name);

    } else {
        return ("");
    }//end if

} //end function getmemberid2name



function number_page($p, $lim) {
    // Autopager・ページリンクで使用している関数
    // 下記は関数をしているファイル名
    //index.php, showplaylist.php, titlelist.php, showlib.php, showlibc.php
    ///////////////////////////////////////////////////////////////////////////
    // ページ数の計算関係
    // 第1引数 : $p       : 現在のページ数
    // 第2引数 : $lim     : 1ページあたりに表示するレコード数
    ///////////////////////////////////////////////////////////////////////////

    if($p == 0) {
        $p2 = 2;        // $p2の初期値設定
    } else {
        $p2 = $p;       // 次のページ数の値を$p2に代入する
        $p2++;
    }

    if($p < 1) {
        $p = 1;
    }
    //表示するページの値を取得
    $st = ($p - 1) * $lim;

    //
    return array($st, $p, $p2);
} //end number_page


function page_display($query_st, $p, $p2, $lim, $dtcnt, $mode) {
    // Autopager・ページリンクで使用している関数
    // 下記は関数を使用しているファイル名
    // index.php, showplaylist.php, titlelist.php, showlib.php, showlibc.php
    /////////////////////////////////////////////////////////////////////////////
    // Autopager処理とページのリンクの表示
    // 第1引数 ： $query_st     : クエリの値
    // 第2引数 ： $p            : 現在のページ数の値
    // 第3引数 ： $p2           : 次のページ数の値
    // 第4引数 ： $lim          : 1ページあたりに表示するレコード数
    // 第5引数 ： $dtcnt        : レコードの総数
    // 第6引数 ： $mode         :【新番組】mode=newのときにリンクページを表示させないフラグ(index.phpのみで使用)
    ////////////////////////////////////////////////////////////////////////////

    $page = ceil($dtcnt / $lim);
    if ($page > 1) {
        ($query_st != '') ?  $query_st = '&' . $_SERVER['QUERY_STRING'] : '';
        $query_st   = preg_replace('/p=[0-9]+&/','',$query_st); //p=0～9&を空欄にする正規表現
        $showPage = 5;
        echo '<div class="pagenation">';
        // Prev, 1ページ を表示
        if ($p != 1) {
            $prev_p = $p - 1;
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?p=' . $prev_p . $query_st . '">Prev</a>';
            if (1 < $p - $showPage) {
                echo '<a href="' . $_SERVER['PHP_SELF'] . '?p=1' . $query_st . '">1</a>';
                if (1 != $p - $showPage - 1) {
                    echo '<span>..</span>';
                }
            }
        }
        $contCnt = 0;
        $startPage = $p - $showPage;
        if ($startPage > $page - ($showPage * 2)) {
            $startPage = $page - ($showPage * 2);
        }
        for ($i = $startPage; $i <= $p + $showPage + $contCnt; $i++) {
            if ($i > $page) {
                break;
            }
            if ($i < 1) {
                $contCnt++;
                continue;
            }
            $attribute = '';
            if ($i == $p) {
                $attribute .= ' class="current"';
            } else if ($i == $p - 1) {
                $attribute .= ' rel="prev"';
            } else if ($i == $p + 1) {
                $attribute .= ' rel="next"';
            }
            echo '<a href="' . $_SERVER['PHP_SELF'] . "?p=$i" . $query_st . '"' . $attribute .'>' . $i . '</a>';
        }
        // Next, 最終ページ を表示
        if ($p != $page) {
            if ($p < $page - $showPage) {
                if ($p != $page - $showPage - 1) {
                    echo '<span>..</span>';
                }
                if (($i - 1) != $page) {
                    echo '<a href="' . $_SERVER['PHP_SELF'] . "?p=$page" . $query_st . '">' . $page . '</a>';
                }
            }
            echo '<a href="' . $_SERVER['PHP_SELF'] . "?p=$p2" . $query_st . '">Next</a>';
        }
        echo '</div>';
    }
    return array($p2, $page);
} // end page_display

function getnextstationid($con) {
    //stationidの最大値を取得して+1する。
    $query2 = "SELECT max(stationid) FROM  foltia_station";
    $rs2 = sql_query($con, $query2, "DBクエリに失敗しました");
    $rowdata2 = $rs2->fetch();
    if (! $rowdata2) {      //レコードにデータが無い時、$id =1
        $sid = 1 ;
    } else {                  //stationidの最大値を$idに入れて、+1する。
        $sid = $rowdata2[0];
        $sid ++ ;
    }
    return ($sid);
} //end getnextstationid

// 録画予約の配列、開始時間、終了時間を受け取って、予約済みかを返す。
// 0: 予約なし
// 1: 予約有り
// 2: 一部の予約有り
function searchStartEndTime($haystack, $startdatetime, $enddatetime)
{
    $ret = 0;
    foreach ($haystack as $item) {
        if ($startdatetime == $item['startdatetime'] &&
            $enddatetime   == $item['enddatetime']) {
            return 1;
        }
        if ($startdatetime <= $item['startdatetime'] &&
            $enddatetime   >= $item['enddatetime']) {
            $ret = 2;
        }
    }
    return $ret;
}

// 予約済みかをチェックする
//
function reserveCheck($con, $startfoltime, $endfoltime, $stationid)
{
    $query = "
      SELECT
       foltia_program.title,
       foltia_program.tid,
       stationname,
       foltia_station.stationid,
       foltia_subtitle.countno,
       foltia_subtitle.subtitle,
       foltia_subtitle.startdatetime,
       foltia_subtitle.enddatetime,
       foltia_subtitle.lengthmin,
       foltia_tvrecord.bitrate,
       foltia_subtitle.startoffset,
       foltia_subtitle.pid
      FROM
        foltia_subtitle,
        foltia_program,
        foltia_station,
        foltia_tvrecord
      WHERE foltia_tvrecord.tid           = foltia_program.tid
        AND foltia_tvrecord.stationid     = foltia_station .stationid
        AND foltia_program.tid            = foltia_subtitle.tid
        AND foltia_station.stationid      = foltia_subtitle.stationid
        AND foltia_subtitle.startdatetime >= ?
        AND foltia_subtitle.enddatetime   <= ?
        AND foltia_station.stationid      = ?
      UNION
      SELECT
       foltia_program.title,
       foltia_program.tid,
       stationname,
       foltia_station.stationid,
       foltia_subtitle.countno,
       foltia_subtitle.subtitle,
       foltia_subtitle.startdatetime,
       foltia_subtitle.enddatetime,
       foltia_subtitle.lengthmin,
       foltia_tvrecord.bitrate,
       foltia_subtitle.startoffset,
       foltia_subtitle.pid
      FROM foltia_tvrecord
        LEFT OUTER JOIN foltia_subtitle ON (foltia_tvrecord.tid = foltia_subtitle.tid )
        LEFT OUTER JOIN foltia_program  ON (foltia_tvrecord.tid = foltia_program.tid )
        LEFT OUTER JOIN foltia_station  ON (foltia_subtitle.stationid = foltia_station.stationid )
      WHERE foltia_tvrecord.stationid     = 0
        AND foltia_subtitle.startdatetime >= ?
        AND foltia_subtitle.enddatetime   <= ?
        AND foltia_station.stationid      = ?
      ORDER BY foltia_subtitle.startdatetime ASC ;
    ";
    $rs = sql_query($con, $query, "DBクエリに失敗しました", array($startfoltime, $endfoltime, $stationid, $startfoltime, $endfoltime, $stationid));
    $ret = $chkoverwrap = $rs->fetchAll(PDO::FETCH_ASSOC);
    return $ret;
}

// デバッグ用
function d($var) {
    echo '<pre style="text-align: left;">';
    var_dump($var);
    echo '</pre>';
} // d()

?>

