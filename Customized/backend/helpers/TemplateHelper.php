<?php

/**
 * Helper class to manage invoice templates
 */
class TemplateHelper
{
    /**
     * Get all available invoice templates
     */
    public static function getAvailableTemplates()
    {
        return [
            'default' => [
                'name' => 'Default',
                'description' => 'Standard professional invoice template'
            ],
            'minimal' => [
                'name' => 'Minimal',
                'description' => 'Clean and minimalist design'
            ],
            'modern' => [
                'name' => 'Modern',
                'description' => 'Contemporary and sleek design'
            ],
            'classic' => [
                'name' => 'Classic',
                'description' => 'Traditional formal layout'
            ],
            'professional' => [
                'name' => 'Professional',
                'description' => 'Business-oriented design'
            ],
            'elegant' => [
                'name' => 'Elegant',
                'description' => 'Sophisticated and refined appearance'
            ],
            'creative' => [
                'name' => 'Creative',
                'description' => 'Artistic and colorful design'
            ],
            'corporate' => [
                'name' => 'Corporate',
                'description' => 'Formal business template'
            ],
            'simple' => [
                'name' => 'Simple',
                'description' => 'Basic clean layout'
            ],
            'colorful' => [
                'name' => 'Colorful',
                'description' => 'Vibrant and eye-catching design'
            ],
            'luxury' => [
                'name' => 'Luxury',
                'description' => 'Premium and elegant appearance'
            ]
        ];
    }

    /**
     * Get template options for dropdown
     */
    public static function getTemplateOptions()
    {
        $templates = self::getAvailableTemplates();
        $options = [];

        foreach ($templates as $key => $template) {
            $options[] = [
                'value' => $key,
                'label' => $template['name'],
                'description' => $template['description']
            ];
        }

        return $options;
    }
}
