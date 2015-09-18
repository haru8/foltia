#! /usr/bin/env python
# -*- coding:utf-8 -*-
#
# TsSplitterもどき
# tss.py [tsファイル名]
#
# 出力ファイルは[tsファイル名]の.拡張子の前に_tssが埋め込まれた名前になります。
# ex) tss.py hoge.ts ⇒ hoge_tss.tsを出力。
# （拡張子は必ずつけてください。）
#
import sys

################################ CRC計算
def crc32(data) :
    crc = 0xFFFFFFFF
    for x in data :
        for i in range(8) :
            bit = (x>>(7-i))&0x1
            
            c = 0
            if crc & 0x80000000 :
                c = 1
                
            crc = crc << 1
            
            if c ^ bit :
                crc ^= 0x04c11db7
                
            crc &= 0xFFFFFFFF

    return crc

################################ main

# 第1引数を取り出し
argvs = sys.argv
infile = argvs[1]

# tsファイルをオープン
fi = open(infile, "rb")

# PMTのPIDを初期化
pmt = ""

# 残すPIDリストを初期化
pids = []


# PAT差し替えデータを初期化
z = ""

# PMTパケットデータを初期化
w = ""

### 残すPIDを捜索
while True:
    x = fi.read(188)
    
    if x == "" :
        break
    
    pid = "0x%02x%02x" % ( ord(x[1])&0x1F, ord(x[2]) )
    
    # PAT
    if pid == "0x0000" :
        pmt = "0x%02x%02x" % ( ord(x[19])&0x1F, ord(x[20]) )
        pids.append( pmt )
        
        if z == ""  :
            # PATのPMT1のみ残したデータをリスト化
            y = [ ord(x[i]) for i in range(5, 21) ]
            
            # セクション長を0x11で上書き
            y[2] = 0x11
            
            # CRC計算
            crc = crc32(y)
            
            # PAT差し替えデータを作成
            for tmp in y :
                z += chr(tmp)
                
            z += chr((crc >> 24)&0xFF )
            z += chr((crc >> 16)&0xFF )
            z += chr((crc >>  8)&0xFF )
            z += chr( crc       &0xFF )
            
            for i in range(25,188) :
                z += chr(0xFF)
    
    # PMT
    if pid == pmt :
        w += x if w == "" else x[4:]
        Nall = ((ord(w[6])&0x0F)<<4) + ord(w[7])
       
        if len(w) >= Nall + 8 :
            # PCR
            pcr = "0x%02x%02x" % ( ord(w[13])&0x1F, ord(w[14]) )
            pids.append( pcr )
            N = ((ord(w[15])&0x0F)<<4) + ord(w[16]) + 16 +1
            
            # EPID
            while N < Nall +8 -4 :
                if  ord(w[N]) != 0x0d :
                    pids.append( "0x%02x%02x" % ( ord(w[N+1])&0x1F, ord(w[N+2]) ) )
                N += 4 + ((ord(w[N+3])&0x0F)<<4) + ord(w[N+4]) + 1
 
            
        print pids
        break


### PATを修正しつつ、残すPIDのみ出力
sp = infile.split(".")
sp[-2] += "_tss"
outfile = ".".join(sp)
fo = open(outfile, "wb")

# tsファイルの先頭に戻す(入力をfifoにする場合はコメントアウト)
fi.seek(0)

# ファイル出力
while True:
    x = fi.read(188)
    
    if x == "" :
        break
    
    pid = "0x%02x%02x" % ( ord(x[1])&0x1F, ord(x[2]) )
    
    # PAT
    if pid == "0x0000" :
        # TSヘッダ + zを出力
        fo.write( x[:5] + z )
        
    # その他PID
    elif pid in pids:
        fo.write(x)


fo.close()
fi.close()

