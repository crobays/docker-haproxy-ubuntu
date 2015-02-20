FROM ubuntu:trusty
ENV HOME /root
# RUN /etc/my_init.d/00_regen_ssh_host_keys.sh
CMD ["/sbin/my_init"]

MAINTAINER Crobays <crobays@userex.nl>
ENV DOCKER_NAME haproxy
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update
RUN apt-get -y dist-upgrade

RUN apt-get install -y software-properties-common
RUN add-apt-repository ppa:vbernat/haproxy-1.5
RUN apt-get update

RUN apt-get install -y \
					haproxy \
					curl \
					php5-cli

# Exposed ENV
ENV TIMEZONE Etc/UTC
ENV ENVIRONMENT prod
ENV ENABLE_FORWARD_FOR TRUE

# RUN curl --output /usr/local/bin/conf --url https://github.com/kelseyhightower/confd/releases/download/v0.6.2/confd-0.6.2-linux-amd64 --location
# RUN chmod +x /usr/local/bin/conf

VOLUME  ["/project"]

# HTTP ports
EXPOSE 80 443

RUN echo '/sbin/my_init' > /root/.bash_history

ADD /conf /conf

RUN mkdir -p /etc/service/haproxy && echo "#!/bin/bash\nhaproxy -f /conf/haproxy-output.cfg" > /etc/service/haproxy/run

RUN mkdir -p /etc/my_init.d && echo "#!/bin/bash\necho \"\$TIMEZONE\" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata" > /etc/my_init.d/01-timezone.sh
ADD /scripts/haproxy-config.php /etc/my_init.d/02-haproxy-config.php
ADD /scripts/my_init /sbin/my_init

RUN chmod +x /etc/my_init.d/* && chmod +x /etc/service/*/run && chmod +x /sbin/my_init

# Clean up APT when done.
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


# docker build \
# 	-t crobays/haproxy \
# 	$DOCKER/crobays/haproxy && \
# docker run \
# 	-p 81:80 \
# 	-p 8081:8080 \
#	-v /workspace/cluster/logs:/var/log \
# 	-d \
# 	crobays/haproxy

# docker build \
# 	-t crobays/haproxy \
# 	/workspace/docker/crobays/haproxy && \
# docker run \
#   -p 80:80 \
#   -p 443:443 \
#   -p 8081:8080 \
#   -v /workspace:/workspace \
#   -ti --rm \
#   crobays/haproxy bash


