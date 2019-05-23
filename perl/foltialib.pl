
# config load

$path = $0;
$path =~ s/foltialib.pl$//i;
if ($path ne "./") {
    push( @INC, "$path");
}
require "foltia_conf1.pl";


# foltia lib
use utf8;
use DBI;
use DBD::Pg;
use DBD::SQLite;
use POSIX qw(strftime);
use File::Basename;
use LWP::UserAgent;
use JSON;
use Encode;

binmode(STDIN,  ':utf8');
binmode(STDOUT, ':utf8');
binmode(STDERR, ':utf8');

$DSN=$main::DSN;
$DBUser=$main::DBUser;
$DBPass="";


$FILESTATUSRESERVINGLONG        =  10;
$FILESTATUSRESERVINGSHORT       =  20;
$FILESTATUSRECORDING            =  30;
$FILESTATUSRECTSSPLITTING       =  40;
$FILESTATUSRECEND               =  50; # 録画完了
$FILESTATUSWAITINGCAPTURE       =  55;
$FILESTATUSCAPTURE              =  60;
$FILESTATUSCAPEND               =  70;
$FILESTATUSTHMCREATE            =  72;
$FILESTATUSWAITINGTRANSCODE     =  80;
$FILESTATUSTRANSCODETSSPLITTING =  90;
$FILESTATUSTRANSCODEFFMPEG      = 100;
$FILESTATUSTRANSCODEWAVE        = 110;
$FILESTATUSTRANSCODEAAC         = 120;
$FILESTATUSTRANSCODEMP4BOX      = 130;
$FILESTATUSTRANSCODEATOM        = 140;
$FILESTATUSTRANSCODECOMPLETE    = 150;
$FILESTATUSALLCOMPLETE          = 200;
$FILESTATUSABORT                = 300;


#------------------------------
sub slackSend {
    my ($head, $mesg) = @_;

    binmode(STDIN,  ':utf8');
    binmode(STDOUT, ':utf8');
    binmode(STDERR, ':utf8');

    if (!$slack_webhook_url) {
        return;
    }

    my $timestump = strftime("%Y/%m/%d %H:%M:%S", localtime);

    #&writelog($head . ":" . $mesg);

    # コンテンツの準備
    my $values =    {text     => "`" . $head . ': ' . $$ . "`\n" . '```' . $timestump . "\n" . $mesg . '```',
                     username => 'foltiaBot'};
    my $content = JSON::to_json($values, {utf8 => 1});

    # 組み立てたコンテンツ(=JSON)を Slack に送信
    my $user_agent = LWP::UserAgent->new();
    $user_agent->timeout(5);

    my $response = $user_agent->post(
        $slack_webhook_url,
        Content_Type => 'application/json; charset=UTF-8',
        Content => $content);
    if(!$response->is_success) {
        #print($response->status_line, "\n");
        &writelog("[ERR]" . $response->status_line);
        return;
    }

    #print($response->decoded_content, "\n");
}

sub writelog {
    my $messages  = $_[0];

    binmode(STDIN,  ':utf8');
    binmode(STDOUT, ':utf8');
    binmode(STDERR, ':utf8');

    my $timestump = strftime("%Y/%m/%d_%H:%M:%S", localtime);
    chomp($timestump);
    my ($_pkg, $_file, $_line) = caller;
    my $_processid  = sprintf("%06d", $$);

    if ($debugmode == 1) {
        open (DEBUGLOG , ">>$toolpath/debuglog.txt") || die "Cant write log file.$! \n ";
    } else {
        open (DEBUGLOG , '>-') || die "Cant write log file.$! \n ";
    }
    $messages =~ s/\n//gio;
    $_line = sprintf("%-4d", $_line);
    $_file = basename($_file);
    $_file = sprintf("%-21s", $_file);
    $messages = encode('utf-8', $messages);
    print DEBUGLOG "$timestump $_processid: $_file:$_line $messages\n";
    close (DEBUGLOG);
} #end writelog

sub syobocaldate2foltiadate {
    #20041114213000 -> 200411142130
    my $foltiadate = $_[0] ;
    $foltiadate    = substr($foltiadate,0,12);

    return  $foltiadate;
}


sub foldate2epoch {
    my $foltiadate = $_[0] ;
    # EPGをEPOCに
    # 2004 11 14 21 30
    my $eyear = substr($foltiadate , 0, 4);
    my $emon  = substr($foltiadate, 4, 2);
    $emon--;
    my $emday =  substr($foltiadate , 6, 2);
    my $q_start_time_hour = substr($foltiadate , 8, 2);
    my $q_start_time_min  = substr($foltiadate , 10, 2);

    my $epoch = timelocal(0, $q_start_time_min, $q_start_time_hour, $emday, $emon, $eyear);

    return  $epoch;
}

sub epoch2foldate {
    my $s;
    my $mi;
    my $h;
    my $d;
    my $mo;
    my $y;
    my $w;

    ($s, $mi, $h, $d, $mo, $y, $w) = localtime($_[0]);
    $mo++; $y += 1900;

    my $foltiadate;
    $mo = sprintf("%02d",$mo);
    $d  = sprintf("%02d",$d);

    $h  = sprintf("%02d",$h);
    $mi = sprintf("%02d",$mi);
    $foltiadate = "$y$mo$d$h$mi";

    return  $foltiadate;
}

sub calclength {
    #foltia開始時刻、folti終了時刻
    #戻り値:分数
    my $sttime = $_[0] ;
    my $edtime = $_[1] ;
    my $length = -1;
    $sttime = &foldate2epoch($sttime);
    $edtime = &foldate2epoch($edtime);

    if ($edtime >= $sttime) {
        $length = $edtime - $sttime;
    } else {
        $length = $sttime - $edtime;
    }
    $length = $length / 60;

    return $length ;
}

sub calcoffsetdate{
    #引き数:foltia時刻、オフセット(+/-)分
    #戻り値]foltia時刻
    my $foltime   = $_[0] ;
    my $offsetmin = $_[1] ;
    my $epoch     = &foldate2epoch($foltime );
    $epoch        = $epoch + ($offsetmin * 60 );
    $foltime      = &epoch2foldate($epoch);

    return $foltime ;
}

sub getstationid {
    #引き数:局文字列(NHK総合)
    #戻り値:1
    my $stationname =  $_[0] ;
    my $stationid ;

    my $sth;
    $sth = $dbh->prepare($stmt{'foltialib.getstationid.1'});
    $sth->execute($item{'ChName'});
    my @stationcount;
    @stationcount= $sth->fetchrow_array;

    if ($stationcount[0] == 1) {
        #チャンネルID取得
        $sth = $dbh->prepare($stmt{'foltialib.getstationid.2'});
        $sth->execute($item{'ChName'});
        @stationinfo= $sth->fetchrow_array;

        #局ID
        $stationid  = $stationinfo[0];
        #print "StationID:$stationid \n";

    } elsif($stationcount[0] == 0) {
        #新規登録
        $sth = $dbh->prepare($stmt{'foltialib.getstationid.3'});
        $sth->execute();
        @stationinfo= $sth->fetchrow_array;
        my $stationid = $stationinfo[0] ;
        $stationid++;

        ##$DBQuery =  "insert into  foltia_station values ('$stationid'  ,'$item{ChName}','0','','','','','','')";
        #新規局追加時は非受信局をデフォルトに
        $sth = $dbh->prepare($stmt{'foltialib.getstationid.4'});
        $sth->execute($stationid, $item{'ChName'}, -10);
        #print "Add station;$DBQuery\n";
        &writelog("Add station; $stmt{'foltialib.getstationid.4'}, $stationid, $item{'ChName'}, -10");

    } else {
        #print "Error  getstationid $stationcount[0] stations found. $DBQuery\n";
        &writelog("[ERR] getstationid $stationcount[0] stations found. ChName=$item{'ChName'}, stationname=$stationname");
    }

    #&writelog("[OK] getstationid $stationcount[0] stations found. ChName=$item{'ChName'}, stationname=$stationname, stationid=$stationid");
    return $stationid ;
}

sub calcatqparam {
    my $seconds = $_[0];
    my $processstarttimeepoch = "";
    $processstarttimeepoch = &foldate2epoch($startdatetime);
    $processstarttimeepoch = $processstarttimeepoch - $seconds ;
    my $sec = "";
    my $min = "";
    my $hour = "";
    my $mday = "";
    my $mon = "";
    my $year = "";
    my $wday = "";
    my $yday = "";
    my $isdst = "";
    ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($processstarttimeepoch) ;
    $year+=1900;
    $mon++;#範囲を0-11から1-12へ
    my $atdateparam = "";
    $atdateparam = sprintf ("%04d%02d%02d%02d%02d",$year,$mon,$mday,$hour,$min);

    return  $atdateparam ;
}


sub processfind {
    my @findprocess = @_;
    my @processes ;
    my $chkflag = 0;

    foreach my $find (@findprocess) {
        #@processes = `ps h -C "$find"`;
        @processes = `ps -ef | grep "$find" | grep -Ev 'grep|vi|sudo|ffmpeg.*http'`;

        foreach my $psc (@processes) {
            if ($psc =~ /$find/i) {
                #print "process found: $psc\n";
                $chkflag++;
            }
        }
    }
    return ($chkflag);
} #endsub

sub get_load_average {
    open my $fh, '/proc/loadavg';
    my ($LA1) = split /\s+/, <$fh>;
    close $fh;
    return $LA1;
}

sub filenameinjectioncheck {
    my $filename = $_[0];
    $filename =~ s/\///gi;
    $filename =~ s/\;//gi;
    $filename =~ s/\&//gi;
    $filename  =~ s/\|//gi;

    return ($filename );
}


sub getphpstyleconfig {
    my $key           = $_[0];
    my $phpconfigpath = "";
    my $configline    = "";
    # read
    if (-e "$phptoolpath/php/foltia_config2.php") {
        $phpconfigpath = "$phptoolpath/php/foltia_config2.php";
    }elsif(-e "$toolpath/php/foltia_config2.php") {
        $phpconfigpath = "$toolpath/php/foltia_config2.php";
    }else{
        $phpconfigpath = `locate foltia_config2.php | head -1`;
        chomp($phpconfigpath);
    }


    if (-r $phpconfigpath ) {
        open (CONFIG ,"$phpconfigpath") || die "File canot read.$!";
        while(<CONFIG>) {
            if (/$key/) {
            $configline = $_;
            $configline =~ s/\/\/.*$//;
            $configline =~ s/\/\*.*\*\///;
            } else {
            }
        }
        close(CONFIG);
    } #end if -r $phpconfigpath
    return ($configline);
} #end sub getphpstyleconfig


sub getpidbympegfilename {
    #引き数:m2pfilename
    #戻り値:PID
    my $m2pfilename =  $_[0] ;
    if ($m2pfilename eq "") {
        return  0 ;
    }

    my $sth;
    $sth = $dbh->prepare($stmt{'foltialib.getpidbympegfilename.1'});
    $sth->execute($m2pfilename);
    #print "$stmt{'foltialib.getpidbympegfilename.1'}\n";
    my @pidinfo = $sth->fetchrow_array;
    my $pid  = $pidinfo[0];

    if ($pid eq "") {
        return  0 ;
    } else {
        return $pid;
    }
} #end sub getpidbympegfilename

sub changefilestatus {
    # 引き数:PID,updatestatus
    # 戻り値:エラーコード
    my $pid          = $_[0] ;
    my $updatestatus = $_[1];
    if (($pid eq "" ) || ($updatestatus eq "")) {
        return  0 ;
    }

    if ($updatestatus > 0 ) {
        my $sth;
        $sth = $dbh->prepare($stmt{'foltialib.changefilestatus.1'});
        $sth->execute($updatestatus, $pid);
        return 1;
    } else {
        &writelog("changefilestatus ERR Sttus invalid: $updatestatus");
        return  0 ;
    }
} # end sub changefilestatus


sub getfilestatus {
    #引き数:PID
    #戻り値:ステータス

    #10:予約中(5分以上先)
    #20:予約中(5分以内)
    #30:録画中
    #40:TSSplit中
    #50:MPEG2録画終了
    #55 静止画キャプチャ待
    #60:静止画キャプ中
    #70:静止画キャプ終了
    #72:サムネイル作成済み(.THM)
    #80:トラコン待
    #90:トラコン中:TSsplit
    #100:トラコン中:H264
    #110:トラコン中:WAVE
    #120:トラコン中:AAC
    #130:トラコン中:MP4Box
    #140:トラコン中:ATOM
    #150:トラコン完了
    #200:全完了
    my $pid =  $_[0] ;
    if ($pid eq "" ) {
        return  0 ;
    }

    my $sth;
    $sth = $dbh->prepare($stmt{'foltialib.getfilestatus.1'});
    $sth->execute($pid);

    my @statusinfo = $sth->fetchrow_array;
    my $status  = $statusinfo[0];

    if ($status eq "") {
        return  0 ;
    } else {
        return $status;
    }


} # end sub getfilestatus


sub makemp4dir {
    # TIDが100以上の3桁の場合はそのまま
    my $pspfilnamehd = $_[0];
    my $tid          = $_[0];
    my $pspdirname   = "$tid.localized/";
    $pspdirname      = $recfolderpath."/".$pspdirname;

    if (-e $pspdirname ) {
        # あったらtouch
        &writelog("touch $pspdirname");
        system("touch $pspdirname");
    } else {
        # なければ作る
        &writelog("mkdir $pspdirname");
        system("$toolpath/perl/mklocalizeddir.pl $tid");
    }

    $pspdirname = "$tid.localized/mp4/";
    $pspdirname = $recfolderpath."/".$pspdirname;
    # なければ作る
    unless (-e $pspdirname ) {
        &writelog("mkdir $pspdirname");
        mkdir $pspdirname, 0777;
    }

    return ("$pspdirname");
} #endsub makemp4dir

sub pid2sid {
    #番組IDからStation IDを取得
    my $pid = $_[0];
    my $sth;
    $sth = $dbh->prepare($stmt{'foltialib.pid2sid.1'});
    $sth->execute($pid);
    my @statusinfo = $sth->fetchrow_array;
    my $sid  = $statusinfo[0];

    if ($sid eq "") {
        return  0 ;
    } else {
        return $sid;
    }

} #end sub pid2sid


sub mp4filename2tid {
    #MPEG4ファイル名からTIDを得る
    my $mp4filename = $_[0];
    my $sth;
    $sth = $dbh->prepare($stmt{'foltialib.mp4filename2tid.1'});
    $sth->execute($mp4filename);
    my @statusinfo = $sth->fetchrow_array;
    my $tid  = $statusinfo[0];

    if ($tid eq "") {
        return  0 ;
    } else {
        return $tid;
    }
} #end sub mp4filename2tid

sub movie_sec {
    my $in_file = $_[0];
    my $ffmpeg = '/usr/local/bin/ffmpeg';

    my $time_s = `$toolpath/perl/tool/ffmpeg -i $in_file 2>&1 | grep 'Duration:' | sed 's/  Duration: //g;s/,.*//;s/\\.[0-9]*//'`;
    my @split = split(/:/, $time_s);
    my $h = $split[0] * 3600;
    my $m = $split[1] * 60;
    my $s = $split[2];
    my $sec = $h + $m + $s;

    return $sec;
}


1;

