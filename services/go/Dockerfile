# ---- 构建阶段 ----
FROM golang:1.18-alpine AS builder

RUN sed -i 's/dl-cdn-alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories

WORKDIR /build

COPY app/go.mod app/go.sum ./
RUN GOPROXY=https://goproxy.cn,direct go mod download

COPY app/ .
RUN GOPROXY=https://goproxy.cn,direct go install github.com/swaggo/swag/cmd/swag@v1.16.3 \
    && swag init -g main.go -o ./docs
RUN CGO_ENABLED=0 go build -ldflags="-s -w" -o server main.go

# ---- 运行阶段 ----
FROM alpine:3

RUN sed -i 's/dl-cdn-alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
    && apk add --no-cache ca-certificates tzdata \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

WORKDIR /app
COPY --from=builder /build/server .

EXPOSE 8080
CMD ["./server"]
