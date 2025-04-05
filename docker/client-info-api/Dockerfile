FROM node:18-alpine

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

# 复制应用代码
COPY . .

# 暴露端口
EXPOSE 3000

# 启动应用
CMD ["node", "server.js"]
