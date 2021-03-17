#!/usr/bin/perl
#usage ipodtranscode.pl
#
# Anime recording system foltia
# http://www.dcc-jpl.com/soft/foltia/
#
# iPod MPEG4/H.264トラコン
# ffmpegを呼び出して変換
#
# DCC-JPL Japan/foltia project
#

use utf8;
use DBI;
use DBD::Pg;
use DBD::SQLite;
use Jcode;
use File::Basename;
use Time::Local;

$path = $0;
$path =~ s/ipodtranscode.pl$//i;
if ($path ne "./") {
    push( @INC, "$path");
}
require "foltialib.pl";

&writelog("starting up.");

#DB初期化
$dbh = DBI->connect($DSN, $DBUser, $DBPass) || die $DBI::error;;
$dbh->{sqlite_unicode} = 1;

$counttranscodefiles = &counttranscodefiles();
if ($counttranscodefiles == 0) {
    &writelog("No MPEG2 files to transcode.");
    exit;
}
sleep 30;

while ($counttranscodefiles >= 1) {

    # 空き容量チェック
    $disk_free_gb = &disk_free_size();
    &writelog("DISK FREE = $disk_free_gb GB.");
    if (100 > $disk_free_gb) {
        &writelog("FREE SPACE IS BELOW THE THRESHOLD.");
        $tsrm = &tsrm();
        &writelog($tsrm);
    }

    # Load Average をチェック
    my $la;
    $la = &get_load_average();
    if ($la > 10) {
        &writelog("Load Average over threshold! exit: $la");
        exit;
    } else {
        &writelog("Load Average OK.: $la");
    }

    # 二重起動の確認!
    $processes = &processfind("ipodtranscode.pl");
    #$processes = &processfind("ipodtranscode.pl", "ffmpeg");
    if ($processes > 1 ) {
        &writelog("processes exist. exit: $processes");
        exit;
    } else {
        &writelog("Normal launch.: $processes");
        #slackSend("Normal launch.");
    }

    # トラコンフラグがたっていてステータス50以上150未満のファイルを古い順にひとつ探す
    $sth = $dbh->prepare($stmt{'ipodtranscode.1'});
    $sth->execute($FILESTATUSRECEND, $FILESTATUSTRANSCODECOMPLETE);
    @dbparam = $sth->fetchrow_array;
    &writelog("DEBUG $stmt{'ipodtranscode.1'}");
    &writelog("DEBUG pid=$dbparam[0], tid=$dbparam[1], m2pfilename=$dbparam[2], filestatus=$dbparam[3], aspect=$dbparam[4], countno=$dbparam[5], title=$dbparam[6], subtitle=$dbparam[7], startdatetime=$dbparam[8], enddatetime=$dbparam[9], lengthmin=$dbparam[10]");
    $pid               = $dbparam[0];
    $tid               = $dbparam[1];
    $inputmpeg2        = $recfolderpath."/".$dbparam[2]; # path付き
    $mpeg2filename     = $dbparam[2]; # pathなし
    $filestatus        = $dbparam[3];
    $aspect            = $dbparam[4];# 16,1 (超額縁),4,3
    $countno           = $dbparam[5];
    $title             = $dbparam[6];
    $subtitle          = $dbparam[7];
    $startdatetime     = $dbparam[8];
    $enddatetime       = $dbparam[9];
    $lengthmin         = $dbparam[10];
    $mp4filenamestring = &mp4filenamestringbuild($pid);

    $head  = "ts->mp4 エンコード開始";
    $mesg  = sprintf("pid           : %s\n", $pid);
    $mesg .= sprintf("tid           : %s\n", $tid);
    $mesg .= sprintf("タイトル      : %s\n", $title);
    $mesg .= sprintf("サブタイトル  : %s\n", $subtitle);
    $mesg .= sprintf("話数          : %s\n", $countno);
    $mesg .= sprintf("放送開始日時  : %s\n", $startdatetime);
    $mesg .= sprintf("放送終了日時  : %s\n", $enddatetime);
    $mesg .= sprintf("尺(分)        : %s\n", $lengthmin);
    $mesg .= sprintf("TSファイル名  : %s\n", $mpeg2filename);
    slackSend($head, $mesg);

    print("\nTS File Name : $mpeg2filename\n\n");

    $mpeg2_tm_start = time();

    if (-e $inputmpeg2) {
        # MPEG2ファイルが存在していれば

        &writelog("DEBUG mp4filenamestring $mp4filenamestring");
        # 展開ディレクトリ作成
        $pspdirname = &makemp4dir($tid);
        $mp4outdir  = $pspdirname ;

        # 実際のトラコン
        # タイトル取得
        if ($pid ne "") {
            $sth = $dbh->prepare($stmt{'ipodtranscode.2'});
            $sth->execute($pid);
            @programtitle    = $sth->fetchrow_array;
            $programtitle[0] =~ s/\"/\\"/gi;
            $programtitle[2] =~ s/\"/\\"/gi;

            if ($pid > 0) {
                if ($programtitle[1] ne "") {
                    $movietitle    = " -title \"$programtitle[0] 第$programtitle[1]話 $programtitle[2]\" ";
                    $movietitleeuc = " -t \"$programtitle[0] 第$programtitle[1]話 $programtitle[2]\" ";
                } else {
                    $movietitle    = " -title \"$programtitle[0] $programtitle[2]\" ";
                    $movietitleeuc = " -t \"$programtitle[0] $programtitle[2]\" ";
                }
            } elsif($pid < 0) {
                # EPG
                $movietitle    = " -title \"$programtitle[2]\" ";
                $movietitleeuc = " -t \"$programtitle[2]\" ";
            } else {# 0
                # 空白
                $movietitle    = "";
                $movietitleeuc = "";
            }
            #Jcode::convert(\$movietitle,'utf8');# Title入れるとiTunes7.0.2がクラッシュする
            $movietitle    = "";
            $movietitleeuc = "";

        }

        if ($filestatus <= $FILESTATUSRECEND) {
        }

        if ($filestatus <= $FILESTATUSWAITINGCAPTURE) {
            # なにもしない
        }

        if ($filestatus <= $FILESTATUSCAPTURE) {
            # unlink
            # Starlight breaker向けキャプチャ画像作成
            if (-e "$toolpath/perl/captureimagemaker.pl") {
                # &writelog("Call captureimagemaker $mpeg2filename");
                # &changefilestatus($pid, $FILESTATUSCAPTURE);
                # system ("$toolpath/perl/captureimagemaker.pl $mpeg2filename");
                &changefilestatus($pid, $FILESTATUSCAPEND);
            }
        }

        if ($filestatus <= $FILESTATUSCAPEND) {
            # サムネイル作る
            &makethumbnail();
            &changefilestatus($pid, $FILESTATUSTHMCREATE);
        }

        if ($filestatus <= $FILESTATUSWAITINGTRANSCODE) {
        }

        $filenamebody = $inputmpeg2 ;
        $filenamebody =~ s/.m2t$|.ts$|.m2p$|.mpg$|.aac$//gi;

        # デジタル
        if ($inputmpeg2 =~ /m2t$|ts$|aac$/i) {

            if ($filestatus <= $FILESTATUSTRANSCODETSSPLITTING) {
                unlink("${filenamebody}_tss.m2t");
                unlink("${filenamebody}_HD.m2t");
                unlink("${filenamebody}.base.mp4");
            }

            if ($filestatus <= $FILESTATUSTRANSCODEFFMPEG) {
                unlink("$filenamebody.264");

                # H.264出力
                $trcnmpegfile = $inputmpeg2 ;

                # アスペクト比
                if ($aspect == 1) {
                    # 超額縁
                    $cropopt = " -vf crop=in_w-400:in_h-300:200:150 ";
                } elsif($aspect == 4) {
                    # SD
                    $cropopt = " -vf crop=in_w-16:in_h-12:8:6 ";
                } else {
                    # 16:9
                    $cropopt = " -vf crop=in_w-16:in_h-12:8:6 ";
                }
                $cropopt = "";

                # クオリティごとに
                $threads    = "-threads 0";
                $resolution = "-s 640x360";
                $aspect     = "-aspect 16:9";
                $framerate  = "-r 30000/1001";
                $maxrate    = "-bufsize 1152K -maxrate 1152K";
                $refs       = "-refs 13";
                $preset     = "-preset veryslow";
                $tune       = "-tune animation";
                $crf        = "-crf 22";
                $qcomp      = ""; # -qcomp 0.7
                $x264opts   = "-x264opts merange=32:no-dct-decimate";
                $vf         = "-vf yadif";
                $aacopt     = "-ac 2 -ar 48000 -vbr 4";
                #$sync       = "-async 200";
                $sync       = "-vsync 1";
                $bench      = "-ssim 1 -benchmark";
                if (($trconqty eq "") || ($trconqty == 1)) {
                    $resolution = "-s 360x202";
                    $refs       = "-refs 3";
                    $crf        = "-crf 25";

                } elsif($trconqty == 2) {
                    $resolution = "-s 480x272";
                    $refs       = "-refs 3";
                    $crf        = "-crf 25";

                } elsif($trconqty == 3) {
                    $resolution = "-s 640x360";
                    $crf        = "-crf 22";
                }

                if ($tid != 0) {
                    $tune       = "-tune animation";
                    $crf        = "-crf 23";

                } else {
                    $maxrate    = "-bufsize 1024K -maxrate 1024K";
                    $tune       = "-tune film";
                    $crf        = "-crf 26";
                }
                $ffmpegencopt = "$threads $resolution $aspect $framerate -f mp4 -vcodec libx264 $preset $crf $maxrate $refs $tune $qcomp $x264opts $vf -acodec libfdk_aac $aacopt $sync $bench ${filenamebody}.base.mp4";

                $ts_splitter = "";

                # 不安定なので頭2秒は捨てる
                #$sstime = " -ss 00:00:02.000 ";
                $sstime = "";

                &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
                #   &writelog("ffmpeg $filenamebody.264");
                #   system ("nice -n 15 /usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $ffmpegencopt");
                # まずTsSplitする →ワンセグをソースにしてしまわないように
                if (! -e "${filenamebody}.base.mp4") {
                    $sp_start = time();

                    &changefilestatus($pid, $FILESTATUSTRANSCODETSSPLITTING);
                    unlink("${filenamebody}_tss.m2t");
                    unlink("${filenamebody}_HD.m2t");
                    #if (-e "$toolpath/perl/tool/tss.py") {
                    #    $ts_splitter = "tss.py";

                    #    &writelog("$toolpath/perl/tool/tss.py $inputmpeg2  :start.");
                    #    $tm_start = time();
                    #    system("nice -n 15 $toolpath/perl/tool/tss.py $inputmpeg2");
                    #    $exit_value  = $? >> 8;
                    #    $signal_num  = $? & 127;
                    #    $dumped_core = $? & 128;
                    #    &writelog("tss.py $inputmpeg2 :$exit_value:$signal_num:$dumped_core:end.");
                    #    $tm_end   = time();
                    #    $tm_process = $tm_end - $tm_start;
                    #    $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                    #    &writelog("tss.py PROCESS TIME : $tm_process sec, $ftm_process");

                    #} else {
                    #    # TsSplit
                    #    #&writelog("TsSplitter $inputmpeg2");
                    #    #system("wine $toolpath/perl/tool/TsSplitter.exe  -EIT -ECM  -EMM -SD -1SEG -WAIT2 $inputmpeg2");
                    #}

                    #if(-e "${filenamebody}_tss.m2t") {
                    #    $trcnmpegfile = "${filenamebody}_tss.m2t";
                    #    &writelog("trcnmpegfile = $trcnmpegfile");
                    #} elsif (-e "${filenamebody}_HD.m2t") {
                    #    $trcnmpegfile = "${filenamebody}_HD.m2t";
                    #    &writelog("trcnmpegfile = $trcnmpegfile");
                    #} else {
                    #    &writelog("ERR NOT Exist ${filenamebody}_tss.m2t or ${filenamebody}_HD.m2t");
                    #}

                    $trcnmpegfile = $inputmpeg2 ;
                    # Splitファイルの確認
                    $trcnmpegfile = &validationsplitfile($inputmpeg2, $trcnmpegfile);
                    &writelog("trcnmpegfile = $trcnmpegfile");

                    # tss.py に失敗してたなら強制的にWINEでTsSplit.exe
                    if($trcnmpegfile eq $inputmpeg2) {
                        $ts_splitter = "TsSplitter.exe";

                        # TsSplit
                        &writelog("WINE TsSplitter.exe -EIT -ECM  -EMM -SD -1SEG $inputmpeg2  :start.");
                        $tm_start = time();
                        system("nice -n 15 wine $toolpath/perl/tool/TsSplitter.exe -EIT -ECM  -EMM -SD -1SEG $inputmpeg2");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("TsSplitter.exe  :$exit_value:$signal_num:$dumped_core:end.");
                        $tm_end   = time();
                        $tm_process  = $tm_end - $tm_start;
                        $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                        &writelog("TsSplitter.exe PROCESS TIME : $tm_process sec, $ftm_process");

                        if (-e "${filenamebody}_HD.m2t") {
                            $trcnmpegfile = "${filenamebody}_HD.m2t";
                            &writelog("trcnmpegfile = $trcnmpegfile");

                            # ストリームの最初からのはずなので捨てない。
                            #$sstime = " -ss 00:00:02.000 ";
                            $sstime = "";

                            # Splitファイルの確認
                            $trcnmpegfile = &validationsplitfile($inputmpeg2, $trcnmpegfile);
                            &writelog("trcnmpegfile = $trcnmpegfile");

                            #if($trcnmpegfile ne $inputmpeg2) {
                            #   &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
                            #   &writelog("ffmpeg retry ; WINE TsSplitter.exe $trcnmpegfile");
                            #   system ("/usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $ffmpegencopt");
                            #} else {
                            #   &writelog("WINE TsSplit.exe fail");
                            #}
                        } else {
                            &writelog("WINE TsSplitter.exe; Not exist ${filenamebody}_HD.m2t");
                        } # endif -e ${filenamebody}_HD.m2t

                    } # endif $trcnmpegfile eq $inputmpeg2

                    # TsSplitter.exeでも失敗してたならSDファイルを抽出してみる。
                    if($trcnmpegfile eq $inputmpeg2) {
                        # TsSplit
                        &writelog("WINE TsSplitter.exe -EIT -ECM -EMM -1SEG $inputmpeg2  :start.");
                        $tm_start = time();
                        system("nice -n 15 wine $toolpath/perl/tool/TsSplitter.exe -EIT -ECM  -EMM -1SEG $inputmpeg2");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("TsSplitter.exe  :$exit_value:$signal_num:$dumped_core:end.");
                        $tm_end   = time();
                        $tm_process  = $tm_end - $tm_start;
                        $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                        &writelog("SD TsSplitter.exe PROCESS TIME : $tm_process sec, $ftm_process");

                        if (-e "${filenamebody}_SD1.m2t" || -e "${filenamebody}_SD2.m2t" || -e "${filenamebody}_SD3.m2t") {
                            # Splitファイルの確認
                            $trcnmpegfile = &validationsplitfilesd($inputmpeg2, "${filenamebody}_SD1.m2t", "${filenamebody}_SD2.m2t", "${filenamebody}_SD3.m2t");
                            &writelog("trcnmpegfile = $trcnmpegfile");

                            # ストリームの最初からのはずなので捨てない。
                            #$sstime = " -ss 00:00:02.000 ";
                            $sstime = "";

                        }
                    }

                    $sp_end     = time();
                    $cp_start   = time();

                    # Starlight breaker向けキャプチャ画像作成
                    # Split していないファイルを渡すとffmpegが戻ってこない事があるので。
                    if($trcnmpegfile ne $inputmpeg2) {
                        if (-e "$toolpath/perl/captureimagemaker.pl") {
                            $trcnmpegfilename = basename($trcnmpegfile);
                            &writelog("Call captureimagemaker(TsSplit) $trcnmpegfilename");
                            $tm_start = time();
                            system ("$toolpath/perl/captureimagemaker.pl $trcnmpegfilename");
                            $tm_end   = time();
                            $tm_process  = $tm_end - $tm_start;
                            $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                            &writelog("captureimagemaker.pl PROCESS TIME : $tm_process sec, $ftm_process");
                        }
                    } else {
                        &writelog("trcnmpegfile = $trcnmpegfile  inputmpeg2 = $inputmpeg2");
                        &writelog("captureimagemaker.pl skip.");
                    }

                    $cp_end     = time();
                    $enc_start  = time();

                    # ffmpeg エンコード
                    $encsrcfile = $trcnmpegfile;
                    &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
                    &writelog("ffmpeg ${filenamebody}.base.mp4  :start.");
                    &writelog("CMD: $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $cropopt $sstime $ffmpegencopt");
                    $tm_start = time();
                    system ("nice -n 15 $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $cropopt $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg ts => mp4.  $encsrcfile :$exit_value:$signal_num:$dumped_core:end.");
                    $tm_end   = time();
                    $tm_process  = $tm_end - $tm_start;
                    $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                    &writelog("ffmpeg PROCESS TIME : $tm_process sec, $ftm_process");
                }

                # もしエラーになったらcropやめる
                if (! -e "${filenamebody}.base.mp4") {
                    # 再ffmpeg
                    $encsrcfile = $trcnmpegfile;
                    &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
                    &writelog("ffmpeg retry no crop ${filenamebody}.base.mp4  :start.");
                    &writelog("CMD: $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $sstime $ffmpegencopt");
                    $tm_start = time();
                    system ("nice -n 15 $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg retry no crop.  $encsrcfile :$exit_value:$signal_num:$dumped_core:end.");
                    $tm_end   = time();
                    $tm_process  = $tm_end - $tm_start;
                    $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                    &writelog("ffmpeg PROCESS TIME : $tm_process sec, $ftm_process");
                }

                # 強制的にWINEでTsSplit.exe
                if (! -e "${filenamebody}.base.mp4") {
                }

                # それでもエラーならsplitしてないファイルをターゲットに
                if (! -e "${filenamebody}.base.mp4") {
                    # 不安定なので頭2秒は捨てる
                    #$sstime = " -ss 00:00:02.000 ";
                    $sstime = "";

                    # # Starlight breaker向けキャプチャ画像作成
                    # if (-e "$toolpath/perl/captureimagemaker.pl") {
                    #   $trcnmpegfilename = basename($inputmpeg2);
                    #   &writelog("Call captureimagemaker(TsSplit) $trcnmpegfilename");
                    #   $tm_start = time();
                    #   system ("$toolpath/perl/captureimagemaker.pl $trcnmpegfilename");
                    #   $tm_end   = time();
                    #   $tm_process  = $tm_end - $tm_start;
                    #   $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                    #   &writelog("captureimagemaker.pl PROCESS TIME : $tm_process sec, $ftm_process");
                    # }

                    # 再々ffmpeg
                    $encsrcfile = $inputmpeg2;
                    &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
                    &writelog("ffmpeg retry No splited originalTS file ${filenamebody}.base.mp4  :start.");
                    &writelog("CMD: $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $sstime $ffmpegencopt");
                    $tm_start = time();
                    system ("nice -n 15 $toolpath/perl/tool/ffmpeg -y -i $encsrcfile $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg retry No splited.  $encsrcfile :$exit_value:$signal_num:$dumped_core:end.");
                    $tm_end   = time();
                    $tm_process  = $tm_end - $tm_start;
                    $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                    &writelog("ffmpeg PROCESS TIME : $tm_process sec, $ftm_process");
                }
                $enc264size = -s "${filenamebody}.base.mp4";
                $enc264size = $enc264size / 1024 / 1024;
                &writelog("ffmpeg encode mp4 file : ${filenamebody}.base.mp4 size: $enc264size MB");
            }

            $enc_end    = time();
            $wav_start  = time();

            #if ($filestatus <= $FILESTATUSTRANSCODEWAVE) {
            #    # WAV出力
            #    unlink("${filenamebody}.wav");
            #    &changefilestatus($pid, $FILESTATUSTRANSCODEWAVE);

            #    # mplayer
            #    #&writelog("mplayer $filenamebody.wav");
            #    #&writelog("CMD: mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast  :start.");
            #    #system ("mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast");
            #    #&writelog("CMD: mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast  :end.");

            #    # ffmpeg aac
            #    #&writelog("ffmpeg aac  $trcnmpegfile :start.");
            #    #&writelog("CMD: ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec copy $filenamebody.aac");
            #    #system ("ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec copy $filenamebody.aac");

            #    # ffmpeg aac -> wav
            #    &writelog("ffmpeg TS -> wav  $trcnmpegfile :start.");
            #    &writelog("CMD: $toolpath/perl/tool/ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec pcm_s16le -ac 2 $filenamebody.wav");
            #    $tm_start = time();
            #    system ("$toolpath/perl/tool/ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec pcm_s16le -ac 2 $filenamebody.wav");

            #    $exit_value  = $? >> 8;
            #    $signal_num  = $? & 127;
            #    $dumped_core = $? & 128;
            #    &writelog("ffmpeg TS -> wav.  $trcnmpegfile :$exit_value:$signal_num:$dumped_core:end.");
            #    $tm_end   = time();
            #    $tm_process  = $tm_end - $tm_start;
            #    $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
            #    &writelog("ffmpeg TS -> wav. PROCESS TIME : $tm_process sec, $ftm_process");
            #}
            &changefilestatus($pid, $FILESTATUSTRANSCODEWAVE);

            $wav_end    = time();
            $aac_start  = time();

            #if ($filestatus <= $FILESTATUSTRANSCODEAAC) {
            #    # AAC変換
            #    unlink("${filenamebody}.aac");
            #    &changefilestatus($pid, $FILESTATUSTRANSCODEAAC);
            #    if (-e "$toolpath/perl/tool/neroAacEnc") {
            #        if (-e "$filenamebody.wav") {
            #            &writelog("neroAacEnc $filenamebody.wav");
            #            #&writelog("CMD: $toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac  :start.");
            #            &writelog("CMD: $toolpath/perl/tool/neroAacEnc -q 0.4 -hev2 -if $filenamebody.wav  -of $filenamebody.aac  :start.");
            #            $tm_start = time();
            #            #system ("$toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac");
            #            system ("nice -n 15 $toolpath/perl/tool/neroAacEnc -q 0.4 -hev2 -if $filenamebody.wav  -of $filenamebody.aac");
            #            #&writelog("CMD: $toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac  :end.");
            #            &writelog("CMD: $toolpath/perl/tool/neroAacEnc -q 0.4 -hev2 -if $filenamebody.wav  -of $filenamebody.aac  :end.");
            #            $tm_end   = time();
            #            $tm_process  = $tm_end - $tm_start;
            #            $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
            #            &writelog("neroAacEnc PROCESS TIME : $tm_process sec, $ftm_process");

            #        } else {
            #            &writelog("ERR Not Found $filenamebody.wav");
            #        }
            #    }
            #    if (!-e "$filenamebody.aac") {
            #        #print "DEBUG $to
            #        &writelog("faac $filenamebody.wav");
            #        &writelog("CMD: $toolpath/perl/tool/faac -b 128  -o $filenamebody.aac $filenamebody.wav  :start.");
            #        $tm_start = time();
            #        system ("$toolpath/perl/tool/faac -b 128  -o $filenamebody.aac $filenamebody.wav ");
            #        &writelog("CMD: $toolpath/perl/tool/faac -b 128  -o $filenamebody.aac $filenamebody.wav  :end.");
            #        $tm_end   = time();
            #        $tm_process  = $tm_end - $tm_start;
            #        $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
            #        &writelog("faac PROCESS TIME : $tm_process sec, $ftm_process");
            #    }
            #    $encaacsize = -s "$filenamebody.aac";
            #    $encaacsize = $encaacsize / 1024 / 1024;
            #    &writelog("encode aac file size: $encaacsize MB");
            #}
            &changefilestatus($pid, $FILESTATUSTRANSCODEAAC);

            $aac_end    = time();

            if ($filestatus <= $FILESTATUSTRANSCODEMP4BOX) {

                #unlink("${filenamebody}.base.mp4");

                # デジタルラジオなら
                if ($inputmpeg2 =~ /aac$/i) {
                    if (-e "$toolpath/perl/tool/MP4Box") {
                        &writelog("MP4Box $filenamebody  :start.");
                        &writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac  -new $filenamebody.base.mp4");
                        system ("cd $recfolderpath ;nice -n 15 $toolpath/perl/tool/MP4Box -add $filenamebody.aac  -new $filenamebody.base.mp4");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("MP4Box  :$exit_value:$signal_num:$dumped_core:end.");
                    } else {
                        &writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
                    }
                } else {
                    # MP4ビルド
                    #if (-e "$toolpath/perl/tool/MP4Box") {
                        # Starlight breaker向けキャプチャ画像作成
                        # ここまででキャプチャを作ってなかったら作っておく。
                        $cp2_start  = time();
                        if (-e "$toolpath/perl/captureimagemaker.pl") {

                            $trcnmpegfilename = basename($trcnmpegfile);
                            &writelog("Call captureimagemaker(MP4Box) $trcnmpegfilename");
                            $tm_start = time();
                            system ("$toolpath/perl/captureimagemaker.pl $trcnmpegfilename");
                            $tm_end   = time();
                            $tm_process  = $tm_end - $tm_start;
                            $ftm_process = sprintf("%00d:%02d:%02d", int($tm_process / 3600), int($tm_process % 3600 / 60), $tm_process % 60);
                            &writelog("captureimagemaker.pl PROCESS TIME : $tm_process sec, $ftm_process");
                        }
                        $cp2_end    = time();

                    #    $mux_start  = time();
                    #    &changefilestatus($pid, $FILESTATUSTRANSCODEMP4BOX);
                    #    &writelog("MP4Box $filenamebody  :start.");
                    #    &writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
                    #    system ("cd $recfolderpath ;nice -n 15 $toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
                    #    $exit_value  = $? >> 8;
                    #    $signal_num  = $? & 127;
                    #    $dumped_core = $? & 128;
                    #    &writelog("MP4Box -add 264 new $filenamebody.base.mp4  :$exit_value:$signal_num:$dumped_core:end.");

                    #    if (-e "$filenamebody.base.mp4") {
                    #        &writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac $filenamebody.base.mp4  :start.");
                    #        system ("cd $recfolderpath ;nice -n 15 $toolpath/perl/tool/MP4Box -add $filenamebody.aac $filenamebody.base.mp4");
                    #        $exit_value  = $? >> 8;
                    #        $signal_num  = $? & 127;
                    #        $dumped_core = $? & 128;
                    #        &writelog("MP4Box -add aac  :$exit_value:$signal_num:$dumped_core:end.");
                    #    } else {
                    #        $filelist = `ls -lhtr $recfolderpath/${filenamebody}*`;
                    #        $debugenv = `env`;
                    #        $debugenv =~ s/\n/ /g;
                    #        &writelog("ERR File not exist. $debugenv. $filelist; $filenamebody.base.mp4; $filelist; cd $recfolderpath; $toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
                    #    }
                    #    $mux_end    = time();
                    #} else {
                    #    &writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
                    #}
                    #$auFileSize  = `du -sh $filenamebody.aac | awk '{print \$1}'`;
                    #unlink("$filenamebody.aac");
                } # endif #デジタルラジオなら
                &changefilestatus($pid, $FILESTATUSTRANSCODEMP4BOX);


                #if (-e "$toolpath/perl/tool/MP4Box") {
                #    # iPodヘッダ付加
                #    #&changefilestatus($pid,$FILESTATUSTRANSCODEATOM);
                #    &writelog("ATOM $filenamebody");
                #    #system ("/usr/local/bin/ffmpeg -y -i $filenamebody.base.mp4 -vcodec copy -acodec copy -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4");
                #    #system ("cd $recfolderpath ; MP4Box -ipod $filenamebody.base.mp4");
                #    &writelog("CMD: cd $recfolderpath ; $toolpath/perl/tool/MP4Box -ipod $filenamebody.base.mp4  :start.");
                #    system ("cd $recfolderpath ; $toolpath/perl/tool/MP4Box -ipod $filenamebody.base.mp4");
                #    $exit_value = $? >> 8;
                #    $signal_num = $? & 127;
                #    $dumped_core = $? & 128;
                #    &writelog("MP4Box -ipod $filenamebody.base.mp4  :$exit_value:$signal_num:$dumped_core:end.");
                    if (-e "$filenamebody.base.mp4") {
                        unlink("${mp4outdir}MAQ${mp4filenamestring}.MP4");
                        if (!-d "${mp4outdir}") {
                            # MP4の格納ディレクトリがなければ作成する。
                            &writelog("CMD: mkdir -p ${mp4outdir}");
                            system("mkdir -p ${mp4outdir}");
                        }
                        &writelog("CMD: mv $filenamebody.base.mp4 ${mp4outdir}MAQ${mp4filenamestring}.MP4");
                        system("mv $filenamebody.base.mp4 ${mp4outdir}MAQ${mp4filenamestring}.MP4");

                        $encmp4size = -s "${mp4outdir}MAQ${mp4filenamestring}.MP4";
                        $encmp4size = $encmp4size / 1024 / 1024;
                        &writelog("create MP4 file size: $encmp4size MB");

                    } else {
                        &writelog("ERR $filenamebody.base.mp4 Not found.");
                    }
                ## mv /home/foltia/php/tv/1329-21-20080829-0017.base.mp4 /home/foltia/php/tv/1329.localized/mp4/MAQ-/home/foltia/php/tv/1329-21-20080829-0017.MP4
                #} else {
                #    &writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
                #}
            }

            $tmpFileDel = 0;
            if ($filestatus <= $FILESTATUSTRANSCODECOMPLETE) {
                if (-e "${mp4outdir}MAQ${mp4filenamestring}.MP4") {
                    # 中間ファイル消す
                    &changefilestatus($pid, $FILESTATUSTRANSCODECOMPLETE);
                    &updatemp4file();
                    $tmpFileDel = 0;
                } else {
                    &writelog("ERR ; Fail.Giving up!  MAQ${mp4filenamestring}.MP4");
                    &changefilestatus($pid, 999);
                    $tmpFileDel = 1;
                }
                $spFileSize   = `du -sh $trcnmpegfile  | awk '{print \$1}'`;
                #$vdFileSize  = `du -sh $filenamebody.264        | awk '{print \$1}'`;
                #$vdFileSize  = `du -sh ${filenamebody}.base.mp4 | awk '{print \$1}'`;
                $srcFileSize  = `du -sh $encsrcfile    | awk '{print \$1}'`;

                if ($tmpFileDel == 0) {
                    unlink("${filenamebody}_SD1.m2t");
                    unlink("${filenamebody}_SD2.m2t");
                    unlink("${filenamebody}_SD3.m2t");
                    unlink("${filenamebody}_HD.m2t");
                    # ConfigによってTSファイルは常にsplitした状態にするかどうか選択
                    # B25失敗したときにここが走るとファイルぶっ壊れるので検証を入れる
                    #
                    #   if (-e "${filenamebody}_tss.m2t") {
                    #       unlink("${filenamebody}.m2t");
                    #       unless (rename "${filenamebody}_tss.m2t", "${filenamebody}.m2t") {
                    #       &writelog("WARNING RENAME FAILED ${filenamebody}_tss.m2t ${filenamebody}.m2t");
                    #       } else {
                    #
                    #       }
                    #   }
                    unlink("${filenamebody}_tss.m2t");
                    unlink("$filenamebody.264");
                    unlink("$filenamebody.wav");
                    unlink("$filenamebody.base.mp4");
                }

            }

        #} else { #アナログ
        #    #print "MPEG2\n";
        #    # アスペクト比
        #    if ($aspect == 16) {
        #        $cropopt = " -croptop 70 -cropbottom 60 -cropleft  8 -cropright 14 -aspect 16:9 ";
        #    } else {
        #        $cropopt = " -croptop 8 -cropbottom 8 -cropleft  8 -cropright 14 ";
        #    }

        #    # クオリティごとに
        #    if (($trconqty eq "")||($trconqty == 1)) {
        #        $mp4file = "${mp4outdir}M4V${mp4filenamestring}.MP4";
        #        $encodeoption = "-y -i $inputmpeg2 vcodec libxvid $cropopt -s 320x240 -b 300 -bt 128 -r 14.985 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}M4V${mp4filenamestring}.MP4";
        #        # 32sec
        #        # 2.1MB
        #    } elsif($trconqty == 2) {
        #        $mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        $encodeoption = "-y -i $inputmpeg2 -vcodec libx264 -croptop 8 $cropopt -s 320x240 -b 300 -bt 128 -r 24 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        # 2min22sec
        #        # 6.4MB
        #    } elsif($trconqty == 3) {
        #        $mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        $encodeoption = "-y -i $inputmpeg2  -vcodec libx264 $cropopt -s 320x240 -b 380 -bt 128 -r 29.97 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        #  2m53.912s
        #        # 7MB
        #    } elsif($trconqty == 4) {
        #        $mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        $encodeoption = "-y -i $inputmpeg2 -vcodec libx264 $cropopt -s 640x480 -b 500 -maxrate 700 -bt 128 -r 29.97 -deinterlace -acodec libfaac -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        # 11m0.294s
        #        # 20MB
        #    } elsif($trconqty == 5) {
        #        $mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        $encodeoption = "-y -i $inputmpeg2 -vcodec libx264 -croptop 8 $cropopt -s 640x480 -b 500 -maxrate 700 -bt 128 -r 29.97 -deinterlace -flags loop -trellis 2 -partitions parti4x4+parti8x8+partp4x4+partp8x8+partb8x8 -acodec libfaac -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4";
        #        #  14m14.033s
        #        # 18MB
        #    }

        #    $encodeoptionlog = $encodeoption;
        #    Jcode::convert(\$encodeoptionlog,'utf8');

        #    &writelog("START QTY=$trconqty $encodeoptionlog");
        #    #print "ffmpeg $encodeoptionlog \n";
        #    &changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
        #    system ("nice -n 15 $toolpath/perl/tool/ffmpeg  $encodeoption ");
        #    &writelog("FFEND $inputmpeg2");
        #    &changefilestatus($pid, $FILESTATUSTRANSCODECOMPLETE);
        #    # もう要らなくなった #2008/11/14
        #    #&writelog("mp4psp -p $mp4file $movietitleeuc");
        #    #system("/usr/local/bin/mp4psp -p $mp4file '$movietitleeuc' ");
        #    #&writelog("mp4psp COMPLETE  $mp4file ");

        #    &updatemp4file();
        } # endif #デジタルかアナログか

        $counttranscodefiles = &counttranscodefiles();
        ############################
        # 一回で終らせるように
        #exit;


    $mpeg2_tm_end = time();
    $mpeg2_tm_process   = $mpeg2_tm_end - $mpeg2_tm_start;

    $fmpeg2_tm_process = sprintf("%00d:%02d:%02d", int($mpeg2_tm_process / 3600), int($mpeg2_tm_process % 3600 / 60), $mpeg2_tm_process % 60);
    &writelog("$mpeg2filename TS -> MP4 encode PROCESS TIME : $mpeg2_tm_process sec, $fmpeg2_tm_process");

    $sp_sec     = $sp_end  - $sp_start;
    $cp_sec     = $cp_end  - $cp_start;
    $enc_sec    = $enc_end - $enc_start;
    #$wav_sec    = $wav_end - $wav_start;
    #$aac_sec    = $aac_end - $aac_start;
    $cp2_sec    = $cp2_end - $cp2_start;
    #$mux_sec    = $mux_end - $mux_start;

    $sp_time    = sprintf("%00d:%02d:%02d", int($sp_sec / 3600), int($sp_sec % 3600 / 60), $sp_sec % 60);
    $cp_time    = sprintf("%00d:%02d:%02d", int($cp_sec / 3600), int($cp_sec % 3600 / 60), $cp_sec % 60);
    $enc_time   = sprintf("%00d:%02d:%02d", int($enc_sec / 3600), int($enc_sec % 3600 / 60), $enc_sec % 60);
    #$wav_time   = sprintf("%00d:%02d:%02d", int($wav_sec / 3600), int($wav_sec % 3600 / 60), $wav_sec % 60);
    #$aac_time   = sprintf("%00d:%02d:%02d", int($aac_sec / 3600), int($aac_sec % 3600 / 60), $aac_sec % 60);
    $cp2_time   = sprintf("%00d:%02d:%02d", int($cp2_sec / 3600), int($cp2_sec % 3600 / 60), $cp2_sec % 60);
    #$mux_time   = sprintf("%00d:%02d:%02d", int($mux_sec / 3600), int($mux_sec % 3600 / 60), $mux_sec % 60);

    $tsFileSize     = `du -sh $inputmpeg2 | awk '{print \$1}'`;
    $tsFileSizeF    = sprintf("%6s", $tsFileSize);
    $spFileSizeF    = sprintf("%6s", $spFileSize);
    $srFileSizeF    = sprintf("%6s", $srcFileSize);
    #$vdFileSizeF    = sprintf("%6s", $vdFileSize);
    $auFileSizeF    = sprintf("%6s", $auFileSize);
    $m4FileSize     = `du -sh ${mp4outdir}MAQ${mp4filenamestring}.MP4 | awk '{print \$1}'`;
    $m4FileSizeF    = sprintf("%6s", $m4FileSize);
    $tsFileSize2    = -s $inputmpeg2;
    $m4FileSize2    = -s "${mp4outdir}MAQ${mp4filenamestring}.MP4";
    $compRateF      = sprintf("%6s", int($m4FileSize2 / $tsFileSize2 * 100 * 100) / 100);
    ($sec,$min,$hour,$day,$month,$year,$wdy,$yday) = localtime($mpeg2_tm_start);
    $stDateF        = sprintf("%02s/%02s/%02s %02s:%02s:%02s\n" ,$year+1900, $month+1, $day, $hour, $min, $sec);
    ($sec,$min,$hour,$day,$month,$year,$wdy,$yday) = localtime($mpeg2_tm_end);
    $edDateF        = sprintf("%02s/%02s/%02s %02s:%02s:%02s\n" ,$year+1900, $month+1, $day, $hour, $min, $sec);
    $thumbnailDir   = $mp4filenamestring;
    $thumbnailDir   =~ s/^-//;
    $thumbnailDir   = "$recfolderpath/$tid.localized/img/$thumbnailDir";
    $thDireSize     = `du -sh $thumbnailDir | awk '{print \$1}'`;
    $thDireSizeF    = sprintf("%6s", $thDireSize);
    $thCount        = `ls -1 $thumbnailDir | wc -l`;

    $recDirAvail    = `df -h $recfolderpath | tail -1`;
    chomp($recDirAvail);
    $recDirAvail =~ s/\s+/ /g;
    my @recDirAvailSp = split(/ /, $recDirAvail);

    &writelog("");
    &writelog("=========================== TS to MP4 ENCODE RESULT Start =========================");
    &writelog("  TS    FILE      : $tsFileSizeF : $inputmpeg2");
    &writelog("  SPLIT FILE      : $spFileSizeF : $trcnmpegfile");
    &writelog("  ENC SRC FILE    : $srFileSizeF : $encsrcfile");
    #&writelog("  VIDEO FILE      : $vdFileSizeF : $filenamebody.264");
    #&writelog("  AUDIO FILE      : $auFileSizeF : $filenamebody.aac");
    &writelog("  MP4   FILE      : $m4FileSizeF : ${mp4outdir}MAQ${mp4filenamestring}.MP4");
    &writelog("  THUMBNAIL DIR   : $thDireSizeF : $thCount files : $thumbnailDir");
    &writelog("  REC DIR AVAIL   : @recDirAvailSp[3] @recDirAvailSp[4] @recDirAvailSp[5]");
    &writelog("  COMPRESSION RATE: $compRateF%");
    &writelog("  START   TIME    : $stDateF");
    &writelog("  END     TIME    : $edDateF");
    &writelog("  PROCESS TIME    : ${mpeg2_tm_process}sec $fmpeg2_tm_process");
    &writelog("    SP TIME       : $sp_time ($ts_splitter)");
    &writelog("    CP TIME       : $cp_time");
    &writelog("    CP2 TIME      : $cp2_time");
    &writelog("    ENC TIME      : $enc_time");
    #&writelog("    WAV TIME      : $wav_time");
    #&writelog("    AAC TIME      : $aac_time");
    #&writelog("    MUX TIME      : $mux_time");
    &writelog("=========================== TS to MP4 ENCODE RESULT End ===========================");
    &writelog("");
    &writelog("");

    print("=========================== TS to MP4 ENCODE RESULT Start =========================\n");
    print("  TS    FILE      : $tsFileSizeF : $inputmpeg2\n");
    print("  SPLIT FILE      : $spFileSizeF : $trcnmpegfile\n");
    print("  MP4   FILE      : $m4FileSizeF : ${mp4outdir}MAQ${mp4filenamestring}.MP4\n");
    print("  THUMBNAIL DIR   : $thDireSizeF : $thCount files : $thumbnailDir\n");
    print("  COMPRESSION RATE: $compRateF%\n");
    print("  START   TIME    : $stDateF\n");
    print("  END     TIME    : $edDateF\n");
    print("  PROCESS TIME    : ${mpeg2_tm_process}sec $fmpeg2_tm_process\n");
    print("    SP TIME       : $sp_time ($ts_splitter)\n");
    print("    CP TIME       : $cp_time\n");
    print("    ENC TIME      : $enc_time\n");
    print("  REC DIR AVAIL   : @recDirAvailSp[3] @recDirAvailSp[4] @recDirAvailSp[5]\n");
    print("=========================== TS to MP4 ENCODE RESULT End   =========================\n");
    print("\n");
    print("\n");

    chomp($m4FileSize);
    chomp($tsFileSize);
    $head  = "ts->mp4 エンコード完了";
    $mesg  = sprintf("pid          : %s\n", $pid);
    $mesg .= sprintf("tid          : %s\n", $tid);
    $mesg .= sprintf("タイトル     : %s\n", $title);
    $mesg .= sprintf("サブタイトル : %s\n", $subtitle);
    $mesg .= sprintf("話数         : %s\n", $countno);
    $mesg .= sprintf("放送開始日時 : %s\n", $startdatetime);
    $mesg .= sprintf("放送終了日時 : %s\n", $enddatetime);
    $mesg .= sprintf("尺(分)       : %s\n", $lengthmin);
    $mesg .= sprintf("TS Splitter  : %s\n", $ts_splitter);
    $mesg .= sprintf("MP4ファル名  : %6s : MAQ%s.MP4\n", $m4FileSize, $mp4filenamestring);
    $mesg .= sprintf("TSファイル名 : %6s : %s\n", $tsFileSize, $mpeg2filename);
    $mesg .= sprintf("圧縮率       : %s%\n", int($m4FileSize2 / $tsFileSize2 * 100 * 100) / 100);
    $mesg .= sprintf("処理時間     : %ssec %s\n", $mpeg2_tm_process, $fmpeg2_tm_process);
    $mesg .= sprintf("空き容量     : %s %s %s \n", @recDirAvailSp[3], @recDirAvailSp[4], $recfolderpath);
    slackSend($head, $mesg);

    } else {
        # ファイルがなければ
        &writelog("[ERR][ABORT] NO $inputmpeg2 file. Skip.");
        &changefilestatus($pid, $FILESTATUSABORT);
    } # end if

} # end while

# 残りファイルがゼロなら
&writelog("ALL COMPLETE");
exit;


#-----------------------------------------------------------------------
sub mp4filenamestringbuild() {
    #ファイル名決定
    #1329-19-20080814-2337.m2t
    my @mpegfilename = split(/\./,$dbparam[2]) ;
    my $pspfilname   = "-".$mpegfilename[0] ;
    return("$pspfilname");
} #end sub mp4filenamestringbuild


sub makethumbnail() {
    #サムネール
    my $outputfilename = $inputmpeg2 ;#フルパス
    my $thmfilename    = "MAQ${mp4filenamestring}.THM";
    &writelog("DEBUG makethumbnail() start. ffmpeg $thmfilename");

    $cmd = "nice -n 15 $toolpath/perl/tool/ffmpeg -loglevel quiet -ss 00:01:20 -i $outputfilename -vframes 1 -s 160x90 -f image2 $pspdirname/$thmfilename";
    &writelog($cmd);
    system ($cmd);

    &writelog("DEBUG makethumbnail() end. ffmpeg $thmfilename");

} #endsub makethumbnail

sub updatemp4file() {
    my $mp4filename = "MAQ${mp4filenamestring}.MP4";

    if (-e "${mp4outdir}MAQ${mp4filenamestring}.MP4") {
        # MP4ファイル名をPIDレコードに書き込み
        $sth = $dbh->prepare($stmt{'ipodtranscode.updatemp4file.1'});
        $sth->execute($mp4filename, $pid);
        &writelog("UPDATEsubtitleDB $stmt{'ipodtranscode.updatemp4file.1'}");

        # MP4ファイル名をfoltia_mp4files挿入
        $sth = $dbh->prepare($stmt{'ipodtranscode.updatemp4file.2'});
        $sth->execute($tid, $mp4filename);
        &writelog("UPDATEmp4DB: $stmt{'ipodtranscode.updatemp4file.2'}: $tid, $mp4filename");

        &changefilestatus($pid, $FILESTATUSALLCOMPLETE);
    } else {
        &writelog("ERR MP4 NOT EXIST $pid/$mp4filename");
    }

} #updatemp4file

sub counttranscodefiles() {
    # トラコンフラグがたっていてステータス50以上150未満のファイルの数をチェック
    $sth = $dbh->prepare($stmt{'ipodtranscode.counttranscodefiles.1'});
    $sth->execute($FILESTATUSRECEND, $FILESTATUSTRANSCODECOMPLETE);
    my @titlecount= $sth->fetchrow_array;

    return ($titlecount[0]);

} #end sub counttranscodefiles


sub validationsplitfile {
    my $inputmpeg2   = $_[0];
    my $trcnmpegfile = $_[1];

    #Split結果確認
    my $filesizeoriginal = -s $inputmpeg2 ;
    my $filesizesplit    = -s $trcnmpegfile;
    my $validation       = 0;
    if ($filesizesplit  > 0) {
        $validation = $filesizesplit / $filesizeoriginal * 100  ;
        if ($validation < 50 ) {
            #print "Fail split may be fail.\n";
            &writelog("ERR File split may be fail.: $filesizeoriginal:${filesizesplit}. trcnmpegfile = $trcnmpegfile : split file size is under 50%.");
            $trcnmpegfile = $inputmpeg2 ;
            unlink("${filenamebody}_tss.m2t");
            unlink("${filenamebody}_HD.m2t");
            return ($trcnmpegfile);
        } else {
            #print "split may be good.\n";
            &writelog("split may be good.: $filesizeoriginal:${filesizesplit}. trcnmpegfile = $trcnmpegfile");
            return ($trcnmpegfile);
        }
    } else {
        #Fail
        &writelog("ERR File split may be fail.: $filesizeoriginal:${filesizesplit}. trcnmpegfile = $trcnmpegfile : split file size is zero.");
        $trcnmpegfile = $inputmpeg2 ;
        unlink("${filenamebody}_tss.m2t");
        unlink("${filenamebody}_HD.m2t");
        return ($trcnmpegfile);
    }
} #end sub validationsplitfile

sub validationsplitfilesd {
    my $inputmpeg2   = $_[0];
    my $sdfile1      = $_[1];
    my $sdfile2      = $_[2];
    my $sdfile3      = $_[3];
    my $trcnmpegfile = $inputmpeg2;

    #Split結果確認
    my $filesizeoriginal = -s $inputmpeg2 ;
    my $filesizesplit;

    # ファイルサイズ取得
    if (-e $sdfile1) {
        $sdfile1size = -s $sdfile1;
    } else {
        $sdfile1size = 0;
    }
    if (-e $sdfile2) {
        $sdfile2size = -s $sdfile2;
    } else {
        $sdfile2size = 0;
    }
    if (-e $sdfile3) {
        $sdfile3size = -s $sdfile3;
    } else {
        $sdfile3size = 0;
    }

    # SD1 と SD2 を比較
    if ($sdfile1size > $sdfile2size) {
        $trcnmpegfile = $sdfile1;
    } else {
        $trcnmpegfile = $sdfile2;
    }
    # SD3 と比較
    if (-s $trcnmpegfile > $sdfile3size) {
        $trcnmpegfile = $trcnmpegfile;
    } else {
        $trcnmpegfile = $sdfile3;
    }
    $filesizesplit = -s $trcnmpegfile;

    my $validation = 0;
    if ($filesizesplit  > 0) {
        $validation = $filesizesplit / $filesizeoriginal * 100;
        if ($validation >= 25 ) {
            return ($trcnmpegfile);
        } else {
            &writelog("ERR File split may be fail: $filesizeoriginal:${filesizesplit}. split file size is under 25%.");
            $trcnmpegfile = $inputmpeg2 ;
            unlink("${filenamebody}_SD1.m2t");
            unlink("${filenamebody}_SD2.m2t");
            unlink("${filenamebody}_SD3.m2t");
            unlink("${filenamebody}_HD.m2t");
            return ($trcnmpegfile);
        }
    } else {
        &writelog("ERR File split may be fail: $filesizeoriginal:${filesizesplit}. split file size is zero.");
        $trcnmpegfile = $inputmpeg2 ;
        unlink("${filenamebody}_SD1.m2t");
        unlink("${filenamebody}_SD2.m2t");
        unlink("${filenamebody}_SD3.m2t");
        unlink("${filenamebody}_HD.m2t");
        return ($trcnmpegfile);
    }
} #end sub validationsplitfilesd

