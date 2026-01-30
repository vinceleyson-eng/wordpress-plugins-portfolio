# 📍 Geo-Located Store Finder

**Advanced Location Technology for Multi-Location Businesses**

Professional store locator with HTML5 geolocation, distance calculations, and smart fallback systems. Perfect for retail chains, service providers, and any business with multiple physical locations.

## 🌟 Core Features

### **Intelligent Location Detection**
- ✅ **HTML5 Geolocation** with user permission handling
- ✅ **IP-based fallback** when GPS is unavailable
- ✅ **Address search** for manual location entry
- ✅ **Distance calculations** using Haversine formula

### **Professional Experience**
- ✅ **AJAX-powered** store loading
- ✅ **Mobile-responsive** design
- ✅ **Automatic sorting** by proximity to user
- ✅ **Custom database** for coordinate storage
- ✅ **Google Maps integration** for geocoding

## 🛠️ Setup & Usage

### **1. Store Data Entry**
```
Posts → Add New → Store Location
Address: "123 Main St, Springfield, IL 62701"
Phone: "(555) 123-4567"
Hours: "Mon-Fri: 9am-6pm"
// Plugin automatically geocodes to coordinates
```

### **2. Display Store Finder**
```php
// Simple shortcode
[nearby_stores]

// With parameters
[nearby_stores limit="5" radius="25"]
```

### **3. Google Maps API**
```php
// Add to wp-config.php
define('GOOGLE_MAPS_API_KEY', 'your-key-here');
```

## 💼 Business Applications

### **Retail Chains**
- Customer store locator with driving directions
- Product availability by location
- Store hours and contact information
- "Find nearest location" functionality

### **Service Providers**
- **Medical/Dental practices** with multiple offices
- **Auto service centers** and repair shops
- **Financial services** with branch locations
- **Professional services** with regional coverage

## 🎯 Technical Features

### **Database Optimization**
```sql
-- Custom table with spatial indexing
wp_store_locations (id, post_id, address, latitude, longitude)
KEY coordinates (latitude, longitude)
```

### **Distance Algorithm**
```php
// Haversine formula for precise calculations
$distance = calculate_distance($user_lat, $user_lng, $store_lat, $store_lng);
```

## 🎨 Customization

### **Frontend Styling**
```css
.geo-stores-wrapper { /* Main container */ }
.store-card { /* Individual store */ }
.store-distance { /* Distance display */ }
.geo-stores-loading { /* Loading state */ }
```

### **Admin Integration**
- **Meta boxes** for easy address entry
- **Automatic geocoding** on save
- **Bulk operations** for multiple stores
- **Address verification** with Google API

## 📊 Perfect For

- **Retail chains** with 10+ locations
- **Service providers** with regional coverage
- **Franchise operations** with dealer networks
- **Healthcare systems** with multiple facilities
- **Financial institutions** with branch locations

**Help customers find you with precision and professional presentation.**

---

*Professional location technology for modern businesses.*