FROM sail-8.4/app

# 安装supervisor
RUN apt-get update && apt-get install -y supervisor

# 创建supervisor日志目录
RUN mkdir -p /var/log/supervisor

# 确保entrypoint脚本可执行
ENTRYPOINT ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/horizon.conf"] 