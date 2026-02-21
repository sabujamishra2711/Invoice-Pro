# Invoice Template System

## Overview
We've implemented 10 different unique and attractive invoice templates for the invoice management system. Each template offers a distinct visual style to suit different business needs and preferences.

## Available Templates

### 1. Default
- Standard professional invoice template
- Clean, readable layout
- Classic business design

### 2. Minimal
- Clean and minimalist design
- Focus on essential information
- Simple typography

### 3. Modern
- Contemporary and sleek design
- Vibrant accents and colors
- Up-to-date visual elements

### 4. Classic
- Traditional formal layout
- Elegant typography
- Conservative business appearance

### 5. Professional
- Business-oriented design
- Suitable for corporate environments
- Structured information layout

### 6. Elegant
- Sophisticated and refined appearance
- Subtle visual details
- Premium feel

### 7. Creative
- Artistic and colorful design
- Unique visual elements
- Eye-catching aesthetics

### 8. Corporate
- Formal business template
- Structured layout
- Professional appearance

### 9. Simple
- Basic clean layout
- Readability focused
- Clarity prioritized

### 10. Colorful
- Vibrant and eye-catching design
- Gradient effects
- Dynamic visual appeal

### 11. Luxury
- Premium and elegant appearance
- Gold accents
- High-end aesthetic

## How to Use

### API Usage
To generate an invoice with a specific template, add the `template` parameter to your API request:

```
GET /api/index.php?route=invoice.pdf&id={invoice_id}&template={template_name}
```

Examples:
- Default template: `/api/index.php?route=invoice.pdf&id=123`
- Modern template: `/api/index.php?route=invoice.pdf&id=123&template=modern`
- Creative template: `/api/index.php?route=invoice.pdf&id=123&template=creative`

### Template Parameter Values
- `default` - Standard professional template
- `minimal` - Clean minimalist design
- `modern` - Contemporary layout
- `classic` - Traditional formal style
- `professional` - Business-oriented design
- `elegant` - Sophisticated appearance
- `creative` - Artistic colorful design
- `corporate` - Formal business template
- `simple` - Basic clean layout
- `colorful` - Vibrant design with gradients
- `luxury` - Premium elegant appearance

## Technical Implementation

The template system is implemented in the `PDFService` class with:

1. A `generateHTML()` method that routes to the appropriate template function based on the template parameter
2. Individual template functions for each of the 11 designs
3. Consistent data structure across all templates
4. Responsive and professional styling for each design

## Features

- All templates maintain the same data structure and information
- Each template is optimized for printing and PDF generation
- Responsive design considerations
- Professional typography choices
- Appropriate color schemes for each style
- Consistent brand information placement

## Demo

A demonstration page is available at `demo_templates.php` that showcases all available templates with preview and download options.