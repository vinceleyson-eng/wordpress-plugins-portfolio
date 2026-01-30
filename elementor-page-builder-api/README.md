# 🎨 Elementor Page Builder API

**Programmatic Elementor Page Creation**

REST API endpoint for inserting Elementor page data programmatically. Perfect for automated page generation, bulk content creation, and integration with external systems.

## ⚡ Features

- ✅ **REST API endpoint**: `/wp-json/elementor-builder/v1/create-page`
- ✅ **JSON data input** for Elementor page structure
- ✅ **Programmatic page creation** with meta handling
- ✅ **External system integration** capabilities

## 🛠️ Usage

```bash
curl -X POST "yoursite.com/wp-json/elementor-builder/v1/create-page" \
  -H "Content-Type: application/json" \
  -d '{
    "page_id": 123,
    "elementor_data": [...]
  }'
```

## 🎯 Use Cases

- **Automated landing pages** from marketing campaigns
- **Bulk page creation** from external data
- **Template deployment** across multiple sites
- **API-driven content** management

*Programmatic Elementor page creation for automation.*