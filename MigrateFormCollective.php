<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateFormCollective extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-form-collective';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Form laravelcollective/html syntax to blade HTML.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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

            // Count initial form elements
            $initialFormElements = $this->countFormElements($content);

            // Perform replacements
            $content = $this->convertFormOpen($content);
            $content = $this->convertFormModel($content);
            $content = $this->convertFormClose($content);
            $content = $this->convertFormText($content);
            $content = $this->convertFormTextarea($content);
            $content = $this->convertFormPassword($content);
            $content = $this->convertFormEmail($content);
            $content = $this->convertFormNumber($content);
            $content = $this->convertFormHidden($content);
            $content = $this->convertFormSelect($content);
            $content = $this->convertFormCheckbox($content);
            $content = $this->convertFormRadio($content);
            $content = $this->convertFormFile($content);
            $content = $this->convertFormSubmit($content);
            $content = $this->convertFormLabel($content);

            // Count remaining form elements to determine success
            $remainingFormElements = $this->countFormElements($content);
            $replacementsInFile = $initialFormElements - $remainingFormElements;
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
            echo "\nReview changes carefully before deploying. Some complex form elements may need manual adjustments.\n";
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
                (pathinfo($path, PATHINFO_EXTENSION) === 'php' && strpos(file_get_contents($path), 'Form::') !== false)
            ) {
                $results[] = $path;
            }
        }
    }

    // Count form elements to measure success
    function countFormElements($content)
    {
        preg_match_all('/Form::[a-zA-Z]+/', $content, $matches);
        return count($matches[0]);
    }

    // Form::open
    function convertFormOpen($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::open\(\s*(\[.*?\])\s*\)\s*!!}/',
            function ($matches) {
                // Parse the options array
                $options = $this->parseOptionsArray($matches[1]);

                $method = $options['method'] ?? 'POST';
                $route = $options['route'] ?? null;
                $url = $options['url'] ?? null;
                $files = isset($options['files']) && $options['files'] ? ' enctype="multipart/form-data"' : '';
                $attributes = $this->getAttributesString($options, ['method', 'route', 'url', 'files']);

                // Determine the action
                $action = '';
                if ($route) {
                    $action = "{{ route('$route') }}";
                } elseif ($url) {
                    $action = "{{ url('$url') }}";
                }

                // Handle non-standard HTTP methods (PUT, DELETE, etc.)
                $hiddenMethod = '';
                $formMethod = 'POST';
                if (strtoupper($method) !== 'GET' && strtoupper($method) !== 'POST') {
                    $formMethod = 'POST';
                    $hiddenMethod = "@method('$method')";
                } else {
                    $formMethod = $method;
                }

                $csrf = strtoupper($formMethod) !== 'GET' ? '@csrf' : '';

                return "<form method=\"$formMethod\" action=\"$action\"$files$attributes>\n    $csrf\n    $hiddenMethod";
            },
            $content
        );
    }

    // Form::model
    function convertFormModel($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::model\(\s*(\$[a-zA-Z0-9_]+)\s*,\s*(\[.*?\])\s*\)\s*!!}/',
            function ($matches) {
                $model = $matches[1];
                $options = $this->parseOptionsArray($matches[2]);

                $method = $options['method'] ?? 'POST';
                $route = $options['route'] ?? null;
                $url = $options['url'] ?? null;
                $files = isset($options['files']) && $options['files'] ? ' enctype="multipart/form-data"' : '';
                $attributes = $this->getAttributesString($options, ['method', 'route', 'url', 'files']);

                // Determine the action
                $action = '';
                if ($route) {
                    $action = "{{ route('$route') }}";
                } elseif ($url) {
                    $action = "{{ url('$url') }}";
                }

                // Handle non-standard HTTP methods (PUT, DELETE, etc.)
                $hiddenMethod = '';
                $formMethod = 'POST';
                if (strtoupper($method) !== 'GET' && strtoupper($method) !== 'POST') {
                    $formMethod = 'POST';
                    $hiddenMethod = "@method('$method')";
                } else {
                    $formMethod = $method;
                }

                $csrf = strtoupper($formMethod) !== 'GET' ? '@csrf' : '';

                return "<form method=\"$formMethod\" action=\"$action\"$files$attributes>\n" .
                    "    {{-- Converted from Form::model with $model; manually check all fields are properly bound --}}\n" .
                    "    $csrf\n" .
                    "    $hiddenMethod";
            },
            $content
        );
    }

    // Form::close
    function convertFormClose($content)
    {
        return preg_replace(
            '/\{!!\s*Form::close\(\)\s*!!}/',
            '</form>',
            $content
        );
    }

    // Form::text
    function convertFormText($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::text\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If $value looks like a variable (no quotes)
                if (preg_match('/^[$]/', $value) || $value === 'null' || $value === 'NULL') {
                    return "<input type=\"text\" name=\"$name\" value=\"{{ $value ?? '' }}\"$attributesStr>";
                } else {
                    // It's a literal value
                    $value = trim($value, '\'"');
                    return "<input type=\"text\" name=\"$name\" value=\"$value\"$attributesStr>";
                }
            },
            $content
        );
    }

    // Form::textarea
    function convertFormTextarea($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::textarea\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If $value looks like a variable
                if (preg_match('/^[$]/', $value) || $value === 'null' || $value === 'NULL') {
                    return "<textarea name=\"$name\"$attributesStr>{{ $value ?? '' }}</textarea>";
                } else {
                    // It's a literal value
                    $value = trim($value, '\'"');
                    return "<textarea name=\"$name\"$attributesStr>$value</textarea>";
                }
            },
            $content
        );
    }

    // Form::password
    function convertFormPassword($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::password\(\s*([\'"][^\'"]++[\'"])(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $attributes = isset($matches[2]) ? $this->parseOptionsArray($matches[2]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                return "<input type=\"password\" name=\"$name\"$attributesStr>";
            },
            $content
        );
    }

    // Form::email
    function convertFormEmail($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::email\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If $value looks like a variable
                if (preg_match('/^[$]/', $value) || $value === 'null' || $value === 'NULL') {
                    return "<input type=\"email\" name=\"$name\" value=\"{{ $value ?? '' }}\"$attributesStr>";
                } else {
                    // It's a literal value
                    $value = trim($value, '\'"');
                    return "<input type=\"email\" name=\"$name\" value=\"$value\"$attributesStr>";
                }
            },
            $content
        );
    }

    // Form::number
    function convertFormNumber($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::number\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If $value looks like a variable
                if (preg_match('/^[$]/', $value) || $value === 'null' || $value === 'NULL') {
                    return "<input type=\"number\" name=\"$name\" value=\"{{ $value ?? '' }}\"$attributesStr>";
                } else {
                    // It's a literal value
                    $value = trim($value, '\'"');
                    return "<input type=\"number\" name=\"$name\" value=\"$value\"$attributesStr>";
                }
            },
            $content
        );
    }

    // Form::hidden
    function convertFormHidden($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::hidden\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If $value looks like a variable (no quotes) or is null
                if (preg_match('/^[$]/', $value) || $value === 'null' || $value === 'NULL') {
                    return "<input type=\"hidden\" name=\"$name\" value=\"{{ $value ?? '' }}\"$attributesStr>";
                } else {
                    // It's a literal value
                    $value = trim($value, '\'"');
                    return "<input type=\"hidden\" name=\"$name\" value=\"$value\"$attributesStr>";
                }
            },
            $content
        );
    }

    /**
     * Convert Form::select calls - main dispatcher function
     */
    function convertFormSelect($content)
    {
        // First try to match and convert complex expressions with method calls or operators
        $content = $this->convertComplexFormSelect($content);

        // Then try to match and convert simple array-based selects
        $content = $this->convertSimpleFormSelect($content);

        return $content;
    }

    /**
     * Convert Form::select with simple array notation
     */
    function convertSimpleFormSelect($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::select\(\s*([\'"][^\'"]++[\'"])\s*,\s*\[\s*((?:[^[\]]+|\'[^\']*\'|"[^"]*")*)\s*\]\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $optionsContent = $matches[2];
                $selected = trim($matches[3]);
                $attributes = isset($matches[4]) ? $this->parseOptionsArray($matches[4]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                return "<select name=\"$name\"$attributesStr>\n" .
                    "    @php\n" .
                    "        \$selectOptions = [$optionsContent];\n" .
                    "        \$selectedValue = $selected;\n" .
                    "    @endphp\n" .
                    "    @foreach(\$selectOptions as \$value => \$label)\n" .
                    "        <option value=\"{{ \$value }}\" {{ \$selectedValue == \$value ? 'selected' : '' }}>{{ \$label }}</option>\n" .
                    "    @endforeach\n" .
                    "</select>";
            },
            $content
        );
    }

    /**
     * Convert Form::select with complex expressions (method calls, operators, etc.)
     */
    function convertComplexFormSelect($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::select\(\s*([\'"][^\'"]++[\'"])\s*,\s*((?:\$[a-zA-Z0-9_]+(?:->|\[|\(|::).*?|.*?\+.*?))\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $options = trim($matches[2]);
                $selected = trim($matches[3]);
                $attributes = isset($matches[4]) ? $this->parseOptionsArray($matches[4]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                return "<select name=\"$name\"$attributesStr>\n" .
                    "    @php\n" .
                    "        \$selectOptions = $options;\n" .
                    "        \$selectedValue = $selected;\n" .
                    "    @endphp\n" .
                    "    @foreach(\$selectOptions as \$value => \$label)\n" .
                    "        <option value=\"{{ \$value }}\" {{ \$selectedValue == \$value ? 'selected' : '' }}>{{ \$label }}</option>\n" .
                    "    @endforeach\n" .
                    "</select>";
            },
            $content
        );
    }

    // Form::checkbox
    function convertFormCheckbox($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::checkbox\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2], '\'"');
                $checked = isset($matches[3]) ? trim($matches[3]) : 'false';
                $attributes = isset($matches[4]) ? $this->parseOptionsArray($matches[4]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                $checkedAttr = '';
                if ($checked === 'true' || $checked === '1') {
                    $checkedAttr = ' checked';
                } elseif (preg_match('/^[$]/', $checked) || $checked !== 'false' && $checked !== 'null' && $checked !== 'NULL' && $checked !== '0') {
                    $checkedAttr = " {{ $checked ? 'checked' : '' }}";
                }

                return "<input type=\"checkbox\" name=\"$name\" value=\"$value\"$checkedAttr$attributesStr>";
            },
            $content
        );
    }

    // Form::radio
    function convertFormRadio($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::radio\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*([^,\)]+))?(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $value = trim($matches[2], '\'"');
                $checked = isset($matches[3]) ? trim($matches[3]) : 'false';
                $attributes = isset($matches[4]) ? $this->parseOptionsArray($matches[4]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                $checkedAttr = '';
                if ($checked === 'true' || $checked === '1') {
                    $checkedAttr = ' checked';
                } elseif (preg_match('/^[$]/', $checked) || $checked !== 'false' && $checked !== 'null' && $checked !== 'NULL' && $checked !== '0') {
                    $checkedAttr = " {{ $checked ? 'checked' : '' }}";
                }

                return "<input type=\"radio\" name=\"$name\" value=\"$value\"$checkedAttr$attributesStr>";
            },
            $content
        );
    }

    // Form::file
    function convertFormFile($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::file\(\s*([\'"][^\'"]++[\'"])(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $name = trim($matches[1], '\'"');
                $attributes = isset($matches[2]) ? $this->parseOptionsArray($matches[2]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                return "<input type=\"file\" name=\"$name\"$attributesStr>";
            },
            $content
        );
    }

    // Form::submit
    function convertFormSubmit($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::submit\(\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $text = trim($matches[1], '\'"');
                $attributes = isset($matches[2]) ? $this->parseOptionsArray($matches[2]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If it looks like a variable
                if (preg_match('/^[$]/', $text)) {
                    return "<button type=\"submit\"$attributesStr>{{ $text }}</button>";
                } else {
                    return "<button type=\"submit\"$attributesStr>$text</button>";
                }
            },
            $content
        );
    }

    // Form::label
    function convertFormLabel($content)
    {
        return preg_replace_callback(
            '/\{!!\s*Form::label\(\s*([\'"][^\'"]++[\'"])\s*,\s*([^,\)]+)(?:\s*,\s*(\[.*?\]))?\s*\)\s*!!}/',
            function ($matches) {
                $for = trim($matches[1], '\'"');
                $text = trim($matches[2]);
                $attributes = isset($matches[3]) ? $this->parseOptionsArray($matches[3]) : [];
                $attributesStr = $this->getAttributesString($attributes);

                // If it looks like a variable
                if (preg_match('/^[$]/', $text) || $text === 'null' || $text === 'NULL') {
                    return "<label for=\"$for\"$attributesStr>{{ $text }}</label>";
                } else {
                    // It's a literal
                    $text = trim($text, '\'"');
                    return "<label for=\"$for\"$attributesStr>$text</label>";
                }
            },
            $content
        );
    }

    // Parse options array like ['class' => 'form-control', 'id' => 'username']
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
