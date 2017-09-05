#!/bin/sh
#用于生成proto的描述文件
#客户端和服务器端使用什么样的语言自行修改命令即可
#  protoFiles.txt中填写对应的pb文件名
#  --cpp_out=OUT_DIR           Generate C++ header and source. 
#  --csharp_out=OUT_DIR        Generate C# source file. 
#  --java_out=OUT_DIR          Generate Java source file. 
#  --javanano_out=OUT_DIR      Generate Java Nano source file. 
#  --js_out=OUT_DIR            Generate JavaScript source. 
#  --objc_out=OUT_DIR          Generate Objective C header and source. 
#  --python_out=OUT_DIR        Generate Python source file. 
#  --ruby_out=OUT_DIR          Generate Ruby source file.

PROTO_FILES=./protoFiles.txt
READ_LINE_FILES=''
#定义颜色的变量
RES="\033[0m"

#字颜色变量
BLACK_COLOR="\033[30m"          #黑色
RED_COLOR="\033[31m"            #红色
GREEN_COLOR="\033[32m"          #绿色
YELLOW_COLOR="\033[33m"         #黄色
BLUE_COLOR="\033[34m"           #蓝色
PURPLE_COLOR="\033[35m"         #紫色
SKY_GREEN_COLOR="\033[36m"      #天绿色
WHITE_COLOR="\033[37m"          #白色


readFiles()
{
    for i in `cat $PROTO_FILES`; do
        READ_LINE_FILES=$READ_LINE_FILES${i}' '
    done
}
readFiles
echo $READ_LINE_FILES
echo "\n${RED_COLOR}--------------------------------------${RES}"
echo "Generate Proto To Server"
./protoc --php_out=../Server/ $READ_LINE_FILES
echo "Generate Proto To Server END"
sleep 2
echo "${RED_COLOR}--------------------------------------${RES}"
echo "Generate Proto To Client"
./protoc --csharp_out=../Client/ $READ_LINE_FILES
echo "Generate Proto To Client END"