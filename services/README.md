### 内容读取器服务

这个目录包含一个简单的、基于 Python 的微服务，用于从 URL 中提取主要内容。主 PHP 库可以调用此服务来为其“读取 URL”工具提供支持。

我们选择这种方法是为了将 Python 依赖（如 `BeautifulSoup`、`requests`）与主 PHP 项目隔离开来，从而保持核心库的轻量级，并且不依赖于其他编程语言。

#### 如何运行

1.  **安装 Docker 和 Docker Compose**: 确保您的系统中已经安装了 Docker。
2.  **进入此目录**:
    ```bash
    cd services
    ```
3.  **构建并运行服务**:
    ```bash
    docker-compose up --build -d
    ```
服务将在 `http://127.0.0.1:8000` 上可用。

#### API 端点

*   **URL**: `/read`
*   **方法**: `POST`
*   **请求体** (JSON):
    ```json
    {
      "url": "https://example.com/some-article"
    }
    ```
*   **成功响应** (JSON):
    ```json
    {
      "url": "https://example.com/some-article",
      "content": "文章的主要文本内容..."
    }
    ```

#### 禁用此功能

如果您不想使用“读取 URL”工具，只需在您的 `.env` 文件中将 `READER_API_URL` 变量留空即可。深度研究管道将自动检测到这一点，并禁用相应的功能。
