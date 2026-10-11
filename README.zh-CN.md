# PHP Wiki

[![Release](https://img.shields.io/github/v/release/flightstarpilot/php-wiki)](https://github.com/flightstarpilot/php-wiki/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)](https://www.php.net/)

一个用纯 PHP 编写的轻量自托管 Wiki 引擎。公开可读，登录后可协作，支持
多语言内容、树形目录、标签、双向链接、全文搜索、附件，以及可插拔的存储
驱动。

无需构建步骤，无需 Composer，把目录丢到任何支持 PHP 的主机即可运行。

[English README](README.md) | [文档](docs/) | [路线图](docs/roadmap.md)

## 特性

### 内容

- 翻译组：一个逻辑页面对应 N 个语言版本，各自拥有独立的版本历史，但共享
  同一套讨论。
- 每个页面都有版本历史，支持并排和统一两种 diff 视图。
- 一键回滚到任意历史版本。
- 树形目录，支持父子关系与手动排序。
- `[[双链]]` 及反向链接面板。
- 标签系统与标签索引页。
- 页面级锁定，用于保护核心文档。
- slug 变更时自动生成重定向。

### 协作

- Issue：任何登录用户都可发起讨论、评论和关闭。
- 提案：普通用户提交修改，编辑者审核并合并。支持 diff 对比、多轮讨论和
  基于基准版本的冲突检测。
- Issue 和提案都支持评论。
- 参与讨论的用户会在有新评论时收到邮件通知。
- 所有写操作都有审计日志，可导出为纯文本日志行。

### 用户与权限

- 开放注册、邀请码注册，或由管理员手动创建。
- 邮箱验证、密码重置。
- GitHub OAuth 单点登录。
- 三种角色：user（只读）、editor（可编辑页面）、admin（全部权限）。

### 搜索与发现

- 支持 SQLite FTS5 和 MySQL FULLTEXT，自动降级为 LIKE 匹配。
- 顶栏搜索框、标签页、sitemap.xml、RSS 输出。

### 附件

- 本地文件系统或任意 S3 兼容对象存储。
- 内置 SigV4 签名，兼容 AWS S3、Cloudflare R2、MinIO、阿里云 OSS。
- 编辑器内支持拖拽、点击、粘贴三种上传方式。

### 后台管理

- 11 个模块：站点信息、SEO、邮件、账号、功能开关、存储、附件、搜索、
  重定向、审计日志、外观。
- 分 tab 设置页，字段按条件动态显示。
- 危险配置组合实时警告。
- 用户列表支持筛选、分页、批量操作。
- 手动创建用户。
- 审计日志支持 HAR 风格文本输出和友好阅读视图。

### 界面

- 内置 8 种界面语言，按浏览器语言自动识别。
- Toast UI 编辑器，支持 Markdown 和所见即所得两种模式。
- 响应式布局，浅色与深色主题。
- 干净 URL（无 `.php` 后缀）。
- 支持注入自定义 CSS、JavaScript 和页脚 HTML。

### 存储

| 驱动     | 说明                                          |
| -------- | --------------------------------------------- |
| `sqlite` | 单文件数据库，零配置。                        |
| `mysql`  | MySQL 5.7+ / MariaDB 10.2+。                  |
| `http`   | 自定义 JSON 接口，后端用任意语言实现。        |

Redis 作为可选缓存后端。

## 环境要求

- PHP 8.0 或更高
- PDO 扩展，包含 `pdo_sqlite` 或 `pdo_mysql`
- 可选：`pdo_sqlite` 带 FTS5 支持，用于全文搜索
- 可选：`curl`，用于 HTTP 数据库驱动和 GitHub OAuth
- 可选：`openssl`，用于加密存储 SMTP 凭据

不需要 Composer，不需要 npm，不需要构建工具。

## 快速开始

1. 下载或克隆本仓库到网站根目录。
2. 按下面的说明设置属主和权限。
3. 浏览器访问 `/install.php`，按向导操作。
4. 安装成功后安装程序自动删除自身。
5. 访问 `/admin` 用你创建的管理员账号登录。
6. 访问 `/new` 撰写第一篇页面。

详细步骤见 [docs/installation.md](docs/installation.md)。

## 目录权限

以下三个目录必须对 Web 服务器用户可写，而不是 root：

    data/      SQLite 数据库、预写日志、共享内存文件
    config/    安装程序写入的 config.php
    uploads/   使用本地驱动时存储的附件

先确认 Web 服务器用户是谁：

    ps aux | grep -E 'nginx|apache|php-fpm|httpd' | grep -v grep

常见值：Debian / Ubuntu 上是 `www-data`，RHEL / CentOS 上是 `nginx` 或
`apache`，宝塔面板是 `www`，共享主机上通常就是你自己的账号。然后：

    chown -R www-data:www-data data config uploads
    chmod 755 data config uploads

把用户换成你系统上正确的那个。不要用 `chmod 777`。

属主比权限数字重要。属主为 root、权限为 755 的目录，对 Web 服务器仍不可写。

不要把 PHP Wiki 放在 `/root` 目录下。`/root` 本身是 700 权限，Web 服务器无法
进入。请放到正常 Web 根目录，例如 `/var/www/wiki` 或 `/www/wwwroot/wiki`。

如果系统开启了 SELinux（RHEL、CentOS、Rocky、AlmaLinux）：

    chcon -R -t httpd_sys_rw_content_t data config uploads

## 配置

所有配置都在后台完成。唯一需要手动编辑的文件是 `config/config.php`，由
安装程序生成。完整参考见 [docs/configuration.md](docs/configuration.md)。

## 文档

- [安装](docs/installation.md)
- [配置](docs/configuration.md)
- [架构](docs/architecture.md)
- [HTTP 数据库协议](docs/http-database-protocol.md)
- [新增界面语言](docs/translation.md)
- [开发](docs/development.md)
- [故障排查](docs/troubleshooting.md)
- [升级](docs/upgrading.md)
- [常见问题](docs/faq.md)
- [路线图](docs/roadmap.md)

## 目录结构

    admin.php                后台入口（重写到 /admin）
    index.php                前台统一入口
    install.php              安装程序，装完自删
    .htaccess                URL 重写与访问加固
    assets/                  本地托管的前端库
    config/                  生成的配置（可写）
    data/                    SQLite 数据库（可写）
    database/                参考 SQL 建表脚本与迁移
    docs/                    项目文档
    examples/                HTTP 数据库参考服务端
    lang/                    界面语言包
    src/                     应用源码
    templates/               PHP 模板
    uploads/                 附件（可写）
    vendor/                  内置第三方库

## 参与贡献

见 [CONTRIBUTING.md](CONTRIBUTING.md)。特别欢迎语言翻译，见
[docs/translation.md](docs/translation.md)。

## 安全

发现安全问题请不要开公开 Issue，见 [SECURITY.md](SECURITY.md)。

## 许可证

MIT，见 [LICENSE](LICENSE)。

第三方组件：

- [Parsedown](https://github.com/erusev/parsedown) 和
  [Parsedown Extra](https://github.com/erusev/parsedown-extra) - MIT
- [Toast UI Editor](https://github.com/nhn/tui.editor) - MIT
- [Tailwind CSS](https://tailwindcss.com/) - MIT
- [Lucide 图标](https://lucide.dev/) - ISC
- 可选：[PHPMailer](https://github.com/PHPMailer/PHPMailer) - LGPL-2.1
