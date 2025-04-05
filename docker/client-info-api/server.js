const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const app = express();
const PORT = process.env.PORT || 3000;

// 读取X-Apple-I-FD-Client-Info.js文件
const appleScriptPath = path.join(__dirname, 'X-Apple-I-FD-Client-Info.js');
let appleScriptContent;

try {
  appleScriptContent = fs.readFileSync(appleScriptPath, 'utf8');
} catch (error) {
  console.error('无法读取X-Apple-I-FD-Client-Info.js文件:', error);
  process.exit(1); // 如果无法读取关键文件，则退出进程
}

// 创建DOM环境并执行X-Apple-I-FD-Client-Info.js
function generateClientInfo(options = {}) {
  const { userAgent, language, timeZone } = options;
  
  // 默认值
  const defaultUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15';
  const defaultLanguage = 'en-US';
  const defaultTimeZone = 'GMT+08:00';
  
  // 创建DOM环境
  const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'https://appleid.apple.com/',
    referrer: 'https://appleid.apple.com/',
    contentType: 'text/html',
    userAgent: userAgent || defaultUserAgent,
    includeNodeLocations: true,
    runScripts: 'dangerously'
  });

  const { window } = dom;
  
  // 在window上设置临时变量，以便脚本可以访问
  window.__CLIENT_INFO_CONFIG__ = {
    userAgent: userAgent || defaultUserAgent,
    language: language || defaultLanguage,
    timeZone: timeZone || defaultTimeZone
  };

  // 修改导入的脚本内容，插入对自定义配置的使用
  const modifiedScript = `
    // 使用传入的配置来覆盖环境变量
    if (window.__CLIENT_INFO_CONFIG__) {
      navigator.__defineGetter__('userAgent', function() { 
        return window.__CLIENT_INFO_CONFIG__.userAgent; 
      });
      navigator.__defineGetter__('language', function() { 
        return window.__CLIENT_INFO_CONFIG__.language; 
      });
      navigator.__defineGetter__('timeZone', function() { 
        return window.__CLIENT_INFO_CONFIG__.timeZone; 
      });
    }
    
    // 原始脚本
    ${appleScriptContent}
  `;
  
  try {
    // 执行修改后的脚本并获取结果
    const result = window.eval(`
      ${modifiedScript}
      getClientId();
    `);
    
    // 清理并关闭DOM环境
    delete window.__CLIENT_INFO_CONFIG__;
    window.close();
    
    return result;
  } catch (error) {
    console.error('执行X-Apple-I-FD-Client-Info.js脚本时出错:', error);
    
    // 关闭DOM环境并抛出错误
    window.close();
    throw error;
  }
}

// 启用CORS和JSON请求体解析
app.use(cors());
app.use(express.json());

// 健康检查端点
app.get('/health', (req, res) => {
  res.status(200).json({ status: 'ok', message: 'Service is healthy' });
});

// 主要API端点，用于生成客户端信息
app.post('/api/client-info', (req, res) => {
  try {
    const { userAgent, language, timeZone } = req.body;
    
    console.log('收到请求参数:', {
      userAgent: userAgent || '未提供',
      language: language || '未提供',
      timeZone: timeZone || '未提供'
    });
    
    const clientInfo = generateClientInfo({
      userAgent,
      language,
      timeZone
    });
    
    console.log('生成的客户端信息:', clientInfo);
    
    res.status(200).json(clientInfo);
  } catch (error) {
    console.error('生成客户端信息时出错:', error);
    res.status(500).json({
      error: '生成客户端信息时出错',
      message: error.message
    });
  }
});

// 添加GET端点，方便测试
app.get('/api/client-info', (req, res) => {
  try {
    const userAgent = req.query.userAgent || req.headers['user-agent'];
    const language = req.query.language || req.headers['accept-language']?.split(',')[0];
    const timeZone = req.query.timeZone;
    
    console.log('GET请求参数:', {
      userAgent: userAgent || '未提供',
      language: language || '未提供',
      timeZone: timeZone || '未提供'
    });
    
    const clientInfo = generateClientInfo({
      userAgent,
      language,
      timeZone
    });
    
    console.log('生成的客户端信息:', clientInfo);
    
    res.status(200).json(clientInfo);
  } catch (error) {
    console.error('生成客户端信息时出错:', error);
    res.status(500).json({
      error: '生成客户端信息时出错',
      message: error.message
    });
  }
});

// 启动服务器
app.listen(PORT, '0.0.0.0', () => {
  console.log(`客户端信息API服务运行在端口 ${PORT}`);
}); 