#!/bin/bash
root_path="/home/www/common_cron/alarm_admin";
if [ $# -gt 0 ]
then
	echo " Usage : ./monitor.sh"
	exit
fi
source $root_path/alarm_info.ini

active_ok=`$mysql_path -h$alarm_host -u$alarm_user -p$alarm_pwd $alarm_db -e "SELECT active_ok FROM svr_register WHERE pro_name = '$pro_name' AND svr_name = '$svr_name'"`
active_ok=`echo "$active_ok" | grep -E 'yes|no'`
if [ $active_ok = "no" ] || [ $active_ok = '' ]
then
	exit
fi

echo "pro_name : $pro_name, svr_name : $svr_name";
load_avg=`uptime | awk '{print $(NF-2)" "$(NF-1)" "$NF}'`
cpu=`/usr/bin/mpstat | tail -1 | awk '{print 100 - $12}'`
memory=`free | grep -i mem: | awk '{print $3/$2 * 100}'`
io_wait=`iostat -c|awk '/^ /{print $4}'`
disk=`df -h`
net_id=`netstat -i | awk '{print $1}' | grep -iv lo | grep -iv Iface | grep -iv Kernel`
for i in $net_id
do
	network_rx_error=`netstat -i | grep $i | awk '{print $5}'`
	network_tx_error=`netstat -i | grep $i | awk '{print $9}'`
done

START=$(date +%s)
if [ $svr_name == "db"* ] || [ $svr_name == "all" ]
then
#	echo "mysql -u$local_user -p'$local_pwd' $local_db < alarm_query.sql"
	mysql -u$local_user -p$local_pwd $local_db < alarm_query.sql
fi
END=$(date +%s)
query_sec=$(( $END - $START ))
http_cnt=`netstat -nap | grep :80 | grep ESTABLISHED | wc -l`
ping_sec=`ping -c 1 localhost | grep icmp_seq | awk '{print $8}'`
ping_sec=${ping_sec:5:10};

db_live_num=`ps -ef | grep mysql | egrep -v grep | wc -l`;
http_live_num=`ps -ef | grep httpd | egrep -v grep | wc -l`;

db_live_ok='yes';
if [ $db_live_num -lt 1 ]
then
	if [ $svr_name == "db"* ] || [ $svr_name == "all" ]
	then
		db_live_ok='no';	
	fi
fi

http_live_ok='yes';
if [ $http_live_num -lt 1 ]
then
	if [ $svr_name == "web"* ] || [ $svr_name == "all" ]
	then
		http_live_ok='no';
	fi
fi

$mysql_path -h$alarm_host -u$alarm_user -p$alarm_pwd $alarm_db -e "INSERT INTO system_log (pro_name, svr_name, load_avg, cpu, memory, io_wait, disk, network_rx_error, network_tx_error, query_sec, http_cnt, ping_sec, db_live_ok, http_live_ok, ip, joindate) VALUES 
('$pro_name', '$svr_name', '$load_avg', '$cpu', '$memory', '$io_wait', '$disk', '$network_rx_error', '$network_tx_error', '$query_sec', '$http_cnt', '$ping_sec', '$db_live_ok', '$http_live_ok', '127.0.0.1', now())"
