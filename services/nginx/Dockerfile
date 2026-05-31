# 阶段1: 构建 Vue 前端
FROM node:14-alpine AS frontend-builder

RUN sed -i 's/dl-cdn-alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./

RUN npm config set registry https://registry.npmmirror.com \
    && npm install

COPY frontend/ .

RUN npm run build

# 阶段2: 最终 Nginx 镜像
FROM nginx:1.24-alpine

COPY --from=frontend-builder /app/dist /usr/share/nginx/html
# Nginx 配置
COPY services/nginx/conf/nginx.conf /etc/nginx/nginx.conf
COPY services/nginx/conf/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
