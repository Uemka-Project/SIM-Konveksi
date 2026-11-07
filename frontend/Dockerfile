# syntax=docker/dockerfile:1.7

# Stage 1: build bundle Vite.
FROM node:22-alpine AS build

WORKDIR /app

ARG VITE_API_BASE_URL=/api/v1
ARG APP_VERSION=0.0.0
ARG GIT_SHA=unknown

ENV VITE_API_BASE_URL=${VITE_API_BASE_URL} \
    VITE_APP_VERSION=${APP_VERSION} \
    VITE_GIT_SHA=${GIT_SHA}

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Stage 2: serve static assets dengan nginx.
FROM nginx:1.27-alpine AS runtime

ARG APP_VERSION=0.0.0
ARG GIT_SHA=unknown

LABEL org.opencontainers.image.title="konveksi-frontend" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${GIT_SHA}"

# Nama container backend di Docker network. Nilai ini di-override oleh
# workflow deploy (-e BACKEND_UPSTREAM=...:8000).
ENV BACKEND_UPSTREAM=konveksi-backend:8000

# Batasi envsubst hanya ke variabel BACKEND_* supaya variabel runtime nginx
# ($host, $remote_addr, $scheme, ...) tidak ikut di-substitute.
ENV NGINX_ENVSUBST_FILTER="^BACKEND_"

COPY templates/ /etc/nginx/templates/
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
