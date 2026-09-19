ARG CADDY_VERSION="2.11"
ARG FLAG_EXTRA="false"
ARG FLAG_LOCAL="false"
ARG FLAG_DEBUG="false"

FROM ghcr.io/fukumen/rep2-base:latest AS rep2-false-false-false
FROM ghcr.io/fukumen/rep2-base-extra:latest AS rep2-true-false-false
FROM rep2-base:latest AS rep2-false-true-false
FROM rep2-base-dbg:latest AS rep2-false-true-true
FROM rep2-base-extra:latest AS rep2-true-true-false
FROM rep2-base-extra-dbg:latest AS rep2-true-true-true

FROM caddy:${CADDY_VERSION}-alpine AS caddy-upstream

FROM rep2-${FLAG_EXTRA}-${FLAG_LOCAL}-${FLAG_DEBUG} AS builder

COPY --from=p2-rep2 . /var/www
WORKDIR /var/www

RUN /tmp/composer.phar install
RUN rm -rf `find . -name '.git*' -o -name 'composer.*'`

COPY patch /tmp
RUN php /tmp/apply_settings.php /tmp/settings.txt .

RUN mv conf conf.orig && ln -s /ext/conf conf
RUN mv data data.orig && ln -s /ext/data data
RUN ln -s /ext/rep2/ic rep2/ic
RUN ln -s /ext/user_skin rep2/user_skin

FROM rep2-${FLAG_EXTRA}-${FLAG_LOCAL}-${FLAG_DEBUG}
# Based on the original work by Abe Masahiro <pen@thcomp.org>
LABEL org.opencontainers.image.authors="Fukumen <fukumen@gmail.com>" \
    org.opencontainers.image.source="https://github.com/fukumen/docker-rep2"
ARG FLAG_DEBUG

ARG REPO_HASH
ARG REPO_LOG
ARG REP2_HASH
ARG REP2_LOG
ARG RUN_ID
ARG RUN_NUMBER
ENV VER_REPO_HASH=$REPO_HASH
ENV VER_REPO_LOG=$REPO_LOG
ENV VER_REP2_HASH=$REP2_HASH
ENV VER_REP2_LOG=$REP2_LOG
ENV VER_RUN_ID=$RUN_ID
ENV VER_RUN_NUMBER=$RUN_NUMBER

COPY --from=caddy-upstream /usr/bin/caddy /usr/bin/caddy



RUN apk del git patch \
    && rm -f /tmp/composer.phar \
    && rm -rf /var/cache/apk/* /var/log/apk.log /tmp/pear

COPY --from=builder /var/www   /var/www
COPY rootfs /

VOLUME /ext
EXPOSE 443

CMD ["/etc/rc.entry"]
