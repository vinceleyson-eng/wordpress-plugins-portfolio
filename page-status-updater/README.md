# 📄 Page Status Updater

**Simple API for WordPress Page Status Management**

Lightweight REST API endpoint for updating WordPress page status programmatically. Perfect for workflow automation and external system integrations.

## ⚡ Features

- ✅ **REST API endpoint**: `/wp-json/page-status/v1/update`
- ✅ **Simple JSON input** for status updates
- ✅ **Workflow automation** integration
- ✅ **Lightweight** with minimal overhead

## 🛠️ Usage

```bash
curl -X POST "yoursite.com/wp-json/page-status/v1/update" \
  -H "Content-Type: application/json" \
  -d '{"page_id": 123, "status": "publish"}'
```

## 🎯 Use Cases

- **Content approval workflows**
- **Scheduled publishing** from external systems
- **Bulk status updates** for maintenance
- **Project management tool** integration

*Simple page status management via REST API.*