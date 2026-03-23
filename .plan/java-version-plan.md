# PeachtreesCMS Java语言版本重写计划

## 项目概述

基于现有PeachtreesCMS PHP API，使用Java Spring Boot框架进行企业级重写，提供稳定、可扩展的API服务。

## 技术栈选择

- **Web框架**: Spring Boot 3.x
- **数据库**: Spring Data JPA + MySQL
- **认证**: Spring Security + JWT
- **文件上传**: Spring MultipartFile
- **配置管理**: Spring Boot Configuration
- **文档**: SpringDoc OpenAPI 3
- **构建工具**: Maven
- **测试**: JUnit 5 + Mockito

## 项目结构设计

```
peachtrees-java/
├── src/main/java/
│   └── com/peachtrees/
│       └── cms/
│           ├── PeachtreesApplication.java     # 应用启动类
│           ├── config/                        # 配置类
│           │   ├── SecurityConfig.java
│           │   ├── CorsConfig.java
│           │   ├── JpaConfig.java
│           │   └── OpenApiConfig.java
│           ├── controller/                    # 控制器层
│           │   ├── AuthController.java
│           │   ├── PostController.java
│           │   ├── TagController.java
│           │   ├── CommentController.java
│           │   ├── UserController.java
│           │   ├── ThemeController.java
│           │   └── OptionController.java
│           ├── service/                       # 服务层
│           │   ├── AuthService.java
│           │   ├── PostService.java
│           │   ├── TagService.java
│           │   ├── CommentService.java
│           │   ├── UserService.java
│           │   ├── ThemeService.java
│           │   └── OptionService.java
│           ├── repository/                    # 数据访问层
│           │   ├── UserRepository.java
│           │   ├── PostRepository.java
│           │   ├── TagRepository.java
│           │   ├── CommentRepository.java
│           │   ├── ThemeRepository.java
│           │   └── OptionRepository.java
│           ├── entity/                        # 实体类
│           │   ├── User.java
│           │   ├── Post.java
│           │   ├── Tag.java
│           │   ├── Comment.java
│           │   ├── Theme.java
│           │   └── Option.java
│           ├── dto/                          # 数据传输对象
│           │   ├── request/
│           │   │   ├── LoginRequest.java
│           │   │   ├── CreatePostRequest.java
│           │   │   ├── CreateCommentRequest.java
│           │   │   └── UpdateUserRequest.java
│           │   └── response/
│           │       ├── ApiResponse.java
│           │       ├── LoginResponse.java
│           │       ├── PostResponse.java
│           │       └── PaginationResponse.java
│           ├── security/                      # 安全相关
│           │   ├── JwtUtil.java
│           │   ├── JwtAuthenticationFilter.java
│           │   ├── UserDetailsServiceImpl.java
│           │   └── SessionManager.java
│           ├── exception/                    # 异常处理
│           │   ├── GlobalExceptionHandler.java
│           │   ├── ApiException.java
│           │   └── ErrorCode.java
│           └── util/                         # 工具类
│               ├── FileUtil.java
│               ├── PasswordUtil.java
│               ├── CaptchaUtil.java
│               └── ValidationUtil.java
├── src/main/resources/
│   ├── application.yml                       # 主配置文件
│   ├── application-dev.yml                   # 开发环境配置
│   ├── application-prod.yml                  # 生产环境配置
│   └── static/                               # 静态资源
│       └── uploads/                           # 上传文件目录
├── src/test/java/                            # 测试代码
├── uploads/                                  # 文件上传目录
├── pom.xml                                   # Maven配置
├── Dockerfile
└── README.md
```

## 核心架构设计

### 1. 分层架构

采用经典的三层架构：
- **Controller层**: 处理HTTP请求和响应
- **Service层**: 业务逻辑处理
- **Repository层**: 数据访问

### 2. 实体类设计

使用JPA注解映射数据库表：

```java
@Entity
@Table(name = "users")
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    
    @Column(name = "username", unique = true, nullable = false)
    private String username;
    
    @Column(name = "email", unique = true, nullable = false)
    private String email;
    
    @Column(name = "password_hash", nullable = false)
    private String passwordHash;
    
    @CreationTimestamp
    @Column(name = "created_at")
    private LocalDateTime createdAt;
    
    @Column(name = "last_login_at")
    private LocalDateTime lastLoginAt;
    
    // Getters and Setters
}

@Entity
@Table(name = "posts")
public class Post {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    
    @ManyToOne
    @JoinColumn(name = "tag", referencedColumnName = "tag")
    private Tag tag;
    
    @Enumerated(EnumType.STRING)
    @Column(name = "post_type")
    private PostType postType;
    
    @Column(name = "title", nullable = false)
    private String title;
    
    @Column(name = "summary")
    private String summary;
    
    @Column(name = "cover_media", columnDefinition = "JSON")
    private String coverMedia;
    
    @Column(name = "content", columnDefinition = "TEXT")
    private String content;
    
    @Column(name = "allow_comments")
    private Boolean allowComments;
    
    @CreationTimestamp
    @Column(name = "created_at")
    private LocalDateTime createdAt;
    
    @UpdateTimestamp
    @Column(name = "updated_at")
    private LocalDateTime updatedAt;
    
    // Getters and Setters
}
```

### 3. 认证和安全配置

使用Spring Security实现安全控制：

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {
    
    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .cors(withDefaults())
            .csrf(csrf -> csrf.disable())
            .sessionManagement(session -> session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(authz -> authz
                .requestMatchers("/api/auth/login", "/api/auth/captcha").permitAll()
                .requestMatchers("/api/posts/**", "/api/tags/**", "/api/comments/**").permitAll()
                .requestMatchers("/api/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .addFilterBefore(jwtAuthenticationFilter(), UsernamePasswordAuthenticationFilter.class);
        
        return http.build();
    }
    
    @Bean
    public JwtAuthenticationFilter jwtAuthenticationFilter() {
        return new JwtAuthenticationFilter();
    }
}
```

### 4. 统一响应格式

```java
@Data
@AllArgsConstructor
@NoArgsConstructor
public class ApiResponse<T> {
    private boolean success;
    private String message;
    private T data;
    
    public static <T> ApiResponse<T> success(T data, String message) {
        return new ApiResponse<>(true, message, data);
    }
    
    public static <T> ApiResponse<T> success(T data) {
        return success(data, "Success");
    }
    
    public static ApiResponse<?> error(String message) {
        return new ApiResponse<>(false, message, null);
    }
}

@RestControllerAdvice
public class GlobalExceptionHandler {
    
    @ExceptionHandler(Exception.class)
    public ResponseEntity<ApiResponse<?>> handleException(Exception ex) {
        ApiResponse<?> response = ApiResponse.error(ex.getMessage());
        return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).body(response);
    }
    
    @ExceptionHandler(ApiException.class)
    public ResponseEntity<ApiResponse<?>> handleApiException(ApiException ex) {
        ApiResponse<?> response = ApiResponse.error(ex.getMessage());
        return ResponseEntity.status(ex.getStatus()).body(response);
    }
}
```

## API接口设计

### 控制器类示例

```java
@RestController
@RequestMapping("/api/posts")
@Validated
public class PostController {
    
    private final PostService postService;
    
    public PostController(PostService postService) {
        this.postService = postService;
    }
    
    @GetMapping
    public ApiResponse<PaginationResponse<PostResponse>> getPosts(
            @RequestParam(defaultValue = "1") int page,
            @RequestParam(defaultValue = "10") int perPage,
            @RequestParam(required = false) String tag) {
        
        PaginationResponse<PostResponse> result = postService.getPosts(page, perPage, tag);
        return ApiResponse.success(result);
    }
    
    @PostMapping
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<CreatePostResponse> createPost(@Valid @RequestBody CreatePostRequest request) {
        CreatePostResponse result = postService.createPost(request);
        return ApiResponse.success(result, "文章创建成功");
    }
    
    @PutMapping("/{id}")
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<?> updatePost(@PathVariable Long id, @Valid @RequestBody UpdatePostRequest request) {
        postService.updatePost(id, request);
        return ApiResponse.success(null, "文章更新成功");
    }
    
    @DeleteMapping("/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public ApiResponse<?> deletePost(@PathVariable Long id) {
        postService.deletePost(id);
        return ApiResponse.success(null, "文章删除成功");
    }
}
```

### 服务层设计

```java
@Service
@Transactional
public class PostService {
    
    private final PostRepository postRepository;
    private final TagRepository tagRepository;
    private final ModelMapper modelMapper;
    
    public PostService(PostRepository postRepository, TagRepository tagRepository, ModelMapper modelMapper) {
        this.postRepository = postRepository;
        this.tagRepository = tagRepository;
        this.modelMapper = modelMapper;
    }
    
    public PaginationResponse<PostResponse> getPosts(int page, int perPage, String tag) {
        Pageable pageable = PageRequest.of(page - 1, perPage, Sort.by("createdAt").descending());
        
        Page<Post> postPage;
        if (tag != null && !tag.trim().isEmpty()) {
            postPage = postRepository.findByTagTag(tag, pageable);
        } else {
            postPage = postRepository.findAll(pageable);
        }
        
        List<PostResponse> postResponses = postPage.getContent().stream()
            .map(this::convertToResponse)
            .collect(Collectors.toList());
        
        return new PaginationResponse<>(
            postResponses,
            postPage.getNumber() + 1,
            postPage.getSize(),
            postPage.getTotalElements(),
            postPage.getTotalPages()
        );
    }
    
    public CreatePostResponse createPost(CreatePostRequest request) {
        // 验证标签存在
        Tag tag = tagRepository.findByTag(request.getTag())
            .orElseThrow(() -> new ApiException("标签不存在", HttpStatus.BAD_REQUEST));
        
        // 创建文章
        Post post = modelMapper.map(request, Post.class);
        post.setTag(tag);
        post.setCreatedAt(LocalDateTime.now());
        post.setUpdatedAt(LocalDateTime.now());
        
        Post savedPost = postRepository.save(post);
        
        // 更新标签计数
        updateTagPostCount(tag.getTag());
        
        return new CreatePostResponse(savedPost.getId());
    }
    
    private void updateTagPostCount(String tag) {
        Long count = postRepository.countByTagTag(tag);
        tagRepository.updatePostCount(tag, count);
    }
    
    private PostResponse convertToResponse(Post post) {
        PostResponse response = modelMapper.map(post, PostResponse.class);
        
        // 生成摘要
        String summarySource = StringUtils.hasText(post.getSummary()) ? 
            post.getSummary() : post.getContent();
        String plainText = Jsoup.parse(summarySource).text();
        if (plainText.length() > 200) {
            response.setExcerpt(plainText.substring(0, 200) + "...");
        } else {
            response.setExcerpt(plainText);
        }
        
        return response;
    }
}
```

## 数据库配置

### application.yml配置

```yaml
spring:
  application:
    name: peachtrees-cms
  
  datasource:
    url: jdbc:mysql://localhost:3306/peachtrees?useUnicode=true&characterEncoding=utf8&useSSL=false&serverTimezone=Asia/Shanghai
    username: root
    password: 111111
    driver-class-name: com.mysql.cj.jdbc.Driver
  
  jpa:
    hibernate:
      ddl-auto: validate
    properties:
      hibernate:
        dialect: org.hibernate.dialect.MySQL8Dialect
        format_sql: true
    show-sql: true
  
  servlet:
    multipart:
      max-file-size: 10MB
      max-request-size: 10MB

server:
  port: 8080
  servlet:
    context-path: /

jwt:
  secret: peachtrees_secret_key_change_in_production
  expiration: 86400

cors:
  allowed-origins: http://localhost:5173,http://localhost:3000

logging:
  level:
    com.peachtrees.cms: DEBUG
```

## 安全性设计

### 1. Spring Security集成

```java
@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {
    
    @Override
    protected void doFilterInternal(HttpServletRequest request, 
                                   HttpServletResponse response, 
                                   FilterChain filterChain) throws ServletException, IOException {
        
        String authHeader = request.getHeader("Authorization");
        
        if (authHeader != null && authHeader.startsWith("Bearer ")) {
            String token = authHeader.substring(7);
            
            if (jwtUtil.validateToken(token)) {
                String username = jwtUtil.getUsernameFromToken(token);
                UserDetails userDetails = userDetailsService.loadUserByUsername(username);
                
                UsernamePasswordAuthenticationToken authentication = 
                    new UsernamePasswordAuthenticationToken(userDetails, null, userDetails.getAuthorities());
                authentication.setDetails(new WebAuthenticationDetailsSource().buildDetails(request));
                
                SecurityContextHolder.getContext().setAuthentication(authentication);
            }
        }
        
        filterChain.doFilter(request, response);
    }
}
```

### 2. 密码安全

```java
@Component
public class PasswordUtil {
    
    private final PasswordEncoder passwordEncoder;
    
    public PasswordUtil() {
        this.passwordEncoder = new BCryptPasswordEncoder();
    }
    
    public String encodePassword(String rawPassword) {
        return passwordEncoder.encode(rawPassword);
    }
    
    public boolean matches(String rawPassword, String encodedPassword) {
        return passwordEncoder.matches(rawPassword, encodedPassword);
    }
}
```

## 文件上传功能

```java
@Service
public class FileUploadService {
    
    @Value("${file.upload-dir:./uploads}")
    private String uploadDir;
    
    public String uploadFile(MultipartFile file, String subDirectory) throws IOException {
        // 验证文件类型
        String contentType = file.getContentType();
        if (!isAllowedFileType(contentType)) {
            throw new ApiException("不支持的文件类型", HttpStatus.BAD_REQUEST);
        }
        
        // 生成唯一文件名
        String originalFilename = file.getOriginalFilename();
        String fileExtension = getFileExtension(originalFilename);
        String fileName = UUID.randomUUID().toString() + fileExtension;
        
        // 创建目录
        Path uploadPath = Paths.get(uploadDir, subDirectory);
        Files.createDirectories(uploadPath);
        
        // 保存文件
        Path filePath = uploadPath.resolve(fileName);
        file.transferTo(filePath.toFile());
        
        return "/uploads/" + subDirectory + "/" + fileName;
    }
    
    private boolean isAllowedFileType(String contentType) {
        return contentType != null && 
               (contentType.startsWith("image/") || contentType.startsWith("video/"));
    }
    
    private String getFileExtension(String filename) {
        return filename != null ? filename.substring(filename.lastIndexOf(".")) : "";
    }
}
```

## 测试策略

### 单元测试示例

```java
@ExtendWith(MockitoExtension.class)
class PostServiceTest {
    
    @Mock
    private PostRepository postRepository;
    
    @Mock
    private TagRepository tagRepository;
    
    @InjectMocks
    private PostService postService;
    
    @Test
    void createPost_ShouldReturnSuccess_WhenValidRequest() {
        // Given
        CreatePostRequest request = new CreatePostRequest("Test Title", "tech", "Test content");
        Tag tag = new Tag("tech", "技术");
        
        when(tagRepository.findByTag("tech")).thenReturn(Optional.of(tag));
        when(postRepository.save(any(Post.class))).thenAnswer(invocation -> {
            Post post = invocation.getArgument(0);
            post.setId(1L);
            return post;
        });
        
        // When
        CreatePostResponse response = postService.createPost(request);
        
        // Then
        assertNotNull(response);
        assertEquals(1L, response.getId());
        verify(postRepository, times(1)).save(any(Post.class));
    }
}
```

## 部署方案

### Docker部署

```dockerfile
FROM openjdk:17-jdk-slim

WORKDIR /app

COPY target/peachtrees-cms-1.0.0.jar app.jar

EXPOSE 8080

ENTRYPOINT ["java", "-jar", "app.jar"]
```

### 构建和运行

```bash
# 构建项目
mvn clean package

# 运行应用
java -jar target/peachtrees-cms-1.0.0.jar

# 使用Docker运行
docker build -t peachtrees-cms .
docker run -p 8080:8080 peachtrees-cms
```

## 开发计划

### 第一阶段：基础架构 (2周)
- Spring Boot项目初始化
- 数据库配置和实体类设计
- 基础安全配置

### 第二阶段：核心功能 (3周)
- 认证模块实现
- 文章管理功能
- 标签管理功能
- 评论管理功能

### 第三阶段：高级功能 (2周)
- 用户管理模块
- 主题管理模块
- 文件上传功能
- API文档生成

### 第四阶段：测试和优化 (1周)
- 单元测试编写
- 集成测试
- 性能优化
- 部署配置

## 兼容性保证

### API兼容性
- 保持与PHP版本完全相同的请求/响应格式
- 相同的错误处理机制
- 相同的认证方式支持

### 数据库兼容性
- 使用JPA映射现有数据库结构
- 兼容现有数据迁移
- 保持数据一致性

### 功能兼容性
- 所有PHP版本功能完整实现
- 相同的业务逻辑处理
- 相同的权限控制机制

## 总结

Java Spring Boot版本将提供：
- 企业级的稳定性和可扩展性
- 完善的生态工具支持
- 强大的安全机制
- 优秀的开发体验
- 与现有系统完全兼容