#
# This file is part of SwooleGateway.
#
# Licensed under The MIT License
# For full copyright and license information, please see the MIT-LICENSE.txt
# Redistributions of files must retain the above copyright notice.
#
# @author    mingming<363658434@qq.com>
# @copyright mingming<363658434@qq.com>
# @link      xxxx
# @license   http://www.opensource.org/licenses/mit-license.php MIT License
#

#PHP_BIN=$(which php)
PHP_BIN=/Applications/XAMPP/bin/php
APP_NAME="Worker"
PID_FILE="../run/workerSvr_master.pid"
PS_EXE="/bin/ps"
DATE="`date`"
SERVER_LOG=../log/worker_swoole.log

getpid()
{
    if [[ -f "$PID_FILE" ]]; then
        if [[ -r "$PID_FILE" ]]; then
            pid=`cat "$PID_FILE"`
            if [[ "X$pid" != "X" ]]; then
                realpid=`$PS_EXE -p $pid | grep $pid | grep -v grep | awk '{print $1}' | tail -1`
                if [[ "$pid" != "$realpid" ]]; then
                    echo "delete $PID_FILE because $pid does not exist."
                    rm -f "$PID_FILE"
                    pid=""
                fi
            fi
        else
            echo "cannot read $PID_FILE"
            exit 1
        fi
    fi
}

start()
{
    echo "" > $SERVER_LOG
    echo "Starting $APP_NAME"
    getpid
    if [[ "X$pid" = "X" ]]; then
        # $PHP_BIN WorkerSvr_run.php ../config/workerConf_default.json
        nohup $PHP_BIN WorkerSvr_run.php ../config/workerConf_default.json >> ../log/workerConsole.log 2>&1 &
        newpid=$!
        echo "Start $APP_NAME at $DATE,which PID $newpid"
        echo "$newpid" > "$PID_FILE"
    else
        echo "$APP_NAME is already running."
        exit 1
    fi
}

stop()
{
    echo "Stopping $APP_NAME"
    getpid
    if [[ "X$pid" = "X" ]]; then
        echo "$APP_NAME was not running"
    else
        kill -15 $pid
        sleep 2
        getpid
        if [[ "X$pid" != "X" ]]; then
            echo "Successfully stop $APP_NAME"
            exit 0
        else
            echo "Unable to stop $APP_NAME"
        fi
    fi
}

restart()
{
    stop
    sleep 5
    start
}
case $1 in
    "start")
    start
    ;;
    "stop")
    stop
    ;;
    "restart")
    restart
    ;;
esac