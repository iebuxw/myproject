FROM golang:1.18-alpine AS builder

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories

WORKDIR /app

COPY app/ .

RUN GOPROXY=https://goproxy.cn,direct go mod download \
    && CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /app/server main.go

FROM alpine:3.16

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
    && apk add --no-cache ca-certificates tzdata \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

WORKDIR /app

COPY --from=builder /app/server .

EXPOSE 8080

CMD ["./server"]
