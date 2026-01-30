# 🧠 Custom Quiz Plugin

**Interactive Content & Lead Generation System**

Create engaging quizzes with smart result routing that drives conversions and captures leads. Perfect for personality tests, assessments, product recommendations, and interactive marketing campaigns.

## 🌟 Key Features

### **Smart Result Logic**
- ✅ **Most Common Answer** routing ("Mostly A's" logic)
- ✅ **Score Range** calculations (0-50, 51-100, etc.)
- ✅ **Contains Value** detection (has specific answers)
- ✅ **Multiple condition types** for complex routing

### **Professional Experience**
- ✅ **AJAX-powered** submission (no page reloads)
- ✅ **Mobile-responsive** design
- ✅ **Shortcode integration:** `[quiz id="123"]`
- ✅ **Custom post types** for Questions and Quizzes
- ✅ **WordPress page routing** for results

## 🛠️ Setup Guide

### **1. Create Result Pages**
```
Pages → Add New → Create pages like:
- "Extrovert Personality Result" 
- "Introvert Personality Result"
- "Product Recommendation A"
```

### **2. Build Questions**
```
Quiz Questions → Add New
Title: "Do you enjoy social gatherings?"
Options:
- "Yes, I love them!" (Value: A)
- "Sometimes" (Value: B)  
- "No, I prefer alone time" (Value: C)
```

### **3. Create Quiz**
```
Quizzes → Add New
- Select your questions
- Map results: "Most Common A" → "Extrovert Page"
- Publish and copy quiz ID
```

### **4. Display Quiz**
```html
[quiz id="123"]
```

## 💼 Business Applications

### **Lead Generation**
- Personality Quiz → Email Capture → Product Recommendations
- Assessment Tool → Lead Qualification → Sales Follow-up
- Product Finder → User Preferences → Targeted Offers

### **Content Marketing**
- **Engagement:** Interactive content increases time on site
- **Viral Potential:** Shareable results drive social traffic  
- **Data Collection:** Learn audience preferences
- **Segmentation:** Route users to relevant content

## 🎯 Result Examples

### **E-commerce Product Finder**
```
Quiz: "Find Your Perfect Skincare Routine"
Mostly A (Dry Skin) → Moisturizing Products Page
Mostly B (Oily Skin) → Oil-Control Products Page
Mostly C (Sensitive) → Gentle Products Page
```

### **Service Recommendation**
```
Quiz: "What Marketing Package Do You Need?"
Score 0-30 → "Starter Package"
Score 31-70 → "Professional Package"  
Score 71-100 → "Enterprise Package"
```

## 🎨 Customization

### **CSS Styling**
```css
.custom-quiz-container { /* Main wrapper */ }
.quiz-question { /* Question container */ }
.quiz-option { /* Answer option */ }
.quiz-submit-btn { /* Submit button */ }
```

### **WordPress Hooks**
```php
// Modify quiz results
add_filter('custom_quiz_result_page', function($page_id, $quiz_id, $answers) {
    // Custom routing logic
    return $page_id;
}, 10, 3);
```

## 📊 Perfect For

- **Marketing agencies** building client campaigns
- **E-commerce stores** driving product discovery  
- **Educational sites** creating assessments
- **Service providers** qualifying leads
- **Content creators** increasing engagement

**Transform passive visitors into engaged leads with interactive quizzes.**

---

*Professional quiz system for modern marketing.*