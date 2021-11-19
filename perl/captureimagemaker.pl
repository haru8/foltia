#!/usr/bin/perl
# usage captureimagemaker.pl  MPEG2filename
#
# Anime recording system foltia
# http://www.dcc-jpl.com/soft/foltia/
#
#
# キャプチャ画像作成モジュール
# recwrap.plから呼び出される。
#
# DCC-JPL Japan/foltia project
#

use utf8;

$path = $0;
$path =~ s/captureimagemaker.pl$//i;
if ($path ne "./") {
    push( @INC, "$path");
}

require "foltialib.pl";

#$tid = $ARGV[0] ;
$filename = $ARGV[0] ;

# filenameの妥当性をチェック
@filenametmp = split(/\./,$filename);
@filename    = split(/-/,$filenametmp[0]);
$tid         = $filename[0];

# tidが数字のみかチェック
$tid =~ s/[^0-9]//ig;
#print "$tid\n";

if ($tid eq "" ) {
    # 引き数なし出実行されたら、終了
    print "usage captureimagemaker.pl  MPEG2filename\n";
    exit;
}

if ($tid >= 0) {
    #print "TID is valid\n";
} else {
    &writelog("TID invalid: $tid");
    exit;
}

$countno = $filename[1];
$countno =~ s/[^0-9]//ig;
#if ($countno eq "" ){
#$countno = "x";
#}
#   print "CNTNO:$countno\n";

$date = $filename[2];
$date =~ s/[^0-9]//ig;
if ($date eq "" ) {
    $date = strftime("%Y%m%d", localtime);
}
#print "DATE:$date\n";


$time = $filename[3];
$time = substr($time, 0, 4);
$time =~ s/[^0-9]//ig;
if ($time eq "" ) {
    $time = strftime("%H%M", localtime);
}
#print "TIME:$time\n";

# ファイルが有るかチェック
if (-e "$recfolderpath/$filename") {
    #print "EXIST $recfolderpath/$filename\n";
} else {
    #print "NO $recfolderpath/$filename\n";
    &writelog("notexist $recfolderpath/$filename");
    exit;
}

# 展開先ディレクトリがあるか確認
$capimgdirname = "$tid.localized/";
$capimgdirname = $recfolderpath."/".$capimgdirname;

# なければ作る
unless (-e $capimgdirname ) {
    system("$toolpath/perl/mklocalizeddir.pl $tid");
    &writelog("mkdir $capimgdirname");
}

$capimgdirname = "$tid.localized/img";
$capimgdirname = $recfolderpath."/".$capimgdirname;

# なければ作る
unless (-e $capimgdirname ) {
    mkdir $capimgdirname ,0777;
    &writelog("mkdir $capimgdirname");
}

# キャプチャ入れるディレクトリ作成
# $captureimgdir = "$tid"."-"."$countno"."-"."$date"."-"."$time";
$captureimgdir = $filename;
$captureimgdir =~ s/\.m2p$|\.m2t$//;
$captureimgdir =~ s/_HD$|_SD[123]$|_tss$//;

unless (-e "$capimgdirname/$captureimgdir") {
    mkdir "$capimgdirname/$captureimgdir" ,0777;
    mkdir "$capimgdirname/$captureimgdir/l" ,0777;
    &writelog("mkdir $capimgdirname/$captureimgdir");
}

# 変換
#system ("mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf crop=702:468:6:6,scale=160:120,pp=lb -ao null -sstep 14 -v 3 $recfolderpath/$filename");
#system ("mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf crop=702:468:6:6,scale=160:120 -ao null -sstep 14 -v 3 $recfolderpath/$filename");


# とか黒線入るから左右、もうすこしづつ切ろう。
#system ("mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf crop=690:460:12:10,scale=160:120 -ao null -sstep 14 -v 3 $recfolderpath/$filename");

$step    = 10;
$thumb_s = "288x162";
$thumb_l = "1280x720";
$playsec = &movie_sec("$recfolderpath/$filename");
$imgnums = $playsec / $step;
&writelog("FILE = $recfolderpath/$filename  sec=$playsec  imgnums=$imgnums");
# 秒ごとに
if(-e "$capimgdirname/$captureimgdir/00000005.jpg" ) {
    &writelog("Already created. $capimgdirname/$captureimgdir/");
} else {
    if ($filename =~ /m2t$/) {
        #&writelog("DEBUG mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf scale=384:216 -ao null -sstep 9  $recfolderpath/$filename");
        #system ("mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf scale=384:216 -ao null -sstep 9  $recfolderpath/$filename");

        $num = 0;
        $sec = 0;
        $retval = 0;
        while ($retval == 0) {
            $num_s = sprintf('%08d', $num);
            $time  = sprintf("%02d:%02d:%02d", int($sec/3600), int($sec/60), $sec%60);
            system ("$toolpath/perl/tool/ffmpeg -loglevel quiet -ss $sec -i $recfolderpath/$filename -vframes 1 -s $thumb_s -f image2 ${capimgdirname}/${captureimgdir}/${num_s}.jpg");
            system ("$toolpath/perl/tool/ffmpeg -loglevel quiet -ss $sec -i $recfolderpath/$filename -vframes 1 -s $thumb_l -f image2 ${capimgdirname}/${captureimgdir}/l/${num_s}.jpg");
            $retval  = $? >> 8;
            $signal_num  = $? & 127;
            $dumped_core = $? & 128;

            #if (! -e "${capimgdirname}/${captureimgdir}/${num_s}.jpg") {
            #   &writelog("/usr/local/bin/ffmpeg -loglevel quiet -ss $sec -i $recfolderpath/$filename -vframes 1 -s 384x216 -f image2 ${capimgdirname}/${captureimgdir}/${num_s}.jpg");
            #   &writelog("${capimgdirname}/${captureimgdir}/${num_s}.jpg not found.\n");
            #   last;
            #}
            if ($num > $imgnums) {
                &writelog("$toolpath/perl/tool/ffmpeg -loglevel quiet -ss $sec -i $recfolderpath/$filename -vframes 1 -s $thumb_s -f image2 ${capimgdirname}/${captureimgdir}/${num_s}.jpg");
                &writelog("${capimgdirname}/${captureimgdir}/${num_s}.jpg Create Success. imgnums=$imgnums .\n");
                last;
            }
            $num = $num + 1;
            $sec = $sec + $step;
        }

        if(-e "$capimgdirname/$captureimgdir/00000005.jpg" ) {
            # $capimgdirname/$captureimgdir/ があったらなにもしない

        } else {
            #空っぽなら再試行
            &writelog("DEBUG RETRY mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf framestep=300step,scale=384:216 -ao null $recfolderpath/$filename");
            system ("$toolpath/perl/tool/mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf framestep=300step,scale=384:216 -ao null $recfolderpath/$filename 2> /dev/null");
        }

    } else {
        &writelog("DEBUG mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf crop=690:460:12:10,scale=160:120 -ao null -sstep 9 -v 3 $recfolderpath/$filename");
        system ("$toolpath/perl/tool/mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf crop=690:460:12:10,scale=160:120 -ao null -sstep 9 $recfolderpath/$filename 2> /dev/null");
        if(-e "$capimgdirname/$captureimgdir/00000003.jpg" ) { #$capimgdirname/

        } else {
            system ("$toolpath/perl/tool/mplayer -ss 00:00:10 -vo jpeg:outdir=$capimgdirname/$captureimgdir/ -vf framestep=300step,crop=690:460:12:10,scale=160:120 -ao null $recfolderpath/$filename 2> /dev/null");
        }
    }
}

