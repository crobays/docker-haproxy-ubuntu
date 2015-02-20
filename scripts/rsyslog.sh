#!/bin/bash
if [ -f "/etc/rsyslog.d/49-haproxy.conf" ];then
	rm /etc/rsyslog.d/49-haproxy.conf
fi
ln -sf /conf/rsyslog-haproxy.conf /etc/rsyslog.d/49-haproxy.conf
restart rsyslog
