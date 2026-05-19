FROM nginx:1.29.7-alpine AS build

RUN apk add --no-cache openssl && \
	# https://tools.ietf.org/html/rfc7919
	# https://github.com/mozilla/ssl-config-generator/blob/master/docs/ffdhe2048.txt
	wget -q https://ssl-config.mozilla.org/ffdhe2048.txt -O /etc/ssl/dhparam.pem

FROM nginx:1.29.7-alpine

COPY --from=build /etc/ssl/dhparam.pem /etc/ssl/dhparam.pem
COPY ./conf/http.conf.template /etc/nginx/conf-available/http.conf.template
COPY ./conf/ssl.conf.template /etc/nginx/conf-available/ssl.conf.template
COPY ./docker-entrypoint.d/10-select-template.sh /docker-entrypoint.d/10-select-template.sh

RUN chmod +x /docker-entrypoint.d/10-select-template.sh
