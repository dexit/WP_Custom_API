# WP Custom API - Enterprise Rebuild Project

## 🚀 Project Overview

This is a comprehensive rebuild of the WP Custom API Endpoint Manager, transforming it from a basic endpoint manager into a **production-grade WordPress plugin** with full admin UI, job scheduling, advanced logging, visual workflow builder, and enterprise-grade data transformation capabilities.

## 📋 Current Status

**Phase 1 Foundation:** ✅ IN PROGRESS
- Composer infrastructure: ✅ COMPLETE
- Admin menu structure: ⏳ PENDING
- Database schema extensions: ⏳ PENDING
- Asset structure: ⏳ PENDING

## 🎯 Project Goals

Transform the Endpoint Manager into a visual, no-code platform for:
- Creating custom REST API endpoints via UI
- Receiving and processing webhooks
- Building ETL pipelines visually
- Creating workflow automations
- Monitoring and logging all activity
- Queuing heavy operations asynchronously

## 🏗️ Architecture Overview

### External Dependencies (Composer)

| Package | Version | Purpose |
|---------|---------|---------|
| **woocommerce/action-scheduler** | ^3.7 | Job queue system (battle-tested, scales to 50K+ jobs) |
| **monolog/monolog** | ^2.9 | Structured logging with multiple handlers |
| **phpunit/phpunit** | ^9.6 | Unit testing framework (dev) |
| **phpstan/phpstan** | ^1.10 | Static analysis (dev) |

### Core Features

#### 1. **Admin UI System**
- Dashboard with statistics and charts
- List tables for all entities (WP_List_Table)
- Visual form builders
- Test/Debug interfaces
- Real-time log viewers
- Settings pages

#### 2. **Endpoint Management**
- Full CRUD via admin UI
- Support: GET/POST/PUT/PATCH/DELETE
- Multiple auth methods (API key, OAuth, signature, JWT, IP whitelist)
- Request/response transformation
- Custom PHP code execution
- Conditional logic/routing

#### 3. **Job Queue System** (WooCommerce Action Scheduler)
- Queue incoming webhooks for processing
- Retry logic with exponential backoff
- Batch processing
- Scheduled outgoing requests
- Priority queues

#### 4. **Logging System** (Monolog)
- Structured logs with context
- Request/response logging
- Error tracking with stack traces
- Performance metrics
- Searchable log viewer UI

#### 5. **Visual Workflow Builder** (React)
- Drag-and-drop workflow creation
- Multiple node types (Transform, Condition, Action, Data, Loop, Delay)
- Visual connections between nodes
- Test workflows in real-time
- Pre-built templates

#### 6. **ETL Engine**
- Visual ETL pipeline builder
- 50+ data transformations
- Field mapping interface
- Parse JSON, XML, CSV
- Validation rules

## 📂 New Directory Structure

```
/WP_Custom_API/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── dashboard.css
│   │   ├── endpoints.css
│   │   └── logs.css
│   ├── js/
│   │   ├── admin.js
│   │   ├── endpoint-builder.js
│   │   ├── etl-builder.jsx (React)
│   │   ├── workflow-builder.jsx (React)
│   │   └── test-runner.js
│   └── images/
│       └── icons/
├── includes/
│   ├── admin/
│   │   ├── admin-menu.php
│   │   ├── class-admin-controller.php
│   │   ├── pages/
│   │   │   ├── class-dashboard-page.php
│   │   │   ├── class-endpoints-page.php
│   │   │   ├── class-endpoint-edit-page.php
│   │   │   ├── class-jobs-page.php
│   │   │   ├── class-logs-page.php
│   │   │   └── class-settings-page.php
│   │   ├── tables/
│   │   │   ├── class-endpoints-list-table.php
│   │   │   ├── class-jobs-list-table.php
│   │   │   └── class-logs-list-table.php
│   │   └── forms/
│   │       └── class-endpoint-form-builder.php
│   ├── queue/
│   │   ├── class-queue-manager.php
│   │   ├── class-job-processor.php
│   │   ├── class-retry-handler.php
│   │   ├── class-batch-processor.php
│   │   └── actions/
│   ├── logging/
│   │   ├── class-log-manager.php
│   │   ├── class-error-tracker.php
│   │   ├── class-performance-tracker.php
│   │   └── handlers/
│   ├── workflows/
│   │   ├── class-workflow-engine.php
│   │   ├── class-workflow-executor.php
│   │   ├── class-workflow-node.php
│   │   ├── class-workflow-template.php
│   │   └── nodes/
│   │       ├── class-transform-node.php
│   │       ├── class-condition-node.php
│   │       ├── class-action-node.php
│   │       ├── class-data-node.php
│   │       ├── class-loop-node.php
│   │       └── class-delay-node.php
│   ├── etl/
│   │   ├── class-etl-builder.php
│   │   ├── class-data-previewer.php
│   │   └── transformations/
│   ├── validation/
│   │   ├── class-validator.php
│   │   └── rules/
│   ├── endpoint_manager/ (existing, enhanced)
│   └── vendor-loader.php ✅
├── vendor/ (gitignored)
├── tests/
│   ├── bootstrap.php
│   ├── test-endpoint-manager.php
│   ├── test-etl-engine.php
│   └── test-workflow-executor.php
├── docs/
│   ├── README.md
│   ├── installation.md
│   ├── endpoints/
│   ├── webhooks/
│   ├── etl/
│   ├── workflows/
│   └── api-reference/
├── composer.json ✅
├── package.json (for React)
├── phpunit.xml
└── .gitignore ✅
```

## 🗄️ Database Schema

### New Tables

1. **wp_custom_api_action_history**
   - Tracks all executed actions
   - Columns: id, endpoint_id, action_name, action_data, status, executed_at, duration_ms, result

2. **wp_custom_api_cache**
   - Response caching system
   - Columns: id, cache_key, endpoint_id, request_hash, response_data, expires_at, created_at

3. **wp_custom_api_metrics**
   - Performance metrics tracking
   - Columns: id, metric_name, value, tags, recorded_at

4. **wp_custom_api_workflows**
   - Visual workflow definitions
   - Columns: id, name, trigger_type, trigger_config, nodes, connections, status, created_at

5. **wp_custom_api_workflow_executions**
   - Workflow execution history
   - Columns: id, workflow_id, input_data, output_data, status, started_at, completed_at, error_message

### Enhanced Tables

**custom_endpoints** - Added columns:
- `rate_limit_per_minute` (int)
- `rate_limit_per_hour` (int)
- `cache_ttl` (int)
- `queue_async` (tinyint)
- `timeout_seconds` (int)
- `retry_attempts` (int)
- `retry_delay` (int)
- `workflow_id` (bigint)

**webhook_log** - Added columns:
- `request_size` (int)
- `response_size` (int)
- `duration_ms` (int)
- `memory_peak` (int)
- `cpu_time` (float)
- `db_queries` (int)
- `cache_hits` (int)
- `cache_misses` (int)

## 📦 Installation & Setup

### Prerequisites
- PHP 8.1 or higher
- WordPress 6.0 or higher
- Composer installed
- Node.js 16+ and npm (for React components)

### Setup Steps

1. **Install Composer Dependencies**
   ```bash
   cd /path/to/wp-content/plugins/WP_Custom_API
   composer install
   ```

2. **Install Node Dependencies** (for React components, when ready)
   ```bash
   npm install
   ```

3. **Build React Components** (when Phase 5-6 are implemented)
   ```bash
   npm run build
   ```

4. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "WP Custom API"
   - Database tables will be created automatically

5. **Configure Settings**
   - Go to WP Custom API → Settings
   - Configure general settings, authentication, performance options

## 🚦 Implementation Phases

### ✅ PHASE 1: Foundation (Week 1-2) - IN PROGRESS
- [x] Composer dependencies setup
- [ ] Admin menu structure
- [ ] Database schema extensions
- [ ] Asset structure and enqueue system

### ⏳ PHASE 2: Core Endpoint CRUD with Admin UI (Week 3-4)
- [ ] Endpoints List Table
- [ ] Add/Edit forms with tabs
- [ ] Test Interface
- [ ] Enhanced Endpoint Controller

### ⏳ PHASE 3: Job Queue Integration (Week 5-6)
- [ ] WooCommerce Action Scheduler integration
- [ ] Queue Manager and Job Processor
- [ ] Retry logic with exponential backoff
- [ ] Jobs Queue admin page

### ⏳ PHASE 4: Advanced Logging System (Week 7-8)
- [ ] Monolog Logger integration
- [ ] Request/response logging middleware
- [ ] Error tracking system
- [ ] Log Viewer admin page

### ⏳ PHASE 5: Visual Workflow Builder (Week 9-10)
- [ ] Workflow Engine architecture
- [ ] Workflow node types
- [ ] React Workflow Builder UI
- [ ] Workflow execution engine

### ⏳ PHASE 6: Data Transformation Engine (Week 11-12)
- [ ] Visual ETL Builder
- [ ] 50+ transformation types
- [ ] Field mapping interface
- [ ] Data preview system

### ⏳ PHASE 7: Testing & Documentation (Week 13-14)
- [ ] PHPUnit testing infrastructure
- [ ] Integration tests
- [ ] Comprehensive documentation
- [ ] Code quality tools

## 🎨 UI Components

### Admin Pages

1. **Dashboard** - Overview with statistics, charts, recent activity
2. **Endpoints** - List/Add/Edit custom endpoints
3. **Webhooks** - Incoming webhook logs
4. **External Services** - Outgoing API configurations
5. **ETL Templates** - Data transformation pipelines
6. **Workflows** - Visual workflow builder
7. **Jobs Queue** - View and manage queued jobs
8. **Logs** - Request/response/error/system logs
9. **Settings** - Plugin configuration

### React Components (Phase 5-6)

- **WorkflowBuilder** - Drag-and-drop workflow editor
- **ETLBuilder** - Visual ETL pipeline builder
- **FieldMapper** - Visual field mapping
- **RequestTester** - API endpoint testing
- **LogViewer** - Real-time log streaming
- **DataPreview** - Before/after transformation view

## 🔒 Security Enhancements

- **API Key Management**: Hashed storage, rotation support
- **Signature Validation**: HMAC-based webhook security
- **Rate Limiting**: Prevent abuse and DDoS
- **Input Sanitization**: All user inputs sanitized
- **SQL Injection Prevention**: Prepared statements only
- **XSS Prevention**: All outputs escaped
- **Capability Checks**: WordPress capability system
- **Nonce Validation**: All AJAX requests protected

## 📊 Performance Optimizations

- **Object Caching**: In-memory endpoint config caching
- **Response Caching**: Configurable per-endpoint TTL
- **Database Indexing**: Optimized for high-volume queries
- **Async Processing**: Heavy operations queued
- **Batch Processing**: Bulk webhook processing
- **Connection Pooling**: Reuse HTTP connections

## 🔍 Monitoring & Metrics

### Performance Metrics
- Request duration (p50, p95, p99)
- Throughput (requests per minute)
- Error rate
- Queue depth
- Cache hit ratio
- Database query count
- Memory usage

### Success Criteria
- ✅ Handle 10,000+ webhooks/hour
- ✅ Process 95% of jobs within 5 seconds
- ✅ <100ms average request time
- ✅ 99.9% uptime
- ✅ <0.1% error rate

## 🤝 Contributing

This is a major rebuild project. Key areas for contribution:

1. **UI/UX Design** - React components, admin interface
2. **Testing** - Unit tests, integration tests
3. **Documentation** - User guides, API docs
4. **Transformations** - Additional data transformation functions
5. **Workflow Nodes** - New workflow node types
6. **Performance** - Optimization and benchmarking

## 📖 Reference Projects

This rebuild is inspired by and learns from:

- [WooCommerce Action Scheduler](https://github.com/woocommerce/action-scheduler) - Job queue patterns
- [Alleyinteractive WP Path Dispatch](https://github.com/alleyinteractive/wp-path-dispatch) - Routing patterns
- [Alleyinteractive Feed Consumer](https://github.com/alleyinteractive/feed-consumer) - Data ingestion
- [ZAO WP REST Starter](https://github.com/zao-web/WP-REST-Starter) - REST API patterns
- [Hookly Webhook Automator](https://wordpress.org/plugins/hookly-webhook-automator/) - UI/UX inspiration
- [WP Custom API Creator](https://github.com/dexit/wp-custom-api-creator) - Core concept

## 📝 Next Steps

1. **Run Composer Install**
   ```bash
   composer install
   ```

2. **Review Implementation Plan** - See detailed plan document

3. **Begin Phase 1 Continuation** - Admin menu structure, database schema

4. **Set up Development Environment** - Testing, linting, etc.

## 📞 Support

For questions or issues during the rebuild:
- Create an issue in the repository
- Review the comprehensive implementation plan
- Check the docs/ directory for detailed guides

---

**Built with ❤️ by the WP Custom API Team**
**Version:** 2.0.0-alpha (Rebuild in Progress)
**License:** GPL-2.0-or-later
