# Build a Document Server image with open-license Office-compatible fonts plus
# the user-provided Microsoft fonts stored in docker/onlyoffice/fonts/.
FROM onlyoffice/documentserver:latest

USER root

# User-provided font files. Confirm their server installation rights before deployment.
COPY docker/onlyoffice/fonts/ /usr/share/fonts/truetype/custom/

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      fonts-croscore \
      fonts-crosextra-caladea \
      fonts-crosextra-carlito \
      fonts-dejavu \
      fonts-liberation \
      fonts-noto-core \
      fonts-noto-cjk \
      fonts-noto-extra \
      fonts-urw-base35 \
 && rm -rf /var/lib/apt/lists/* \
 && fc-cache -f \
 && /usr/bin/documentserver-generate-allfonts.sh

# Keep the upstream entrypoint and service configuration.
