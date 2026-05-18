FROM node:14-alpine AS builder

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm config set registry https://registry.npmmirror.com \
    && npm install

COPY . .

RUN npm run build

FROM nginx:1.24-alpine

COPY --from=builder /app/dist /usr/share/nginx/html

EXPOSE 80
