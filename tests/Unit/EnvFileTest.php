<?php

use Quentin\InfisicalSync\EnvFile;

beforeEach(function () {
    $this->tempFile = sys_get_temp_dir().'/test-envfile-'.uniqid().'.env';
});

afterEach(function () {
    @unlink($this->tempFile);
    @unlink($this->tempFile.'.backup');
});

it('parses simple key=value pairs', function () {
    file_put_contents($this->tempFile, "APP_KEY=base64:abc\nDB_HOST=localhost\nDB_PORT=3306");

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->variables())->toBe([
        'APP_KEY' => 'base64:abc',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
    ]);
});

it('preserves comments and blank lines', function () {
    $content = <<<'ENV'
# Application
APP_NAME=MyApp

# Database
DB_HOST=localhost
ENV;

    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();
    $env->write();

    expect(file_get_contents($this->tempFile))->toBe($content);
});

it('handles double-quoted values', function () {
    file_put_contents($this->tempFile, 'APP_NAME="My Application"');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('APP_NAME'))->toBe('My Application');
});

it('handles single-quoted values', function () {
    file_put_contents($this->tempFile, "APP_NAME='My Application'");

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('APP_NAME'))->toBe('My Application');
});

it('handles values with hash in quoted strings', function () {
    file_put_contents($this->tempFile, 'DB_PASS="secret#123"');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('DB_PASS'))->toBe('secret#123');
});

it('strips inline comments from unquoted values', function () {
    file_put_contents($this->tempFile, 'APP_DEBUG=true # Enable debug mode');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('APP_DEBUG'))->toBe('true');
});

it('handles multiline double-quoted values', function () {
    $content = <<<'ENV'
PRIVATE_KEY="line1
line2
line3"
OTHER_KEY=value
ENV;

    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('PRIVATE_KEY'))->toBe("line1\nline2\nline3");
    expect($env->get('OTHER_KEY'))->toBe('value');
});

it('handles empty values', function () {
    file_put_contents($this->tempFile, 'EMPTY_KEY=');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('EMPTY_KEY'))->toBe('');
});

it('handles values with equals signs', function () {
    file_put_contents($this->tempFile, 'APP_KEY=base64:abc=def=');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('APP_KEY'))->toBe('base64:abc=def=');
});

it('preserves order when updating an existing variable', function () {
    $content = "FIRST=1\nSECOND=2\nTHIRD=3";
    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();
    $env->set('SECOND', 'updated');
    $env->write();

    $result = file_get_contents($this->tempFile);
    expect($result)->toBe("FIRST=1\nSECOND=updated\nTHIRD=3");
});

it('appends new variables at the end', function () {
    $content = "FIRST=1\nSECOND=2";
    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();
    $env->set('THIRD', '3');
    $env->write();

    $result = file_get_contents($this->tempFile);
    expect($result)->toBe("FIRST=1\nSECOND=2\nTHIRD=3");
});

it('removes a variable', function () {
    $content = "FIRST=1\nSECOND=2\nTHIRD=3";
    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();
    $env->remove('SECOND');
    $env->write();

    $result = file_get_contents($this->tempFile);
    expect($result)->toBe("FIRST=1\nTHIRD=3");
});

it('creates a backup file', function () {
    $content = 'APP_KEY=secret';
    file_put_contents($this->tempFile, $content);

    $env = (new EnvFile($this->tempFile))->parse();
    $env->backup();

    expect(file_exists($this->tempFile.'.backup'))->toBeTrue();
    expect(file_get_contents($this->tempFile.'.backup'))->toBe($content);
});

it('reports has() correctly', function () {
    file_put_contents($this->tempFile, 'APP_KEY=value');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->has('APP_KEY'))->toBeTrue();
    expect($env->has('MISSING_KEY'))->toBeFalse();
});

it('returns null for missing keys via get()', function () {
    file_put_contents($this->tempFile, 'APP_KEY=value');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('MISSING_KEY'))->toBeNull();
});

it('handles export prefix', function () {
    file_put_contents($this->tempFile, 'export APP_KEY=value');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('APP_KEY'))->toBe('value');
});

it('handles non-existent file gracefully', function () {
    $env = (new EnvFile('/tmp/nonexistent-'.uniqid().'.env'))->parse();

    expect($env->variables())->toBe([]);
});

it('quotes values with spaces when writing', function () {
    file_put_contents($this->tempFile, '');

    $env = (new EnvFile($this->tempFile))->parse();
    $env->set('APP_NAME', 'My App');
    $env->write();

    $result = file_get_contents($this->tempFile);
    expect($result)->toBe('APP_NAME="My App"');
});

it('handles escaped quotes in double-quoted values', function () {
    file_put_contents($this->tempFile, 'VALUE="say \\"hello\\""');

    $env = (new EnvFile($this->tempFile))->parse();

    expect($env->get('VALUE'))->toBe('say "hello"');
});
