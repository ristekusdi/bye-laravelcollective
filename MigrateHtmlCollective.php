<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateHtmlCollective extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-html-collective';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Html laravelcollective/html syntax to blade HTML.';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        /**
         * Laravel Collective Html::script and Html::style Migration Script
         * 
         * This script converts Laravel Collective's Html::script and Html::style
         * helpers to plain HTML tags in Blade templates.
         * 
         * Usage: php migrate-html-helpers.php /path/to/views
         */

        // Configuration
        $viewsPath = resource_path('views');

        // Get all blade files recursively
        $bladeFiles = [];
        $this->findBladeFiles($viewsPath, $bladeFiles);

        echo "Found " . count($bladeFiles) . " Blade files to process.\n";
        
        $totalReplacements = 0;
        $modifiedFiles = 0;

        // Process each file
        foreach ($bladeFiles as $file) {
            $content = file_get_contents($file);
            $originalContent = $content;

            // Count initial HTML elements
            $initialHtmlElements = $this->countHtmlElements($content);

            // Perform replacements
            $content = $this->convertHtmlScript($content);
            $content = $this->convertHtmlStyle($content);
            $content = $this->convertHtmlImage($content);
            $content = $this->convertHtmlLink($content);

            // Count remaining HTML elements to determine success
            $remainingHtmlElements = $this->countHtmlElements($content);
            $replacementsInFile = $initialHtmlElements - $remainingHtmlElements;
            $totalReplacements += $replacementsInFile;

            // Save if changed
            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $modifiedFiles++;
                echo "Modified: $file ($replacementsInFile replacements)\n";
            }
        }

        echo "\nSummary:\n";
        echo "Files processed: " . count($bladeFiles) . "\n";
        echo "Files modified: $modifiedFiles\n";
        echo "Total replacements: $totalReplacements\n";

        if ($totalReplacements > 0) {
            echo "\nReview changes carefully before deploying.\n";
        }
    }

    /**
     * Functions
     */

    // Find all blade files recursively
    function findBladeFiles($dir, &$results)
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = realpath($dir . DIRECTORY_SEPARATOR . $file);

            if (is_dir($path)) {
                $this->findBladeFiles($path, $results);
            } else if (
                pathinfo($path, PATHINFO_EXTENSION) === 'blade.php' ||
                (pathinfo($path, PATHINFO_EXTENSION) === 'php' && strpos(file_get_contents($path), 'Html::') !== false)
            ) {
                $results[] = $path;
            }
        }
    }

    // Count HTML elements to measure success
    function countHtmlElements($content)
    {
        preg_match_all('/Html::[a-zA-Z]+/', $content, $matches);
        return count($matches[0]);
    }

    // Html::script
    function convertHtmlScript($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Html::script\(\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $src = trim($matches[1]);
                $attributes = isset($matches[2]) ? $this->parseOptionsArray($matches[2]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If src is a string literal
                if (preg_match('/^[\'"].*[\'"]$/', $src)) {
                    $src = trim($src, '\'"');
                    return "<script src=\"$src\"$attributesStr></script>";
                }
                // If src is a variable or function
                else {
                    return "<script src=\"{{ $src }}\"$attributesStr></script>";
                }
            },
            $content
        );
    }

    // Html::style
    function convertHtmlStyle($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Html::style\(\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $href = trim($matches[1]);
                $attributes = isset($matches[2]) ? $this->parseOptionsArray($matches[2]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If href is a string literal
                if (preg_match('/^[\'"].*[\'"]$/', $href)) {
                    $href = trim($href, '\'"');
                    return "<link href=\"$href\" rel=\"stylesheet\"$attributesStr>";
                }
                // If href is a variable or function
                else {
                    return "<link href=\"{{ $href }}\" rel=\"stylesheet\"$attributesStr>";
                }
            },
            $content
        );
    }

    // Html::image
    function convertHtmlImage($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Html::image\(\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $src = trim($matches[1]);
                $alt = isset($matches[2]) ? trim($matches[2], '\'"') : '';
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If src is a string literal
                if (preg_match('/^[\'"].*[\'"]$/', $src)) {
                    $src = trim($src, '\'"');

                    // If alt is a variable
                    if (preg_match('/^[$]/', $alt)) {
                        return "<img src=\"$src\" alt=\"{{ $alt }}\"$attributesStr>";
                    } else {
                        return "<img src=\"$src\" alt=\"$alt\"$attributesStr>";
                    }
                }
                // If src is a variable or function
                else {
                    // If alt is a variable
                    if (preg_match('/^[$]/', $alt)) {
                        return "<img src=\"{{ $src }}\" alt=\"{{ $alt }}\"$attributesStr>";
                    } else {
                        return "<img src=\"{{ $src }}\" alt=\"$alt\"$attributesStr>";
                    }
                }
            },
            $content
        );
    }

    // Html::link
    function convertHtmlLink($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Html::link\(\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?(?:\s*,\s*(\[.*?\]))?(?:\s*,\s*(true|false))?\s*\)\s*!!}/',
            function ($matches) {
                $url = trim($matches[1]);
                $title = isset($matches[2]) ? trim($matches[2]) : null;
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $secure = isset($matches[4]) && $matches[4] === 'true';
                $attributesStr = $this->getAttributesString($attributes);

                // Handle URL transformation
                if (preg_match('/^[\'"].*[\'"]$/', $url)) {
                    // String literal URL
                    $url = trim($url, '\'"');
                    if ($url && $url[0] === '/') {
                        // Assume it's a relative URL
                        $url = "{{ url('$url'" . ($secure ? ", true" : "") . ") }}";
                    }
                } else {
                    // Variable or function URL
                    $url = "{{ $url }}";
                }

                // Handle title
                if ($title === null || $title === 'null' || $title === 'NULL') {
                    $title = $url;
                } elseif (preg_match('/^[$]/', $title)) {
                    // Variable title
                    $title = "{{ $title }}";
                } else {
                    // String literal title
                    $title = trim($title, '\'"');
                }

                return "<a href=\"$url\"$attributesStr>$title</a>";
            },
            $content
        );
    }

    // Parse options array like ['class' => 'btn', 'id' => 'submit']
    function parseOptionsArray($string)
    {
        $options = [];

        // This is a simplified parser and might not handle all cases perfectly
        preg_match_all('/[\'"]([^\'"]++)[\'"](?:\s*=>\s*([\'"][^\'"]++[\'"]|[^,\[\]]+))?/', $string, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] ?? true;

            // If it's wrapped in quotes, remove them
            if (preg_match('/^[\'"].*[\'"]$/', $value)) {
                $value = trim($value, '\'"');
            }

            $options[$key] = $value;
        }

        return $options;
    }

    // Build HTML attributes string from options array
    function getAttributesString($options, $exclude = [])
    {
        $attributesStr = '';

        foreach ($options as $key => $value) {
            if (in_array($key, $exclude)) {
                continue;
            }

            if ($value === true) {
                $attributesStr .= " $key";
            } elseif ($value !== false && $value !== null) {
                // If it looks like a variable
                if (preg_match('/^[$]/', $value)) {
                    $attributesStr .= " $key=\"{{ $value }}\"";
                } else {
                    $attributesStr .= " $key=\"$value\"";
                }
            }
        }

        return $attributesStr;
    }
}
