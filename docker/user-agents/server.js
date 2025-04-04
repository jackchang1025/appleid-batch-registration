const express = require('express');
const UserAgent = require('user-agents');
const cors = require('cors');

const app = express();
const port = process.env.PORT || 3000;

// 启用 CORS
app.use(cors());
// 解析 JSON 请求体
app.use(express.json());

// 支持的直接过滤字段 (对应 user-agents data 结构)
// 注意：browserName, osName, browserVersion, osVersion 需要特殊处理
const SUPPORTED_DIRECT_FILTERS = [
  'deviceCategory', 'platform', 'screenWidth', 'screenHeight',
  'viewportWidth', 'viewportHeight', 'vendor'
  // 'connection.type' 也可以支持，但需要处理嵌套结构
];

// 支持的特殊处理过滤器
const SUPPORTED_SPECIAL_FILTERS = [
  'browserName', 'osName', 'browserVersion', 'osVersion', 'connectionType' // 添加 connectionType 作为扁平化处理
];

// 支持的浏览器 (用于文档和潜在的未来验证)
const SUPPORTED_BROWSERS = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'];
// 支持的操作系统 (用于文档和潜在的未来验证)
const SUPPORTED_OS = ['Windows', 'Mac', 'Linux', 'Android', 'iOS'];
// 支持的设备类型
const SUPPORTED_DEVICE_CATEGORIES = ['desktop', 'mobile', 'tablet'];


// 健康检查端点
app.get('/health', (req, res) => {
  console.log(`[${new Date().toISOString()}] 健康检查请求`);
  res.status(200).send('OK');
});

// API 文档
app.get('/', (req, res) => {
  console.log(`[${new Date().toISOString()}] 访问 API 文档`);
  res.json({
    name: 'User-Agents API',
    version: '1.1.0', // 版本更新
    endpoints: [
      {
        path: '/api/user-agent',
        method: 'POST',
        description: '获取随机 User-Agent',
        body: {
          count: '返回的 User-Agent 数量 (默认: 1, 最大: 100)',
          filters: `筛选条件对象 (支持的键: ${[...SUPPORTED_DIRECT_FILTERS, ...SUPPORTED_SPECIAL_FILTERS].join(', ')})`,
          fullData: '是否返回完整数据 (默认: false)'
        }
      },
      {
        path: '/api/browsers',
        method: 'GET',
        description: '获取支持的浏览器列表'
      },
      {
        path: '/api/os',
        method: 'GET',
        description: '获取支持的操作系统列表'
      }
    ]
  });
});

// 获取支持的浏览器列表
app.get('/api/browsers', (req, res) => {
  console.log(`[${new Date().toISOString()}] 获取浏览器列表`);
  res.json({ browsers: SUPPORTED_BROWSERS });
});

// 获取支持的操作系统列表
app.get('/api/os', (req, res) => {
  console.log(`[${new Date().toISOString()}] 获取操作系统列表`);
  res.json({ os: SUPPORTED_OS });
});

// 统一的 POST 请求处理端点
app.post('/api/user-agent', (req, res) => {
  const requestId = Date.now().toString(36) + Math.random().toString(36).substr(2);
  const startTime = process.hrtime();

  console.log(`[${new Date().toISOString()}] [${requestId}] 收到 User-Agent 请求`);
  console.log(`[${requestId}] 请求体: ${JSON.stringify(req.body)}`);

  try {
    // 解析请求参数
    let { count = 1, filters: requestFilters = {}, fullData = false } = req.body;

    // 处理 filters 对象
    const processedFilters = processFilters(requestFilters, requestId);
    console.log(`[${requestId}] 处理后的过滤器数量: ${processedFilters.length}`);

    // 限制 count 上限
    const safeCount = Math.min(Math.max(1, parseInt(count) || 1), 100);
    if (safeCount !== count) {
      console.warn(`[${requestId}] 调整请求数量从 ${count} 到 ${safeCount}`);
    }

    console.log(`[${requestId}] 开始生成 User-Agent, 数量: ${safeCount}, 完整数据: ${fullData}`);

    // 生成用户代理
    const result = generateUserAgents(processedFilters, safeCount, fullData, requestId);

    // 计算处理时间
    const endTime = process.hrtime(startTime);
    const duration = (endTime[0] * 1000 + endTime[1] / 1000000).toFixed(2);

    console.log(`[${requestId}] 处理完成，耗时: ${duration}ms`);

    // 返回结果
    res.json(result);
  } catch (error) {
    const endTime = process.hrtime(startTime);
    const duration = (endTime[0] * 1000 + endTime[1] / 1000000).toFixed(2);

    console.error(`[${requestId}] 处理请求出错，耗时: ${duration}ms, 错误: ${error.message}`);
    console.error(`[${requestId}] 错误堆栈:`, error.stack); // 输出完整堆栈

    res.status(400).json({
      error: `处理请求失败: ${error.message}`, // 更具体的错误信息
      requestId: requestId
    });
  }
});

// 处理过滤条件，转换为 user-agents 库可用的格式
function processFilters(filters, requestId) {
  const resultFilters = [];
  const directFilterObject = {};

  // 1. 处理可以直接映射到 data 对象的过滤器
  SUPPORTED_DIRECT_FILTERS.forEach(key => {
    if (filters[key] !== undefined) {
      console.log(`[${requestId}] 添加直接过滤器: ${key} = ${filters[key]}`);
      directFilterObject[key] = filters[key];
    }
  });

  if (Object.keys(directFilterObject).length > 0) {
    resultFilters.push(directFilterObject);
  }

  // 2. 处理需要特殊逻辑的过滤器 (browserName, osName, versions, connectionType)

  // 处理 connectionType (扁平化)
  if (filters.connectionType) {
      console.log(`[${requestId}] 添加 connectionType 过滤器: ${filters.connectionType}`);
      // 注意: user-agents 库期望的是嵌套结构 { connection: { type: '...' } }
      // 这里我们用自定义函数模拟简单匹配
      resultFilters.push((data) => data.connection && data.connection.type === filters.connectionType);
  }

  // 处理浏览器名称
  if (filters.browserName) {
    console.log(`[${requestId}] 添加浏览器名称过滤器: ${filters.browserName}`);
    const browserNameLower = filters.browserName.toLowerCase();

    if (browserNameLower === 'chrome') {
      resultFilters.push(/Chrome/i);
      resultFilters.push((data) => !/Edg\//i.test(data.userAgent)); // Exclude Edge
      resultFilters.push((data) => !/OPR\//i.test(data.userAgent)); // Exclude Opera
      resultFilters.push((data) => !/SamsungBrowser\//i.test(data.userAgent)); // Exclude Samsung Browser
    } else if (browserNameLower === 'firefox') {
      resultFilters.push(/Firefox/i);
    } else if (browserNameLower === 'safari') {
      resultFilters.push(/Safari/i);
      resultFilters.push((data) => !/Chrome\//i.test(data.userAgent)); // Exclude Chrome/Chromium
       resultFilters.push((data) => !/Edg\//i.test(data.userAgent)); // Also exclude Edge which might have Safari token
    } else if (browserNameLower === 'edge') {
      resultFilters.push(/Edg\//i); // Matches modern Edge
    } else if (browserNameLower === 'opera') {
      resultFilters.push(/OPR\//i); // Matches modern Opera
    } else {
      // 通用情况，使用包含检查，更健壮
       console.warn(`[${requestId}] 使用通用匹配模式处理浏览器: ${filters.browserName}`);
      resultFilters.push((data) => data.userAgent.toLowerCase().includes(browserNameLower));
    }
  }

  // 处理操作系统名称
  if (filters.osName) {
    console.log(`[${requestId}] 添加操作系统过滤器: ${filters.osName}`);
    const osNameLower = filters.osName.toLowerCase();

    resultFilters.push((data) => {
      const uaLower = data.userAgent.toLowerCase();
      let matches = false;
      switch (osNameLower) {
        case 'windows':
          matches = uaLower.includes('windows') || data.platform?.toLowerCase().startsWith('win');
          break;
        case 'mac':
        case 'macos':
          matches = uaLower.includes('macintosh') || uaLower.includes('mac os x') || data.platform?.toLowerCase().startsWith('mac');
          break;
        case 'linux':
          // Exclude Android which is also Linux-based
          matches = (uaLower.includes('linux') || data.platform?.toLowerCase().includes('linux')) && !uaLower.includes('android');
          break;
        case 'android':
          matches = uaLower.includes('android');
          break;
        case 'ios':
          matches = uaLower.includes('iphone') || uaLower.includes('ipad') || uaLower.includes('ipod') || data.platform === 'iPhone' || data.platform === 'iPad' || data.platform === 'iPod';
          break;
        default:
          console.warn(`[${requestId}] 未知操作系统名称: ${filters.osName}, 使用通用包含匹配`);
          matches = uaLower.includes(osNameLower);
      }
      // console.log(`[${requestId}] OS Filter (${osNameLower}): UA='${uaLower.substring(0,30)}...' Platform='${data.platform}' -> ${matches}`);
      return matches;
    });
  }

  // 处理浏览器版本 (需要 browserName 才能精确匹配)
  if (filters.browserVersion && filters.browserName) {
    console.log(`[${requestId}] 添加浏览器版本过滤器: ${filters.browserName} ${filters.browserVersion}`);
    const version = filters.browserVersion; // 可能需要处理 '120' vs '120.0' 等情况
    const browserNameLower = filters.browserName.toLowerCase();

    resultFilters.push((data) => {
      const ua = data.userAgent;
      let regex;
      switch(browserNameLower) {
          case 'chrome': regex = new RegExp(`Chrome\\/${version.split('.')[0]}\\.`); break; // Match major version
          case 'firefox': regex = new RegExp(`Firefox\\/${version.split('.')[0]}\\.`); break; // Match major version
          case 'safari': regex = new RegExp(`Version\\/${version.split('.')[0]}\\..*Safari\\/`); break; // Match major version
          case 'edge': regex = new RegExp(`Edg\\/${version.split('.')[0]}\\.`); break; // Match major version
          case 'opera': regex = new RegExp(`OPR\\/${version.split('.')[0]}\\.`); break; // Match major version
          default: regex = new RegExp(`${filters.browserName}\\/${version}`, 'i'); // Fallback
      }
      const matches = regex.test(ua);
      // console.log(`[${requestId}] Browser Version Filter (${browserNameLower} ${version}): UA='${ua.substring(0,50)}...' -> ${matches}`);
      return matches;
    });
  } else if (filters.browserVersion) {
       console.warn(`[${requestId}] 提供了 browserVersion (${filters.browserVersion}) 但未提供 browserName，无法精确过滤版本。`);
  }

   // 处理操作系统版本 (需要 osName 才能精确匹配)
  if (filters.osVersion && filters.osName) {
    console.log(`[${requestId}] 添加操作系统版本过滤器: ${filters.osName} ${filters.osVersion}`);
    const version = filters.osVersion;
    const osNameLower = filters.osName.toLowerCase();

    resultFilters.push((data) => {
        const ua = data.userAgent;
        let regex;
        switch(osNameLower) {
            case 'windows':
                // Windows NT 6.1 -> Win 7, 6.2 -> 8, 6.3 -> 8.1, 10.0 -> 10/11
                const ntVersion = version === '7' ? '6\\.1' : version === '8' ? '6\\.2' : version === '8.1' ? '6\\.3' : version === '10' || version === '11' ? '10\\.0' : version;
                regex = new RegExp(`Windows NT ${ntVersion}`);
                break;
            case 'mac':
            case 'macos':
                const macVersion = version.replace(/\./g, '_');
                regex = new RegExp(`Mac OS X ${macVersion}`);
                break;
            case 'android':
                regex = new RegExp(`Android ${version.split('.')[0]}`); // Match major version
                break;
            case 'ios':
                 const iosVersion = version.replace(/\./g, '_');
                 regex = new RegExp(`OS ${iosVersion}.* like Mac OS X`);
                 break;
            default:
                 regex = new RegExp(`${filters.osName}.*${version}`, 'i'); // Fallback
        }
        const matches = regex.test(ua);
        // console.log(`[${requestId}] OS Version Filter (${osNameLower} ${version}): UA='${ua.substring(0,50)}...' -> ${matches}`);
        return matches;
    });
  } else if (filters.osVersion) {
       console.warn(`[${requestId}] 提供了 osVersion (${filters.osVersion}) 但未提供 osName，无法精确过滤版本。`);
  }


  return resultFilters;
}

// 生成符合条件的用户代理
function generateUserAgents(filters, count, fullData, requestId) {
  console.log(`[${requestId}] 开始生成用户代理，有效过滤器数量: ${filters.length}, 请求数量: ${count}`);

  let userAgentGenerator;
  try {
    // 如果 filters 为空，创建默认生成器，否则使用处理后的过滤器
    userAgentGenerator = filters.length > 0 ? new UserAgent(filters) : new UserAgent();
    console.log(`[${requestId}] 成功初始化 UserAgent 生成器`);
  } catch (error) {
    console.error(`[${requestId}] 初始化 UserAgent 生成器失败: ${error.message}`);
    // 检查是否是因为过滤器冲突导致无法找到匹配项
    if (error.message.includes("Could not find any user agents that match the specified filters")) {
         throw new Error(`无法找到满足所有指定过滤条件的 User Agent。请尝试放宽条件。Filters: ${JSON.stringify(filters)}`);
    } else {
         throw new Error(`创建 UserAgent 生成器时出错: ${error.message}`);
    }
  }

  // 根据 count 生成结果
  if (count === 1) {
    let uaInstance;
     try {
         uaInstance = userAgentGenerator(); // 调用实例获取随机 UA
     } catch (e) {
         console.error(`[${requestId}] 生成单个 UA 时出错 (可能过滤器无匹配): ${e.message}`);
         throw new Error(`生成 User Agent 时出错，可能无匹配项: ${e.message}`);
     }
    const uaString = uaInstance.toString();
    console.log(`[${requestId}] 生成单个 UA: ${uaString.substring(0, 70)}...`);
    return fullData ? uaInstance.data : { userAgent: uaString };
  } else {
    const results = [];
    console.log(`[${requestId}] 准备生成 ${count} 个 UA...`);
    let generatedCount = 0;
    let attemptCount = 0;
    const maxAttempts = count * 3; // 允许一些失败尝试

    while (generatedCount < count && attemptCount < maxAttempts) {
       attemptCount++;
      try {
        const uaInstance = userAgentGenerator(); // 高效生成
        const uaString = uaInstance.toString();
        // console.log(`[${requestId}] 生成 UA #${generatedCount + 1}: ${uaString.substring(0, 70)}...`);
        results.push(fullData ? uaInstance.data : { userAgent: uaString });
        generatedCount++;
      } catch (error) {
        // 如果是因为找不到匹配项而重复失败，可能需要停止
        if (error.message.includes("Could not find any user agents that match")) {
             console.error(`[${requestId}] 连续生成失败，可能过滤器无匹配项: ${error.message}`);
             if (results.length === 0) { // 如果一个都没生成成功
                throw new Error(`无法生成满足条件的 User Agent: ${error.message}`);
             }
             break; // 如果已经生成了一些，就返回已有的
        }
        console.warn(`[${requestId}] 生成 UA #${generatedCount + 1} 时出现临时错误 (尝试 ${attemptCount}/${maxAttempts}): ${error.message}`);
        // 少量错误可以接受，继续尝试
      }
    }
     if (generatedCount < count) {
         console.warn(`[${requestId}] 请求生成 ${count} 个 UA，但只成功生成了 ${generatedCount} 个 (尝试 ${attemptCount} 次)。`);
     } else {
         console.log(`[${requestId}] 成功生成 ${generatedCount} 个 UA。`);
     }

    return {
      count: results.length,
      results: results
    };
  }
}

// 启动服务器
app.listen(port, () => {
  console.log(`[${new Date().toISOString()}] User-Agents API 服务运行在 http://localhost:${port}`);
});
