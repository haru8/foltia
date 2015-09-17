#!/usr/bin/perl

$rtool="/usr/local/apps/foltia2/perl/tool/hdusrec";
$ctool="/usr/local/apps/foltia2/perl/tool/epgdump";
$dtool="/usr/local/apps/foltia2/perl/xmltv2foltia.pl";
$dtime=60;
#$tdir="/usr/local/apps/foltia2/perl";
$tdir="/tmp";

@channel = (
    [ 18, '0009.ontvjapan.com' ], # TVK
    [ 16, '0016.ontvjapan.com' ], # MX
    [ 21, '0006.ontvjapan.com' ], # CX
    [ 22, '0005.ontvjapan.com' ], # TBS
    [ 23, '0008.ontvjapan.com' ], # TX
    [ 24, '0007.ontvjapan.com' ], # EX
    [ 25, '0004.ontvjapan.com' ], # NTV
    [ 26, '0041.ontvjapan.com' ], # ETV
    [ 27, '0031.ontvjapan.com' ], # NHK
    );

while ($ch = shift(@channel)) {
    $c = @$ch[0];
    $n = @$ch[1];

    $t1 = `mktemp ${tdir}/tv_grab.$c.ts`;
    chomp $t1;
    $com = "$rtool $c $dtime $t1";
    system($com);

    $t2 = `mktemp ${tdir}/tv_grab.$c.xml`;
    chomp $t2;
    $com = "$ctool $n $t1 $t2";
    system($com);

    $com = "$dtool < $t2";
    #printf("%s\n", $com);
    system($com);

    unlink($t1, $t2);
}

