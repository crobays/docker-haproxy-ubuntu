## Run HAproxy in a container on top of ubuntu:trusty

	docker build \
		 --tag crobays/haproxy-ubuntu \
		 .

# Create webservers with [crobays/nginx-php](https://github.com/crobays/docker-nginx-php)
	
	docker run \
		-v ./:/project \
		-e PUBLIC_PATH=/project/public \
		-e TIMEZONE=Etc/UTC \
		-e DOMAIN=your-domain.com \
		--name web1 \
		-it --rm \
		crobays/nginx-php

	docker run \
		-v ./:/project \
		-e PUBLIC_PATH=/project/public \
		-e TIMEZONE=Etc/UTC \
		-e DOMAIN=your-other-domain.com \
		--name web2 \
		-it --rm \
		crobays/nginx-php

# Run the Load Balancer

	docker run \
		--link web1:backend \
		--link web2:backend \
		-v ./:/project \
		-it --rm \
		crobays/haproxy-ubuntu
