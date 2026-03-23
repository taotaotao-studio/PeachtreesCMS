# Node.js版本API架构设计

## 项目概述
为PeachtreesCMS项目创建Node.js版本的API后端，保持与现有PHP版本相同的功能特性和接口规范。

## 技术栈选择

### 核心框架
- **Web框架**: Express.js 4.x（稳定、生态丰富）
- **数据库**: mysql2 + Sequelize ORM
- **认证**: JWT + express-session
- **文件上传**: multer中间件
- **验证**: Joi或express-validator
- **安全**: helmet、cors、rate-limit
- **日志**: winston

### 开发工具
- **运行时**: Node.js 18+
- **包管理**: npm或yarn
- **开发工具**: nodemon、ESLint、Prettier
- **测试**: Jest + Supertest

## 目录结构设计

```
peachtrees-cms-nodejs/
├── src/
│   ├── controllers/          # 控制器层
│   │   ├── auth.js          # 认证控制器
│   │   ├── posts.js         # 文章控制器
│   │   ├── tags.js          # 标签控制器
│   │   ├── comments.js      # 评论控制器
│   │   ├── users.js         # 用户控制器
│   │   ├── themes.js        # 主题控制器
│   │   └── settings.js      # 设置控制器
│   ├── models/              # 数据模型层
│   │   ├── index.js         # 模型入口
│   │   ├── User.js          # 用户模型
│   │   ├── Post.js          # 文章模型
│   │   ├── Tag.js           # 标签模型
│   │   ├── Comment.js       # 评论模型
│   │   ├── Theme.js         # 主题模型
│   │   └── Setting.js       # 设置模型
│   ├── routes/              # 路由层
│   │   ├── index.js         # 路由入口
│   │   ├── auth.js          # 认证路由
│   │   ├── posts.js         # 文章路由
│   │   ├── tags.js          # 标签路由
│   │   ├── comments.js      # 评论路由
│   │   ├── users.js         # 用户路由
│   │   ├── themes.js        # 主题路由
│   │   └── settings.js      # 设置路由
│   ├── middleware/          # 中间件层
│   │   ├── auth.js          # 认证中间件
│   │   ├── validation.js    # 验证中间件
│   │   ├── upload.js        # 上传中间件
│   │   └── errorHandler.js  # 错误处理中间件
│   ├── services/            # 业务逻辑层
│   │   ├── authService.js   # 认证服务
│   │   ├── postService.js   # 文章服务
│   │   ├── tagService.js    # 标签服务
│   │   ├── commentService.js # 评论服务
│   │   ├── userService.js   # 用户服务
│   │   ├── themeService.js  # 主题服务
│   │   └── settingService.js # 设置服务
│   ├── utils/               # 工具函数
│   │   ├── jwt.js           # JWT工具
│   │   ├── password.js      # 密码工具
│   │   ├── captcha.js       # 验证码工具
│   │   ├── rss.js           # RSS生成工具
│   │   └── fileUpload.js    # 文件上传工具
│   ├── config/              # 配置文件
│   │   ├── database.js      # 数据库配置
│   │   ├── app.js           # 应用配置
│   │   └── env.js           # 环境配置
│   └── app.js               # 应用入口
├── uploads/                 # 文件上传目录
├── public/                  # 静态资源目录
├── tests/                   # 测试文件
├── docs/                    # 文档
├── .env                     # 环境变量
├── package.json
├── README.md
└── Dockerfile
```

## 核心功能模块设计

### 1. 认证管理模块
- **JWT认证**: 支持token刷新和过期机制
- **Session管理**: 可选session存储
- **权限验证**: 基于角色的访问控制
- **密码加密**: bcrypt加密存储

### 2. 文章管理模块
- **CRUD操作**: 完整的增删改查功能
- **文件上传**: 支持图片和附件上传
- **分页查询**: 支持多种排序和筛选
- **状态管理**: 草稿、发布、删除状态

### 3. 标签管理模块
- **标签关联**: 文章与标签多对多关系
- **标签统计**: 标签使用频率统计
- **批量操作**: 支持批量添加和删除

### 4. 评论管理模块
- **审核机制**: 支持评论审核流程
- **白名单管理**: 邮箱白名单功能
- **嵌套评论**: 支持评论回复
- **垃圾过滤**: 基础垃圾评论过滤

### 5. 用户管理模块
- **用户权限**: 多级用户权限管理
- **个人资料**: 用户信息维护
- **密码安全**: 密码修改和重置

### 6. 主题管理模块
- **主题切换**: 动态主题切换功能
- **布局配置**: 自定义页面布局
- **主题包管理**: 主题包上传和激活

### 7. 设置管理模块
- **系统配置**: 全局系统设置
- **动态配置**: 运行时配置更新
- **配置备份**: 配置导入导出

## API接口设计

### 响应格式统一
```javascript
{
  "code": 200,
  "message": "success",
  "data": {},
  "timestamp": "2025-03-11T10:30:00Z"
}
```

### 错误处理规范
```javascript
{
  "code": 400,
  "message": "参数错误",
  "error": "详细错误信息",
  "timestamp": "2025-03-11T10:30:00Z"
}
```

## 数据库设计

### 表结构保持与PHP版本一致
- users (用户表)
- posts (文章表)
- tags (标签表)
- post_tags (文章标签关联表)
- comments (评论表)
- themes (主题表)
- settings (设置表)

### Sequelize模型定义
使用Sequelize ORM定义数据模型，支持数据验证和关联关系。

## 安全设计

### 1. 输入验证
- 请求参数验证
- SQL注入防护
- XSS攻击防护

### 2. 认证安全
- JWT token安全
- 密码强度验证
- 会话管理安全

### 3. 文件安全
- 文件类型限制
- 文件大小限制
- 病毒扫描集成

## 性能优化

### 1. 数据库优化
- 连接池配置
- 查询优化
- 索引优化

### 2. 缓存策略
- Redis缓存集成
- 静态资源缓存
- 查询结果缓存

### 3. 代码优化
- 异步处理
- 内存管理
- 垃圾回收优化

## 部署方案

### 开发环境
- 使用nodemon热重载
- 环境变量配置
- 开发调试工具

### 生产环境
- Docker容器化部署
- PM2进程管理
- Nginx反向代理
- 负载均衡配置

## 开发计划

### 第一阶段：基础框架搭建
1. 项目初始化
2. 数据库连接配置
3. 基础中间件开发
4. 用户认证模块

### 第二阶段：核心功能开发
1. 文章管理模块
2. 标签管理模块
3. 评论管理模块
4. 文件上传功能

### 第三阶段：高级功能开发
1. 主题管理模块
2. 设置管理模块
3. RSS功能实现
4. 验证码功能

### 第四阶段：测试和优化
1. 单元测试编写
2. 集成测试
3. 性能优化
4. 安全加固

这个Node.js版本架构设计为PeachtreesCMS提供了现代化的后端解决方案，同时保持了与现有PHP版本的API兼容性。