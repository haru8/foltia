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

use DBI;
use DBD::Pg;
use DBD::SQLite;
use Jcode;
use File::Basename;

$path = $0;
$path =~ s/ipodtranscode.pl$//i;
if ($path ne "./") {
	push( @INC, "$path");
}
require "foltialib.pl";


# 二重起動の確認!
$processes = &processfind("ipodtranscode.pl");
if ($processes > 1 ) {
	&writelog("processes exist. exit: $processes");
	exit;
} else {
	&writelog("Normal launch.");
}

#DB初期化
$dbh = DBI->connect($DSN,$DBUser,$DBPass) ||die $DBI::error;;

# タイトル取得
#トラコンフラグがたっていてステータス50以上150未満のファイルを古い順にひとつ探す
# 数数える
#$DBQuery =  "SELECT count(*) FROM foltia_subtitle, foltia_program, foltia_m2pfiles 
#WHERE filestatus >= $FILESTATUSRECEND AND filestatus < $FILESTATUSTRANSCODECOMPLETE  AND foltia_program.tid = foltia_subtitle.TID AND foltia_program.PSP = 1  AND foltia_m2pfiles.m2pfilename = foltia_subtitle.m2pfilename  ";
#$sth = $dbh->prepare($DBQuery);
#$sth->execute();
#@titlecount= $sth->fetchrow_array;
&writelog("starting up.");

$counttranscodefiles = &counttranscodefiles();
if ($counttranscodefiles == 0) {
	&writelog("No MPEG2 files to transcode.");
	exit;
}
sleep 30;

while ($counttranscodefiles >= 1) {
	$sth = $dbh->prepare($stmt{'ipodtranscode.1'});
	$sth->execute($FILESTATUSRECEND, $FILESTATUSTRANSCODECOMPLETE, );
	@dbparam = $sth->fetchrow_array;
	&writelog("DEBUG $DBQuery");
	&writelog("DEBUG $dbparam[0], $dbparam[1], $dbparam[2], $dbparam[3], $dbparam[4], $dbparam[5]");
	$pid               = $dbparam[0];
	$tid               = $dbparam[1];
	$inputmpeg2        = $recfolderpath."/".$dbparam[2]; # path付き
	$mpeg2filename     = $dbparam[2]; # pathなし
	$filestatus        = $dbparam[3];
	$aspect            = $dbparam[4];# 16,1 (超額縁),4,3
	$countno           = $dbparam[5];
	$mp4filenamestring = &mp4filenamestringbuild($pid);

	if (-e $inputmpeg2) { #MPEG2ファイルが存在していれば

		&writelog("DEBUG mp3filenamestring $mp4filenamestring");
		#展開ディレクトリ作成
		$pspdirname = &makemp4dir($tid);
		$mp4outdir = $pspdirname ;

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
				#EPG
				$movietitle    = " -title \"$programtitle[2]\" ";
				$movietitleeuc = " -t \"$programtitle[2]\" ";
			} else {# 0
				#空白
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
			#なにもしない
		}

		if ($filestatus <= $FILESTATUSCAPTURE) {
			# unlink
			# Starlight breaker向けキャプチャ画像作成
			if (-e "$toolpath/perl/captureimagemaker.pl") {
				&writelog("Call captureimagemaker $mpeg2filename");
				&changefilestatus($pid, $FILESTATUSCAPTURE);
				system ("$toolpath/perl/captureimagemaker.pl $mpeg2filename");
				&changefilestatus($pid, $FILESTATUSCAPEND);
			}
		}

		if ($filestatus <= $FILESTATUSCAPEND) {
			# サムネイル作る
			&makethumbnail();
			&changefilestatus($pid,$FILESTATUSTHMCREATE);
		}

		if ($filestatus <= $FILESTATUSWAITINGTRANSCODE) {
		}

		$filenamebody = $inputmpeg2 ;
		$filenamebody =~ s/.m2t$|.ts$|.m2p$|.mpg$|.aac$//gi;

		#デジタル
		if ($inputmpeg2 =~ /m2t$|ts$|aac$/i) {

			if ($filestatus <= $FILESTATUSTRANSCODETSSPLITTING){
				unlink("${filenamebody}_tss.m2t");
				unlink("${filenamebody}_HD.m2t");
			}

			if ($filestatus <= $FILESTATUSTRANSCODEFFMPEG) {
				unlink("$filenamebody.264");

				# H.264出力
				$trcnmpegfile = $inputmpeg2 ;

				# アスペクト比
				if ($aspect == 1) { #超額縁
					#$cropopt = " -croptop 150 -cropbottom 150 -cropleft 200 -cropright 200 ";
					$cropopt = " -vf crop=in_w-400:in_h-300:200:150 ";
				} elsif($aspect == 4) { #SD 
					#$cropopt = " -croptop 6 -cropbottom 6 -cropleft 8 -cropright 8 ";
					$cropopt = " -vf crop=in_w-16:in_h-12:8:6 ";
				} else { #16:9
					#$cropopt = " -croptop 6 -cropbottom 6 -cropleft 8 -cropright 8 ";
					$cropopt = " -vf crop=in_w-16:in_h-12:8:6 ";
				}

				# クオリティごとに
				if (($trconqty eq "")||($trconqty == 1)) {
					$ffmpegencopt = " -threads 0 -s 360x202 -deinterlace -r 24.00 -vcodec libx264 -preset fast -g 300 -b 330000 -level 13 -sc_threshold 60 -refs 3 -maxrate 700000 -async 50 -f h264 $filenamebody.264";

				} elsif($trconqty == 2) {
					$ffmpegencopt = " -threads 0 -s 480x272 -deinterlace -r 29.97 -vcodec libx264 -preset fast -g 300 -b 400000 -level 13 -sc_threshold 60 -refs 3 -maxrate 700000 -async 50 -f h264 $filenamebody.264";

				} elsif($trconqty == 3) {
					#$ffmpegencopt = " -threads 0 -s 640x352 -deinterlace -r 29.97 -vcodec libx264 -preset medium -g 100 -b 600k -bufsize 768k -maxrate 700k -level 13 -qmin 10 -qmax 35 -sc_threshold 60 -refs 3 -async 50 -f h264 $filenamebody.264";
					$ffmpegencopt = " -threads 0 -s 640x352 -r 29.97 -vcodec libx264 -preset fast -g 100 -crf 21 -bufsize 768k -maxrate 768K -level 30 -sc_threshold 60 -refs 3 -async 50 -f h264 $filenamebody.264";

				}
				# 不安定なので頭2秒は捨てる
				$sstime = " -ss 00:00:02.000 ";

				&changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
				#	&writelog("ffmpeg $filenamebody.264");
				#	system ("/usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $ffmpegencopt");
				# まずTsSplitする →ワンセグをソースにしてしまわないように
				if (! -e "$filenamebody.264") {
					&changefilestatus($pid, $FILESTATUSTRANSCODETSSPLITTING);
					unlink("${filenamebody}_tss.m2t");
					unlink("${filenamebody}_HD.m2t");
					if (-e "$toolpath/perl/tool/tss.py") {
						&writelog("$toolpath/perl/tool/tss.py $inputmpeg2  :start.");
						system("$toolpath/perl/tool/tss.py $inputmpeg2");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("tss.py $inputmpeg2 :$exit_value:$signal_num:$dumped_core:end.");

					} else {
					# TsSplit
					#		&writelog("TsSplitter $inputmpeg2");
					#		system("wine $toolpath/perl/tool/TsSplitter.exe  -EIT -ECM  -EMM -SD -1SEG -WAIT2 $inputmpeg2");
					}

					if(-e "${filenamebody}_tss.m2t") {
						$trcnmpegfile = "${filenamebody}_tss.m2t";
						&writelog("trcnmpegfile = $trcnmpegfile");
					} elsif (-e "${filenamebody}_HD.m2t") {
						$trcnmpegfile = "${filenamebody}_HD.m2t";
						&writelog("trcnmpegfile = $trcnmpegfile");
					} else {
						&writelog("ERR NOT Exist ${filenamebody}_tss.m2t or ${filenamebody}_HD.m2t");
						$trcnmpegfile = $inputmpeg2 ;
					}

					# Splitファイルの確認
					$trcnmpegfile = &validationsplitfile($inputmpeg2, $trcnmpegfile);
					&writelog("trcnmpegfile = $trcnmpegfile");

					# tss.py に失敗してたなら強制的にWINEでTsSplit.exe
					if($trcnmpegfile eq $inputmpeg2) {
						# TsSplit
						&writelog("WINE TsSplitter.exe -EIT -ECM  -EMM -SD -1SEG $inputmpeg2  :start.");
						system("wine $toolpath/perl/tool/TsSplitter.exe -EIT -ECM  -EMM -SD -1SEG $inputmpeg2");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("TsSplitter.exe  :$exit_value:$signal_num:$dumped_core:end.");

						if (-e "${filenamebody}_HD.m2t") {
							$trcnmpegfile = "${filenamebody}_HD.m2t";
							&writelog("trcnmpegfile = $trcnmpegfile");

							# ストリームの最初からのはずなので捨てない。
							$sstime = "";

							# Splitファイルの確認
							$trcnmpegfile = &validationsplitfile($inputmpeg2, $trcnmpegfile);
							&writelog("trcnmpegfile = $trcnmpegfile");

							# Starlight breaker向けキャプチャ画像作成
							if (-e "$toolpath/perl/captureimagemaker.pl") {
								$trcnmpegfilename = basename($trcnmpegfile);
								&writelog("Call captureimagemaker(TsSplit) $trcnmpegfilename");
								system ("$toolpath/perl/captureimagemaker.pl $trcnmpegfilename");
							}

				#			if($trcnmpegfile ne $inputmpeg2) {
				#				&changefilestatus($pid,$FILESTATUSTRANSCODEFFMPEG);
				#				&writelog("ffmpeg retry ; WINE TsSplitter.exe $trcnmpegfile");
				#				system ("/usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $ffmpegencopt");
				#			} else {
				#				&writelog("WINE TsSplit.exe fail");
				#			}
						} else {
							&writelog("WINE TsSplitter.exe; Not exist ${filenamebody}_HD.m2t");
						} #endif -e ${filenamebody}_HD.m2t

					} #endif $trcnmpegfile eq $inputmpeg2

					# TsSplitter.exeでも失敗してたならSDファイルを抽出してみる。
					if($trcnmpegfile eq $inputmpeg2) {
						# TsSplit
						&writelog("WINE TsSplitter.exe -EIT -ECM -EMM -1SEG $inputmpeg2  :start.");
						system("wine $toolpath/perl/tool/TsSplitter.exe -EIT -ECM  -EMM -1SEG $inputmpeg2");
                        $exit_value  = $? >> 8;
                        $signal_num  = $? & 127;
                        $dumped_core = $? & 128;
                        &writelog("TsSplitter.exe  :$exit_value:$signal_num:$dumped_core:end.");

						if (-e "${filenamebody}_SD1.m2t" || -e "${filenamebody}_SD2.m2t" || -e "${filenamebody}_SD3.m2t") {
							# Splitファイルの確認
							$trcnmpegfile = &validationsplitfilesd($inputmpeg2, "${filenamebody}_SD1.m2t", "${filenamebody}_SD2.m2t", "${filenamebody}_SD3.m2t");
							&writelog("trcnmpegfile = $trcnmpegfile");

							# ストリームの最初からのはずなので捨てない。
							$sstime = "";
						}
						# Starlight breaker向けキャプチャ画像作成
						if (-e "$toolpath/perl/captureimagemaker.pl") {
							$trcnmpegfilename = basename($trcnmpegfile);
							&writelog("Call captureimagemaker(TsSplit) $trcnmpegfilename");
							system ("$toolpath/perl/captureimagemaker.pl $trcnmpegfilename");
						}
					}

					# 再ffmpeg
					&changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
					&writelog("ffmpeg $filenamebody.264  :start.");
					&writelog("CMD: /usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $sstime $ffmpegencopt");
					system ("/usr/local/bin/ffmpeg -y -i $trcnmpegfile $cropopt $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg retry.  $trcnmpegfile :$exit_value:$signal_num:$dumped_core:end.");

				}

				# もしエラーになったらcropやめる
				if (! -e "$filenamebody.264") {
					#再ffmpeg
					&changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
					&writelog("ffmpeg retry no crop $filenamebody.264  :start.");
					&writelog("CMD: /usr/local/bin/ffmpeg -y -i $trcnmpegfile $sstime $ffmpegencopt");
					system ("/usr/local/bin/ffmpeg -y -i $trcnmpegfile $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg retry no crop.  $trcnmpegfile :$exit_value:$signal_num:$dumped_core:end.");
				}

				#強制的にWINEでTsSplit.exe
				if (! -e "$filenamebody.264") {
				}

				#それでもエラーならsplitしてないファイルをターゲットに
				if (! -e "$filenamebody.264") {
					# 不安定なので頭2秒は捨てる
					$sstime = " -ss 00:00:02.000 ";

					#再ffmpeg
					&changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
					&writelog("ffmpeg retry No splited originalTS file $filenamebody.264  :start.");
					&writelog("CMD: /usr/local/bin/ffmpeg -y -i $inputmpeg2 $sstime $ffmpegencopt");
					system ("/usr/local/bin/ffmpeg -y -i $inputmpeg2 $sstime $ffmpegencopt");
                    $exit_value  = $? >> 8;
                    $signal_num  = $? & 127;
                    $dumped_core = $? & 128;
                    &writelog("ffmpeg retry No splited.  $inputmpeg2 :$exit_value:$signal_num:$dumped_core:end.");
				}
				$enc264size = -s "$filenamebody.264";
				&writelog("ffmpeg encode 264 file size: $enc264size/1024/1024 MB");
			}

			if ($filestatus <= $FILESTATUSTRANSCODEWAVE) {
				# WAV出力
				unlink("${filenamebody}.wav");
				&changefilestatus($pid, $FILESTATUSTRANSCODEWAVE);

				# mplayer	
				#&writelog("mplayer $filenamebody.wav");
				#&writelog("CMD: mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast  :start.");
				#system ("mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast");
				#&writelog("CMD: mplayer $trcnmpegfile -vc null -vo null -ao pcm:file=$filenamebody.wav:fast  :end.");

				# ffmpeg aac
				#&writelog("ffmpeg aac  $trcnmpegfile :start.");
				#&writelog("CMD: ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec copy $filenamebody.aac");
				#system ("ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec copy $filenamebody.aac");

				# ffmpeg aac -> wav
				&writelog("ffmpeg aac -> wav  $trcnmpegfile :start.");
				&writelog("CMD: ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec pcm_s16le -ac 2 $filenamebody.wav");
				system ("ffmpeg -i $trcnmpegfile $sstime -map 0:1 -vn -acodec pcm_s16le -ac 2 $filenamebody.wav");
				
                $exit_value  = $? >> 8;
                $signal_num  = $? & 127;
                $dumped_core = $? & 128;
                &writelog("ffmpeg aac -> wav.  $trcnmpegfile :$exit_value:$signal_num:$dumped_core:end.");
			}

			if ($filestatus <= $FILESTATUSTRANSCODEAAC) {
				# AAC変換
				unlink("${filenamebody}.aac");
				&changefilestatus($pid, $FILESTATUSTRANSCODEAAC);
				if (-e "$toolpath/perl/tool/neroAacEnc") {
					if (-e "$filenamebody.wav") {
						&writelog("neroAacEnc $filenamebody.wav");
						&writelog("CMD: $toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac  :start.");
						system ("$toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac");
						&writelog("CMD: $toolpath/perl/tool/neroAacEnc -br 128000  -if $filenamebody.wav  -of $filenamebody.aac  :end.");

						$encaacsize = -s "$filenamebody.aac";
						&writelog("encode aac file size: $encaacsize/1024/1024 MB");
					} else {
						&writelog("ERR Not Found $filenamebody.wav");
					}
				} else {
					#print "DEBUG $to
					&writelog("faac $filenamebody.wav");
					&writelog("CMD: faac -b 128  -o $filenamebody.aac $filenamebody.wav  :start.");
					system ("faac -b 128  -o $filenamebody.aac $filenamebody.wav ");
					&writelog("CMD: faac -b 128  -o $filenamebody.aac $filenamebody.wav  :end.");
				}
			}

			if ($filestatus <= $FILESTATUSTRANSCODEMP4BOX) {

				unlink("${filenamebody}.base.mp4");

				# デジタルラジオなら
				if ($inputmpeg2 =~ /aac$/i) {
					if (-e "$toolpath/perl/tool/MP4Box") {
						&writelog("MP4Box $filenamebody  :start.");
						&writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac  -new $filenamebody.base.mp4");
						system ("cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac  -new $filenamebody.base.mp4");
						$exit_value  = $? >> 8;
						$signal_num  = $? & 127;
						$dumped_core = $? & 128;
						&writelog("MP4Box  :$exit_value:$signal_num:$dumped_core:end.");
					} else {
						&writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
					}
				} else {
					# MP4ビルド
					if (-e "$toolpath/perl/tool/MP4Box") {
						&changefilestatus($pid, $FILESTATUSTRANSCODEMP4BOX);
						&writelog("MP4Box $filenamebody  :start.");
						&writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
						system ("cd $recfolderpath ;$toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
						$exit_value  = $? >> 8;
						$signal_num  = $? & 127;
						$dumped_core = $? & 128;
						&writelog("MP4Box -add 264 new $filenamebody.base.mp4  :$exit_value:$signal_num:$dumped_core:end.");

						if (-e "$filenamebody.base.mp4") {
							&writelog("CMD: cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac $filenamebody.base.mp4  :start.");
							system ("cd $recfolderpath ;$toolpath/perl/tool/MP4Box -add $filenamebody.aac $filenamebody.base.mp4");
							$exit_value  = $? >> 8;
							$signal_num  = $? & 127;
							$dumped_core = $? & 128; 
							&writelog("MP4Box -add aac  :$exit_value:$signal_num:$dumped_core:end.");
						} else {
							$filelist = `ls -lhtr $recfolderpath/${filenamebody}*`;
							$debugenv = `env`;
							$debugenv =~ s/\n/ /g;
							&writelog("ERR File not exist. $debugenv. $filelist; $filenamebody.base.mp4; $filelist; cd $recfolderpath; $toolpath/perl/tool/MP4Box -fps 29.97 -add $filenamebody.264 -new $filenamebody.base.mp4");
						}
					} else {
						&writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
					}
					unlink("$filenamebody.aac");
				} # endif #デジタルラジオなら


			#if ($filestatus <= $FILESTATUSTRANSCODEATOM) 
				if (-e "$toolpath/perl/tool/MP4Box") {
					# iPodヘッダ付加
					#&changefilestatus($pid,$FILESTATUSTRANSCODEATOM);
					&writelog("ATOM $filenamebody");
					#system ("/usr/local/bin/ffmpeg -y -i $filenamebody.base.mp4 -vcodec copy -acodec copy -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4");
					#system ("cd $recfolderpath ; MP4Box -ipod $filenamebody.base.mp4");
					&writelog("CMD: cd $recfolderpath ; $toolpath/perl/tool/MP4Box -ipod $filenamebody.base.mp4  :start.");
					system ("cd $recfolderpath ; $toolpath/perl/tool/MP4Box -ipod $filenamebody.base.mp4");
					$exit_value = $? >> 8;
					$signal_num = $? & 127;
					$dumped_core = $? & 128;
					&writelog("MP4Box -ipod $filenamebody.base.mp4  :$exit_value:$signal_num:$dumped_core:end.");
					if (-e "$filenamebody.base.mp4") {
						unlink("${mp4outdir}MAQ${mp4filenamestring}.MP4");
						&writelog("CMD: mv $filenamebody.base.mp4 ${mp4outdir}MAQ${mp4filenamestring}.MP4");
						system("mv $filenamebody.base.mp4 ${mp4outdir}MAQ${mp4filenamestring}.MP4");
					} else {
						&writelog("ERR $filenamebody.base.mp4 Not found.");
					}
				# mv /home/foltia/php/tv/1329-21-20080829-0017.base.mp4 /home/foltia/php/tv/1329.localized/mp4/MAQ-/home/foltia/php/tv/1329-21-20080829-0017.MP4
				} else {
					&writelog("WARN; Pls. install $toolpath/perl/tool/MP4Box");
				}
			}

			if ($filestatus <= $FILESTATUSTRANSCODECOMPLETE) {
				if (-e "${mp4outdir}MAQ${mp4filenamestring}.MP4") {
					# 中間ファイル消す
					&changefilestatus($pid, $FILESTATUSTRANSCODECOMPLETE);
					&updatemp4file();
				} else {
					&writelog("ERR ; Fail.Giving up!  MAQ${mp4filenamestring}.MP4");
					&changefilestatus($pid, 999);
				}
				unlink("${filenamebody}_SD1.m2t");
				unlink("${filenamebody}_SD2.m2t");
				unlink("${filenamebody}_SD3.m2t");
				unlink("${filenamebody}_HD.m2t");
				# ConfigによってTSファイルは常にsplitした状態にするかどうか選択
				# B25失敗したときにここが走るとファイルぶっ壊れるので検証を入れる
				#
				#	if (-e "${filenamebody}_tss.m2t") {
				#		unlink("${filenamebody}.m2t");
				#		unless (rename "${filenamebody}_tss.m2t", "${filenamebody}.m2t") {
				#		&writelog("WARNING RENAME FAILED ${filenamebody}_tss.m2t ${filenamebody}.m2t");
				#		} else {
				#		
				#		}
				#	}
				unlink("${filenamebody}_tss.m2t");
				unlink("$filenamebody.264");
				unlink("$filenamebody.wav");
				unlink("$filenamebody.base.mp4");
			
			}

		} else { #アナログ
			#print "MPEG2\n";
			# アスペクト比
			if ($aspect == 16) {
				$cropopt = " -croptop 70 -cropbottom 60 -cropleft  8 -cropright 14 -aspect 16:9 ";
			} else {
				$cropopt = " -croptop 8 -cropbottom 8 -cropleft  8 -cropright 14 ";
			}

			# クオリティごとに
			if (($trconqty eq "")||($trconqty == 1)) {
				$mp4file = "${mp4outdir}M4V${mp4filenamestring}.MP4";
				$encodeoption = "-y -i $inputmpeg2 vcodec libxvid $cropopt -s 320x240 -b 300 -bt 128 -r 14.985 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}M4V${mp4filenamestring}.MP4";
				# 32sec
				# 2.1MB
			} elsif($trconqty == 2) { 
				$mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
				$encodeoption = "-y -i $inputmpeg2 -vcodec libx264 -croptop 8 $cropopt -s 320x240 -b 300 -bt 128 -r 24 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}MAQ${mp4filenamestring}.MP4";
				# 2min22sec
				# 6.4MB
			} elsif($trconqty == 3) {
				$mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
				$encodeoption = "-y -i $inputmpeg2  -vcodec libx264 $cropopt -s 320x240 -b 380 -bt 128 -r 29.97 -deinterlace -acodec libfaac -f ipod  ${mp4outdir}MAQ${mp4filenamestring}.MP4";
				#  2m53.912s
				# 7MB
			} elsif($trconqty == 4) {
				$mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
				$encodeoption = "-y -i $inputmpeg2 -vcodec libx264 $cropopt -s 640x480 -b 500 -maxrate 700 -bt 128 -r 29.97 -deinterlace -acodec libfaac -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4";
				# 11m0.294s
				# 20MB
			} elsif($trconqty == 5) { 
				$mp4file = "${mp4outdir}MAQ${mp4filenamestring}.MP4";
				$encodeoption = "-y -i $inputmpeg2 -vcodec libx264 -croptop 8 $cropopt -s 640x480 -b 500 -maxrate 700 -bt 128 -r 29.97 -deinterlace -flags loop -trellis 2 -partitions parti4x4+parti8x8+partp4x4+partp8x8+partb8x8 -acodec libfaac -f ipod ${mp4outdir}MAQ${mp4filenamestring}.MP4";
				#  14m14.033s
				# 18MB
			}
		
			$encodeoptionlog = $encodeoption;
			Jcode::convert(\$encodeoptionlog,'euc');
		
			&writelog("START QTY=$trconqty $encodeoptionlog");
			#print "ffmpeg $encodeoptionlog \n";
			&changefilestatus($pid, $FILESTATUSTRANSCODEFFMPEG);
			system ("/usr/local/bin/ffmpeg  $encodeoption ");
			&writelog("FFEND $inputmpeg2");
			&changefilestatus($pid, $FILESTATUSTRANSCODECOMPLETE);
			#もう要らなくなった #2008/11/14 
			#&writelog("mp4psp -p $mp4file $movietitleeuc");
			#system("/usr/local/bin/mp4psp -p $mp4file '$movietitleeuc' ");
			#&writelog("mp4psp COMPLETE  $mp4file ");
		
			&updatemp4file();
		} #endif #デジタルかアナログか

		$counttranscodefiles = &counttranscodefiles();
		############################
		#一回で終らせるように
		#exit;
		
		
	} else { #ファイルがなければ
		&writelog("NO $inputmpeg2 file.Skip.");
	} #end if

} # end while

#残りファイルがゼロなら
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
	&writelog("DEBUG thmfilename $thmfilename");

	#system ("mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -ao null -sstep 1 -frames 3  -v 3 $outputfilename");
	#
	#&writelog("DEBUG mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -ao null -sstep 1 -frames 3  -v 3 $outputfilename");
	if($outputfilename =~ /.m2t$/) {
		#ハイビジョンTS
		system ("mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -nosound -vf framestep=300step,scale=160:90,expand=160:120 -frames 1 $outputfilename");
		&writelog("DEBUG mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -nosound -vf framestep=300step,scale=160:90,expand=160:120 -frames 1 $outputfilename");
	} else {
		#アナログ
		system ("mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -nosound -vf framestep=300step,scale=165:126,crop=160:120 -frames 1 $outputfilename");
		&writelog("DEBUG mplayer -ss 00:01:20 -vo jpeg:outdir=$pspdirname -nosound -vf framestep=300step,scale=165:126,crop=160:120 -frames 1 $outputfilename");
	}
	#if (-e "$pspdirname/$thmfilename"){
	#	$timestamp = strftime("%Y%m%d-%H%M%S", localtime);
	#chomp $timestamp;
	#	system("convert -crop 160x120+1+3 -resize 165x126\! $pspdirname/00000002.jpg $pspdirname/$thmfilename".$timestamp.".THM");
	#}else{
	#	system("convert -crop 160x120+1+3 -resize 165x126\! $pspdirname/00000002.jpg $pspdirname/$thmfilename");
	#}
	#&writelog(" DEBUG convert -crop 160x120+1+3 -resize 165x126\! $pspdirname/00000002.jpg $pspdirname/$thmfilename");
	
	#system("rm -rf $pspdirname/0000000*.jpg ");
	#&writelog("DEBUG rm -rf $pspdirname/0000000*.jpg");
	system("mv $pspdirname/00000001.jpg $pspdirname/$thmfilename");

} #endsub makethumbnail

sub updatemp4file() {
	my $mp4filename = "MAQ${mp4filenamestring}.MP4";

	if (-e "${mp4outdir}MAQ${mp4filenamestring}.MP4") {
		# MP4ファイル名をPIDレコードに書き込み
		$sth = $dbh->prepare($stmt{'ipodtranscode.updatemp4file.1'});
		$sth->execute($mp4filename, $pid);
		&writelog("UPDATEsubtitleDB $stmt{'ipodtranscode.updatemp4file.1'} $mp4filename, $pid");

		# MP4ファイル名をfoltia_mp4files挿入
		$sth = $dbh->prepare($stmt{'ipodtranscode.updatemp4file.2'});
		$sth->execute($tid, $mp4filename);
		&writelog("UPDATEmp4DB $stmt{'ipodtranscode.updatemp4file.2'} $tid, $mp4filename");

		&changefilestatus($pid, $FILESTATUSALLCOMPLETE);
	} else {
		&writelog("ERR MP4 NOT EXIST $pid/$mp4filename");
	}

} #updatemp4file

sub counttranscodefiles() {
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
		$validation = $filesizeoriginal / $filesizesplit   ;
		if ($validation > 2 ) {
			#print "Fail split may be fail.\n";
			&writelog("ERR File split may be fail: $filesizeoriginal:$filesizesplit");
			$trcnmpegfile = $inputmpeg2 ;
			unlink("${filenamebody}_tss.m2t");
			unlink("${filenamebody}_HD.m2t");
			return ($trcnmpegfile);
		} else {
			#print "Fail split may be good.\n";
			return ($trcnmpegfile);
		}
	} else {
		#Fail
		&writelog("ERR File split may be fail: $filesizeoriginal:$filesizesplit");
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
	
	my $validation       = 0;
	if ($filesizesplit  > 0) {
		$validation = $filesizeoriginal / $filesizesplit   ;
		if ($validation > 2.5 ) {
			&writelog("ERR File split may be fail: $filesizeoriginal:$filesizesplit");
			$trcnmpegfile = $inputmpeg2 ;
			unlink("${filenamebody}_SD1.m2t");
			unlink("${filenamebody}_SD2.m2t");
			unlink("${filenamebody}_SD3.m2t");
			unlink("${filenamebody}_HD.m2t");
			return ($trcnmpegfile);
		} else {
			return ($trcnmpegfile);
		}
	} else {
		&writelog("ERR File split may be fail: $filesizeoriginal:$filesizesplit");
		$trcnmpegfile = $inputmpeg2 ;
		unlink("${filenamebody}_SD1.m2t");
		unlink("${filenamebody}_SD2.m2t");
		unlink("${filenamebody}_SD3.m2t");
		unlink("${filenamebody}_HD.m2t");
		return ($trcnmpegfile);
	}
} #end sub validationsplitfilesd

