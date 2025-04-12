FROM sail-8.4/app

# 安装必要的软件包
RUN apt-get update && apt-get install -y \
    supervisor \
    cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 创建supervisor日志目录
RUN mkdir -p /var/log/supervisor

# 启动supervisor
ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"] 