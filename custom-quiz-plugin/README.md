# Custom Quiz Plugin for WordPress

A comprehensive WordPress plugin that allows you to create interactive quizzes with custom questions and route users to specific result pages based on their answers.

## Features

- **Custom Post Types**: Separate post types for Quiz Questions and Quizzes
- **Flexible Question Options**: Add unlimited answer options to each question
- **Smart Result Routing**: Map answer patterns to specific WordPress pages
- **Multiple Condition Types**:
  - Most Common Answer (e.g., "Mostly A's")
  - Score Range (e.g., "0-50")
  - Contains Value (e.g., "Has at least one B")
- **AJAX Form Submission**: Smooth user experience without page reloads
- **Responsive Design**: Works perfectly on desktop and mobile devices
- **Easy Integration**: Simple shortcode to embed quizzes anywhere

## Installation

1. Download or clone this repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin panel
4. You'll see two new menu items: "Quiz Questions" and "Quizzes"

## File Structure

```
custom-quiz-plugin/
├── quiz-plugin.php                          # Main plugin file
├── includes/
│   ├── class-quiz-post-types.php           # Registers custom post types
│   ├── class-quiz-meta-boxes.php           # Handles admin meta boxes
│   ├── class-quiz-shortcode.php            # Frontend quiz rendering
│   └── class-quiz-ajax.php                 # AJAX handling and result calculation
├── assets/
│   ├── css/
│   │   ├── admin-style.css                 # Admin area styles
│   │   └── frontend-style.css              # Frontend quiz styles
│   └── js/
│       ├── admin-script.js                 # Admin functionality
│       └── frontend-script.js              # Frontend quiz handling
└── README.md                                # This file
```

## Usage Guide

### Step 1: Create Result Pages

1. Go to **Pages > Add New** in WordPress
2. Create static pages for each possible quiz result
3. For example:
   - "Result: Extrovert Personality"
   - "Result: Introvert Personality"
   - "Result: Ambivert Personality"
4. Add content describing each result type
5. Publish the pages

### Step 2: Create Quiz Questions

1. Go to **Quiz Questions > Add New**
2. Enter your question as the title (e.g., "Do you enjoy social gatherings?")
3. In the "Question Options" meta box:
   - Add answer options (e.g., "Yes, I love them!", "Sometimes", "No, I prefer alone time")
   - Assign values to each option (e.g., A, B, C)
   - Click "Add Option" to add more options
4. Publish the question
5. Repeat for all your quiz questions

### Step 3: Create a Quiz

1. Go to **Quizzes > Add New**
2. Enter a title (e.g., "Personality Type Quiz")
3. Add a description in the content editor (optional)
4. In the "Quiz Questions" meta box:
   - Check the questions you want to include
   - Questions will appear in the order they're selected
5. In the "Result Page Mapping" meta box:
   - Click "Add Result Mapping"
   - Configure each rule:
     - **Rule Name**: Description (e.g., "Mostly A's - Extrovert")
     - **Condition**: Choose the logic type
       - **Most Common Answer**: User gets most answers as this value
       - **Score Range**: Based on calculated score
       - **Contains Value**: If answers include this value
     - **Value**: The value to match (e.g., "A" or "0-50")
     - **Result Page**: Select the WordPress page to redirect to
6. Publish the quiz

### Step 4: Display the Quiz

1. Copy the quiz ID from the quiz list or edit screen
2. Add the shortcode to any page or post:
   ```
   [quiz id="123"]
   ```
   (Replace 123 with your actual quiz ID)
3. Publish or update the page

## Result Mapping Examples

### Example 1: Most Common Answer
- **Condition Type**: Most Common Answer
- **Value**: A
- **Result Page**: "Extrovert Result"
- **Logic**: If user selected "A" for most questions, redirect to Extrovert page

### Example 2: Score Range
- **Condition Type**: Score Range
- **Value**: 0-10
- **Result Page**: "Beginner Level"
- **Logic**: If calculated score is between 0-10, redirect to Beginner page
- **Note**: Scores are calculated as A=1, B=2, C=3, etc., then summed

### Example 3: Contains Value
- **Condition Type**: Contains Value
- **Value**: C
- **Result Page**: "Special Category"
- **Logic**: If user selected "C" at least once, redirect to Special Category page

## How Results Are Calculated

The plugin evaluates result mappings in the order they're defined:

1. It checks each mapping condition from top to bottom
2. When a condition matches, it redirects to that result page
3. If no conditions match, it uses the first result page as fallback

**Best Practice**: Order your mappings from most specific to least specific.

## Customization

### Styling

You can customize the appearance by:

1. Overriding CSS in your theme's stylesheet
2. Editing `/assets/css/frontend-style.css` directly
3. Using custom CSS classes:
   - `.custom-quiz-container` - Main quiz wrapper
   - `.quiz-question` - Individual question wrapper
   - `.quiz-option` - Answer option label
   - `.quiz-submit-btn` - Submit button

### Modifying Logic

To customize result calculation logic:

1. Edit `/includes/class-quiz-ajax.php`
2. Modify the `calculate_result()` method
3. Add custom condition types in the switch statement

## Hooks & Filters

For developers who want to extend functionality:

```php
// Modify quiz results before redirect
add_filter('custom_quiz_result_page', function($page_id, $quiz_id, $answers) {
    // Your custom logic
    return $page_id;
}, 10, 3);
```

## Troubleshooting

### Quiz doesn't appear
- Verify the quiz ID in your shortcode
- Check that questions are selected in the quiz settings
- Ensure the quiz is published

### Results not redirecting
- Verify result page mappings are configured
- Check that result pages are published
- Ensure condition values match your question option values

### Styling issues
- Clear browser cache
- Check for theme CSS conflicts
- Verify CSS files are loading (check browser console)

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Modern browser with JavaScript enabled

## Support

For issues or questions:
1. Check this README for common solutions
2. Review the code comments in each file
3. Test with a default WordPress theme to rule out conflicts

## License

GPL v2 or later

## Credits

Created as a custom WordPress quiz solution with flexible result routing capabilities.
