# PeachtreesCMS Go语言版本重写计划

## 项目概述

基于现有PeachtreesCMS PHP API，使用Go语言进行完全重写，保持与原API完全兼容的接口规范。

## 技术栈选择

- **Web框架**: Gin (高性能、轻量级)
- **数据库**: 标准库 database/sql + MySQL驱动
- **认证**: JWT Token + Session兼容
- **文件上传**: Gin Multipart支持
- **配置管理**: Viper
- **日志**: Zap
- **依赖管理**: Go Modules

## 目录结构设计

```
peachtrees-go/
├── cmd/
│   └── server/
│       └── main.go              # 应用入口
├── internal/
│   ├── config/                  # 配置管理
│   │   └── config.go
│   ├── database/                # 数据库连接
│   │   └── mysql.go
│   ├── middleware/              # 中间件
│   │   ├── auth.go
│   │   ├── cors.go
│   │   └── logging.go
│   ├── models/                  # 数据模型
│   │   ├── user.go
│   │   ├── post.go
│   │   ├── tag.go
│   │   ├── comment.go
│   │   └── theme.go
│   ├── handlers/                # 请求处理器
│   │   ├── auth/
│   │   │   ├── login.go
│   │   │   ├── logout.go
│   │   │   └── check.go
│   │   ├── posts/
│   │   │   ├── list.go
│   │   │   ├── create.go
│   │   │   ├── update.go
│   │   │   ├── delete.go
│   │   │   ├── view.go
│   │   │   └── upload.go
│   │   ├── tags/
│   │   │   ├── list.go
│   │   │   ├── create.go
│   │   │   ├── update.go
│   │   │   └── delete.go
│   │   ├── comments/
│   │   │   ├── list.go
│   │   │   ├── create.go
│   │   │   ├── approve.go
│   │   │   └── delete.go
│   │   ├── users/
│   │   │   ├── list.go
│   │   │   ├── create.go
│   │   │   ├── update.go
│   │   │   └── delete.go
│   │   └── themes/
│   │       ├── list.go
│   │       ├── activate.go
│   │       └── layout.go
│   ├── services/                # 业务逻辑层
│   │   ├── auth_service.go
│   │   ├── post_service.go
│   │   ├── comment_service.go
│   │   └── theme_service.go
│   ├── utils/                   # 工具函数
│   │   ├── response.go
│   │   ├── password.go
│   │   ├── captcha.go
│   │   └── validation.go
│   └── types/                   # 类型定义
│       └── request.go
├── uploads/                     # 文件上传目录
├── go.mod
├── go.sum
├── config.yaml                  # 配置文件
└── README.md
```

## 核心架构设计

### 1. 配置管理

使用Viper管理配置，支持环境变量和配置文件：

```go
type Config struct {
    Server   ServerConfig   `mapstructure:"server"`
    Database DatabaseConfig `mapstructure:"database"`
    JWT      JWTConfig      `mapstructure:"jwt"`
    Upload   UploadConfig   `mapstructure:"upload"`
}

type ServerConfig struct {
    Port         int      `mapstructure:"port"`
    CorsOrigins []string `mapstructure:"cors_origins"`
}

type DatabaseConfig struct {
    Host     string `mapstructure:"host"`
    Port     int    `mapstructure:"port"`
    User     string `mapstructure:"user"`
    Password string `mapstructure:"password"`
    Name     string `mapstructure:"name"`
}
```

### 2. 数据库层

使用database/sql标准库，实现连接池和预处理语句：

```go
type DB struct {
    *sql.DB
}

func NewDB(config DatabaseConfig) (*DB, error) {
    dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True",
        config.User, config.Password, config.Host, config.Port, config.Name)
    
    db, err := sql.Open("mysql", dsn)
    if err != nil {
        return nil, err
    }
    
    db.SetMaxOpenConns(25)
    db.SetMaxIdleConns(25)
    db.SetConnMaxLifetime(5 * time.Minute)
    
    return &DB{db}, nil
}
```

### 3. 认证中间件

兼容Session和JWT两种认证方式：

```go
func AuthMiddleware() gin.HandlerFunc {
    return func(c *gin.Context) {
        // 1. 尝试从JWT Token获取用户信息
        token := c.GetHeader("Authorization")
        if token != "" {
            user, err := validateJWT(token)
            if err == nil {
                c.Set("user", user)
                c.Next()
                return
            }
        }
        
        // 2. 尝试从Session获取用户信息
        session := sessions.Default(c)
        userID := session.Get("user_id")
        if userID != nil {
            user := getUserByID(userID.(int))
            if user != nil {
                c.Set("user", user)
                c.Next()
                return
            }
        }
        
        // 3. 未认证
        c.JSON(401, gin.H{"error": "Unauthorized"})
        c.Abort()
    }
}
```

### 4. 统一响应格式

保持与PHP版本相同的响应格式：

```go
type Response struct {
    Success bool        `json:"success"`
    Message string      `json:"message"`
    Data    interface{} `json:"data"`
}

func Success(c *gin.Context, data interface{}, message string) {
    c.JSON(200, Response{
        Success: true,
        Message: message,
        Data:    data,
    })
}

func Error(c *gin.Context, message string, status int) {
    c.JSON(status, Response{
        Success: false,
        Message: message,
        Data:    nil,
    })
}
```

## API接口映射

### 认证模块
- `POST /api/auth/login` → `auth.LoginHandler`
- `POST /api/auth/logout` → `auth.LogoutHandler`
- `GET /api/auth/check` → `auth.CheckHandler`

### 文章管理模块
- `GET /api/posts` → `posts.ListHandler`
- `POST /api/posts` → `posts.CreateHandler`
- `PUT /api/posts/:id` → `posts.UpdateHandler`
- `DELETE /api/posts/:id` → `posts.DeleteHandler`
- `GET /api/posts/:id` → `posts.ViewHandler`
- `POST /api/posts/upload-media` → `posts.UploadMediaHandler`
- `POST /api/posts/upload-bigpicture` → `posts.UploadBigPictureHandler`

### 标签管理模块
- `GET /api/tags` → `tags.ListHandler`
- `POST /api/tags` → `tags.CreateHandler`
- `PUT /api/tags/:id` → `tags.UpdateHandler`
- `DELETE /api/tags/:id` → `tags.DeleteHandler`

### 评论管理模块
- `GET /api/comments` → `comments.ListHandler`
- `POST /api/comments` → `comments.CreateHandler`
- `PUT /api/comments/:id/approve` → `comments.ApproveHandler`
- `DELETE /api/comments/:id` → `comments.DeleteHandler`

### 用户管理模块
- `GET /api/users` → `users.ListHandler` (需要管理员权限)
- `POST /api/users` → `users.CreateHandler` (需要管理员权限)
- `PUT /api/users/:id/password` → `users.UpdatePasswordHandler`
- `DELETE /api/users/:id` → `users.DeleteHandler` (需要管理员权限)

### 主题管理模块
- `GET /api/themes` → `themes.ListHandler` (需要管理员权限)
- `PUT /api/themes/:slug/active` → `themes.ActivateHandler` (需要管理员权限)
- `POST /api/themes/:slug/layout` → `themes.SaveLayoutHandler` (需要管理员权限)

### 设置管理模块
- `GET /api/options` → `options.ListHandler`
- `PUT /api/options` → `options.UpdateHandler` (需要管理员权限)

## 数据库设计

保持与现有MySQL数据库结构完全兼容，使用相同的表结构：

```sql
-- 用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME,
    last_login_at DATETIME
);

-- 文章表
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag VARCHAR(50),
    post_type ENUM('normal', 'big-picture') DEFAULT 'normal',
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    cover_media JSON,
    content TEXT,
    allow_comments TINYINT(1) DEFAULT 1,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (tag) REFERENCES tags(tag)
);

-- 其他表结构保持不变...
```

## 安全性设计

### 1. SQL注入防护
- 使用预处理语句
- 参数化查询
- 输入验证和过滤

### 2. XSS防护
- 输出转义
- 内容安全策略

### 3. 文件上传安全
- 文件类型验证
- 大小限制
- 路径遍历防护

### 4. 认证安全
- JWT Token过期机制
- Session安全配置
- 密码哈希（bcrypt）

## 性能优化

### 1. 数据库优化
- 连接池配置
- 查询优化
- 索引优化

### 2. 并发处理
- Goroutine并发处理
- 连接复用
- 缓存策略

### 3. 内存管理
- 对象池
- 垃圾回收优化

## 部署配置

### Docker部署
```dockerfile
FROM golang:1.21-alpine

WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN go build -o main ./cmd/server

EXPOSE 8080
CMD ["/app/main"]
```

### 环境变量配置
```bash
# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=111111
DB_NAME=peachtrees

# 服务器配置
PORT=8080
CORS_ORIGINS=http://localhost:5173,http://localhost:3000

# JWT配置
JWT_SECRET=peachtrees_secret_key
JWT_EXPIRE=86400
```

## 开发计划

### 第一阶段：基础架构搭建 (1-2周)
- 项目结构初始化
- 配置管理实现
- 数据库连接层
- 基础中间件开发

### 第二阶段：核心模块开发 (2-3周)
- 认证模块实现
- 文章管理模块
- 标签管理模块
- 评论管理模块

### 第三阶段：高级功能实现 (1-2周)
- 用户管理模块
- 主题管理模块
- 设置管理模块
- 文件上传功能

### 第四阶段：测试和优化 (1周)
- 单元测试编写
- 集成测试
- 性能优化
- 部署文档编写

## 兼容性保证

### API兼容性
- 保持与PHP版本完全相同的请求/响应格式
- 相同的错误处理机制
- 相同的认证方式支持

### 数据库兼容性
- 使用相同的数据库结构
- 兼容现有数据迁移
- 保持数据一致性

### 功能兼容性
- 所有PHP版本功能完整实现
- 相同的业务逻辑处理
- 相同的权限控制机制

## 总结

Go语言版本将提供：
- 更高的性能和并发能力
- 更好的内存管理
- 更强的类型安全性
- 现代化的开发体验
- 与现有系统完全兼容