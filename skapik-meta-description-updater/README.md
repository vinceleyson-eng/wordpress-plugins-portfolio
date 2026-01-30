# 🔧 Skapik Meta Description Bulk Updater

**Fix for Yoast SEO Meta Description API Issues**

Specialized plugin that solves the critical problem where WordPress REST API updates the wrong field for Yoast SEO meta descriptions. Essential for bulk meta description updates.

## 🎯 Problem Solved

**Issue**: Standard WordPress REST API updates `excerpt` field instead of Yoast's `_yoast_wpseo_metadesc` field.

**Solution**: Custom endpoint that properly updates Yoast SEO meta descriptions.

## ⚡ Features

- ✅ **Custom REST endpoint**: `/wp-json/skapik/v1/bulk-update-meta`
- ✅ **Proper Yoast integration** updates correct field
- ✅ **Bulk processing** for multiple URLs
- ✅ **WordPress backend compatibility** 
- ✅ **Speed optimization** 20+ URLs in 30 seconds

## 🛠️ Usage

```bash
curl -X POST "yoursite.com/wp-json/skapik/v1/bulk-update-meta" \
  -H "Content-Type: application/json" \
  -d '{
    "updates": [
      {"post_id": 123, "meta_description": "New meta description"}
    ]
  }'
```

## 🎯 Professional Value

**Critical for SEO agencies** managing Yoast-powered sites. This is the **only reliable way** to bulk update meta descriptions without breaking Yoast's interface.

*Essential tool for Yoast SEO bulk operations.*