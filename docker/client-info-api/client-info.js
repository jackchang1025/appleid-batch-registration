const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

// 从原始X-Apple-I-FD-Client-Info.js读取内容
const originalScriptPath = path.join(__dirname, 'X-Apple-I-FD-Client-Info.js');
let appleScriptContent;

try {
  appleScriptContent = fs.readFileSync(originalScriptPath, 'utf8');
} catch (error) {
  console.error('无法读取原始X-Apple-I-FD-Client-Info.js文件:', error);
  // 如果无法读取原始文件，将使用我们的自定义实现
}

// 创建更精确的DOM环境来模拟浏览器
function createDOMEnvironment(options = {}) {
  const { userAgent, language, timeZone } = options;
  
  // 默认值
  const defaultUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15';
  const defaultLanguage = 'en-US';
  const defaultTimeZone = 'America/Los_Angeles';

  // 创建DOM环境
  const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'https://appleid.apple.com/',
    referrer: 'https://appleid.apple.com/',
    contentType: 'text/html',
    userAgent: userAgent || defaultUserAgent,
    includeNodeLocations: true,
    runScripts: 'dangerously', // 允许脚本运行，谨慎使用
  });

  const { window } = dom;
  const { document, navigator } = window;
  
  // 更完整地模拟navigator对象
  Object.defineProperties(navigator, {
    'language': {
      value: language || defaultLanguage,
      writable: false
    },
    'timeZone': {
      value: timeZone || defaultTimeZone,
      writable: false
    },
    'product': {
      value: 'Gecko',
      writable: false
    },
    'productSub': {
      value: '20030107',
      writable: false
    },
    'appCodeName': {
      value: 'Mozilla',
      writable: false
    },
    'plugins': {
      value: [],
      writable: false
    }
  });

  // 模拟时区偏移
  const originalGetTimezoneOffset = Date.prototype.getTimezoneOffset;
  Date.prototype.getTimezoneOffset = function() {
    if (timeZone) {
      // 基于传入的时区计算偏移，这里简化处理
      // 实际应用中可能需要更复杂的时区处理库
      try {
        if (timeZone.startsWith('GMT')) {
          const match = timeZone.match(/GMT([+-])(\d{2}):(\d{2})/);
          if (match) {
            const sign = match[1] === '-' ? 1 : -1;
            const hours = parseInt(match[2], 10);
            const minutes = parseInt(match[3], 10);
            return sign * (hours * 60 + minutes);
          }
        }
      } catch (e) {
        console.error('时区解析错误:', e);
      }
    }
    
    // 默认返回原始方法结果
    return originalGetTimezoneOffset.call(this);
  };

  return { window, document };
}

// 直接使用原始脚本生成客户端ID
function generateClientInfoFromOriginalScript(options = {}) {
  if (!appleScriptContent) {
    return fallbackClientInfoGeneration(options);
  }

  try {
    const { window } = createDOMEnvironment(options);
    
    // 在window环境中执行原始脚本
    const scriptResult = window.eval(`
      ${appleScriptContent}
      // 封装关键函数，使其可用
      (function() {
        try {
          return getClientId();
        } catch (e) {
          return { error: e.message, stack: e.stack };
        }
      })();
    `);
    
    // 关闭DOM环境以释放资源
    window.close();
    
    if (scriptResult && scriptResult.error) {
      console.error('执行原始脚本时出错:', scriptResult.error, scriptResult.stack);
      return fallbackClientInfoGeneration(options);
    }
    
    return scriptResult;
  } catch (error) {
    console.error('使用原始脚本生成客户端ID时出错:', error);
    return fallbackClientInfoGeneration(options);
  }
}

// 备用的客户端ID生成方法，当原始脚本无法使用时
function fallbackClientInfoGeneration(options = {}) {
  const { userAgent, language, timeZone } = options;
  const { window } = createDOMEnvironment(options);
  
  // 简化版的get_data函数
  function get_data(clientId) {
    const data = {
      U: userAgent || window.navigator.userAgent,
      L: language || window.navigator.language || '',
      Z: timeZone || (() => {
        try {
          const offset = new Date().getTimezoneOffset();
          const hours = Math.abs(Math.floor(offset / 60));
          const minutes = Math.abs(offset % 60);
          return `GMT${offset <= 0 ? '+' : '-'}${hours < 10 ? '0' + hours : hours}:${minutes < 10 ? '0' + minutes : minutes}`;
        } catch (e) {
          return 'GMT+00:00';
        }
      })(),
      V: '1.1'
    };
    
    if (clientId) {
      data.F = clientId;
    }
    
    return JSON.stringify(data);
  }
  
  // 简化版的r函数，生成客户端ID
  function generateClientId() {
    const timestamp = new Date().getTime();
    const random = Math.random().toString(36).substring(2, 7);
    return timestamp.toString(36) + random;
  }
  
  const clientId = generateClientId();
  const fullData = get_data(clientId);
  
  // 关闭DOM环境以释放资源
  window.close();
  
  return { clientId, fullData };
}

// 主生成函数，先尝试使用原始脚本，失败则使用备用方法
function generateClientInfo(options = {}) {
  if (appleScriptContent) {
    return generateClientInfoFromOriginalScript(options);
  } else {
    return fallbackClientInfoGeneration(options);
  }
}

module.exports = { generateClientInfo }; 