<?php

namespace Quentin\InfisicalSync;

class EnvFile
{
    /** @var array<int, array{type: string, key?: string, value?: string, raw: string}> */
    private array $entries = [];

    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function parse(): self
    {
        if (! file_exists($this->path)) {
            return $this;
        }

        $content = file_get_contents($this->path);
        if ($content === false || $content === '') {
            return $this;
        }

        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // Blank line
            if (trim($line) === '') {
                $this->entries[] = ['type' => 'blank', 'raw' => $line];
                $i++;

                continue;
            }

            // Comment line
            if (str_starts_with(trim($line), '#')) {
                $this->entries[] = ['type' => 'comment', 'raw' => $line];
                $i++;

                continue;
            }

            // Variable line: KEY=VALUE
            $equalsPos = strpos($line, '=');
            if ($equalsPos === false) {
                // Not a valid variable line, preserve as comment
                $this->entries[] = ['type' => 'comment', 'raw' => $line];
                $i++;

                continue;
            }

            $key = trim(substr($line, 0, $equalsPos));
            // Strip optional 'export ' prefix
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }

            $rawValue = substr($line, $equalsPos + 1);
            $rawLines = [$line];

            // Check for multiline quoted values
            $trimmedValue = ltrim($rawValue);
            if (str_starts_with($trimmedValue, '"') && ! $this->hasClosingDoubleQuote($trimmedValue)) {
                // Multiline: accumulate until closing quote
                $i++;
                while ($i < $count) {
                    $rawLines[] = $lines[$i];
                    $rawValue .= "\n".$lines[$i];
                    if (str_contains($lines[$i], '"')) {
                        $i++;
                        break;
                    }
                    $i++;
                }
            } else {
                $i++;
            }

            $value = $this->parseValue($rawValue);
            $raw = implode("\n", $rawLines);

            $this->entries[] = [
                'type' => 'variable',
                'key' => $key,
                'value' => $value,
                'raw' => $raw,
            ];
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $vars = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'variable') {
                $vars[$entry['key']] = $entry['value'];
            }
        }

        return $vars;
    }

    public function has(string $key): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'variable' && $entry['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    public function get(string $key): ?string
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'variable' && $entry['key'] === $key) {
                return $entry['value'];
            }
        }

        return null;
    }

    public function set(string $key, string $value): self
    {
        $formattedValue = $this->formatValue($value);

        foreach ($this->entries as $i => $entry) {
            if ($entry['type'] === 'variable' && $entry['key'] === $key) {
                $this->entries[$i]['value'] = $value;
                $this->entries[$i]['raw'] = $key.'='.$formattedValue;

                return $this;
            }
        }

        // Key not found, append at the end
        $this->entries[] = [
            'type' => 'variable',
            'key' => $key,
            'value' => $value,
            'raw' => $key.'='.$formattedValue,
        ];

        return $this;
    }

    public function remove(string $key): self
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            fn (array $entry) => ! ($entry['type'] === 'variable' && $entry['key'] === $key),
        ));

        return $this;
    }

    public function write(): void
    {
        $lines = array_map(fn (array $entry) => $entry['raw'], $this->entries);
        file_put_contents($this->path, implode("\n", $lines));
    }

    public function backup(?string $backupPath = null): void
    {
        $backupPath ??= $this->path.'.backup';
        copy($this->path, $backupPath);
    }

    public function path(): string
    {
        return $this->path;
    }

    private function hasClosingDoubleQuote(string $value): bool
    {
        // Value starts with " — check if there's a closing " (not escaped)
        $inner = substr($value, 1);

        $pos = 0;
        while (($pos = strpos($inner, '"', $pos)) !== false) {
            // Check if preceded by backslash
            if ($pos > 0 && $inner[$pos - 1] === '\\') {
                $pos++;

                continue;
            }

            return true;
        }

        return false;
    }

    private function parseValue(string $rawValue): string
    {
        $value = trim($rawValue);

        // Empty value
        if ($value === '') {
            return '';
        }

        // Double-quoted value
        if (str_starts_with($value, '"')) {
            $end = $this->findClosingQuote($value, '"');
            if ($end !== false) {
                $inner = substr($value, 1, $end - 1);

                // Unescape
                return str_replace(
                    ['\\n', '\\r', '\\t', '\\"', '\\\\'],
                    ["\n", "\r", "\t", '"', '\\'],
                    $inner,
                );
            }

            // No closing quote found, return as-is minus opening quote
            return substr($value, 1);
        }

        // Single-quoted value
        if (str_starts_with($value, "'")) {
            $end = strrpos($value, "'");
            if ($end !== false && $end > 0) {
                return substr($value, 1, $end - 1);
            }

            return substr($value, 1);
        }

        // Unquoted value: strip inline comments
        $commentPos = strpos($value, ' #');
        if ($commentPos !== false) {
            $value = rtrim(substr($value, 0, $commentPos));
        }

        return $value;
    }

    private function findClosingQuote(string $value, string $quote): int|false
    {
        $pos = 1; // Skip opening quote
        $len = strlen($value);

        while ($pos < $len) {
            if ($value[$pos] === '\\') {
                $pos += 2; // Skip escaped character

                continue;
            }
            if ($value[$pos] === $quote) {
                return $pos;
            }
            $pos++;
        }

        return false;
    }

    private function formatValue(string $value): string
    {
        // Quote if the value contains spaces, #, quotes, newlines, or is empty
        if ($value === ''
            || str_contains($value, ' ')
            || str_contains($value, '#')
            || str_contains($value, '"')
            || str_contains($value, "\n")
            || str_contains($value, '\\')
        ) {
            $escaped = str_replace(
                ['\\', '"', "\n", "\r", "\t"],
                ['\\\\', '\\"', '\\n', '\\r', '\\t'],
                $value,
            );

            return '"'.$escaped.'"';
        }

        return $value;
    }
}
