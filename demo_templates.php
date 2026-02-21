<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Template Demo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .template-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .template-name {
            font-weight: bold;
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .template-desc {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .template-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }

        .btn-preview {
            background-color: #3498db;
            color: white;
        }

        .btn-download {
            background-color: #2ecc71;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .instructions {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .instructions h2 {
            margin-top: 0;
            color: #2c3e50;
        }

        .api-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Invoice Template Showcase</h1>

        <div class="instructions">
            <h2>How to Use Different Templates</h2>
            <p>You can generate invoices with different templates by specifying the template parameter in the API request:</p>
            <div class="api-info">
                GET /api/index.php?route=invoice.pdf&amp;id={invoice_id}&amp;template={template_name}
            </div>
            <p>Available templates: default, minimal, modern, classic, professional, elegant, creative, corporate, simple, colorful, luxury</p>
        </div>

        <div class="template-grid">
            <div class="template-card">
                <div class="template-name">Default</div>
                <div class="template-desc">Standard professional invoice template with a clean, readable layout.</div>
                <div class="template-actions">
                    <a href="?preview=default" class="btn btn-preview">Preview</a>
                    <a href="?download=default" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Minimal</div>
                <div class="template-desc">Clean and minimalist design focusing on essential information.</div>
                <div class="template-actions">
                    <a href="?preview=minimal" class="btn btn-preview">Preview</a>
                    <a href="?download=minimal" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Modern</div>
                <div class="template-desc">Contemporary and sleek design with vibrant accents.</div>
                <div class="template-actions">
                    <a href="?preview=modern" class="btn btn-preview">Preview</a>
                    <a href="?download=modern" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Classic</div>
                <div class="template-desc">Traditional formal layout with elegant typography.</div>
                <div class="template-actions">
                    <a href="?preview=classic" class="btn btn-preview">Preview</a>
                    <a href="?download=classic" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Professional</div>
                <div class="template-desc">Business-oriented design suitable for corporate environments.</div>
                <div class="template-actions">
                    <a href="?preview=professional" class="btn btn-preview">Preview</a>
                    <a href="?download=professional" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Elegant</div>
                <div class="template-desc">Sophisticated and refined appearance with subtle details.</div>
                <div class="template-actions">
                    <a href="?preview=elegant" class="btn btn-preview">Preview</a>
                    <a href="?download=elegant" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Creative</div>
                <div class="template-desc">Artistic and colorful design with unique visual elements.</div>
                <div class="template-actions">
                    <a href="?preview=creative" class="btn btn-preview">Preview</a>
                    <a href="?download=creative" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Corporate</div>
                <div class="template-desc">Formal business template with structured layout.</div>
                <div class="template-actions">
                    <a href="?preview=corporate" class="btn btn-preview">Preview</a>
                    <a href="?download=corporate" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Simple</div>
                <div class="template-desc">Basic clean layout focused on readability and clarity.</div>
                <div class="template-actions">
                    <a href="?preview=simple" class="btn btn-preview">Preview</a>
                    <a href="?download=simple" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Colorful</div>
                <div class="template-desc">Vibrant and eye-catching design with gradient effects.</div>
                <div class="template-actions">
                    <a href="?preview=colorful" class="btn btn-preview">Preview</a>
                    <a href="?download=colorful" class="btn btn-download">Download</a>
                </div>
            </div>

            <div class="template-card">
                <div class="template-name">Luxury</div>
                <div class="template-desc">Premium and elegant appearance with gold accents.</div>
                <div class="template-actions">
                    <a href="?preview=luxury" class="btn btn-preview">Preview</a>
                    <a href="?download=luxury" class="btn btn-download">Download</a>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>Different invoice templates to suit various business needs and personal preferences.</p>
        </div>
    </div>
</body>

</html>