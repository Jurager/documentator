<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Jurager\Documentator\Formats\SimpleFormat;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

beforeEach(function () {
    $this->outputDirectory = sys_get_temp_dir().'/documentator-tests-'.bin2hex(random_bytes(8));
});

afterEach(function () {
    if (str_starts_with($this->outputDirectory, sys_get_temp_dir().'/documentator-tests-')) {
        (new Filesystem)->deleteDirectory($this->outputDirectory);
    }
});

it('generates a valid JSON file and supports output and format overrides', function (string $format) {
    Route::apiResource('api/widgets', WidgetsController::class);
    $output = $this->outputDirectory.'/nested/openapi.json';
    $this->artisan('documentator:generate', ['--output' => $output, '--format' => $format])->assertSuccessful();
    $spec = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
    assertValidOpenApi($spec);
    expect(config('documentator.format'))->toBe(SimpleFormat::class);
})->with(['simple', 'json-api']);

it('writes compact output to the configured file and handles empty routes', function () {
    $output = $this->outputDirectory.'/openapi.json';
    config(['documentator.output.path' => $output, 'documentator.output.pretty_print' => false]);
    $this->artisan('documentator:generate')->expectsOutputToContain('No routes were found')->assertSuccessful();
    expect(file_get_contents($output))->not->toContain("\n");
    assertValidOpenApi(json_decode(file_get_contents($output)));
});

it('rejects a non-array output configuration', function () {
    $output = $this->outputDirectory.'/openapi.json';
    config(['documentator.output' => $output]);
    $this->artisan('documentator:generate')->expectsOutputToContain('output.path')->assertFailed();
    expect(is_file($output))->toBeFalse();
});

it('reports an invalid format without writing a document', function () {
    $output = $this->outputDirectory.'/openapi.json';
    $this->artisan('documentator:generate', ['--output' => $output, '--format' => 'missing'])
        ->expectsOutputToContain('Unknown format')->assertFailed();
    expect(is_file($output))->toBeFalse();
});

it('returns failure when the filesystem cannot write the document', function () {
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('ensureDirectoryExists')->once();
    $files->shouldReceive('replace')->once()->andThrow(new RuntimeException('Failed to write'));
    $this->app->instance(Filesystem::class, $files);
    $this->artisan('documentator:generate', ['--output' => $this->outputDirectory.'/openapi.json'])
        ->expectsOutputToContain('Failed to write')->assertFailed();
});

it('reports filesystem exceptions as command failures', function () {
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('ensureDirectoryExists')->once()->andThrow(new RuntimeException('Directory unavailable'));
    $this->app->instance(Filesystem::class, $files);
    $this->artisan('documentator:generate', ['--output' => $this->outputDirectory.'/openapi.json'])
        ->expectsOutputToContain('Directory unavailable')->assertFailed();
});

it('returns success for consistent parameter definitions', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    $this->artisan('documentator:lint')->expectsOutputToContain('No inconsistencies found')->assertSuccessful();
});

it('reports parameter signature drift and returns failure', function () {
    Route::get('api/widgets', [WidgetsController::class, 'index']);
    Route::get('api/other', [WidgetsController::class, 'inconsistent']);
    $this->artisan('documentator:lint')->expectsOutputToContain('filter[name]')
        ->expectsOutputToContain('X-Tenant')->assertFailed();
});

it('shows extraction diagnostics in verbose mode', function () {
    Route::post('api/failure', [WidgetsController::class, 'failing']);
    $this->artisan('documentator:generate', ['--output' => $this->outputDirectory.'/openapi.json', '--verbose' => true])
        ->expectsOutputToContain('FailingRequest')->assertSuccessful();
});

it('shows route diagnostics while linting in verbose mode', function () {
    Route::get('api/missing', 'MissingController@index');
    $this->artisan('documentator:lint', ['--verbose' => true])->expectsOutputToContain('MissingController')->assertSuccessful();
});

it('returns failure for JSON encoding errors', function () {
    config(['documentator.info.description' => INF]);
    $this->artisan('documentator:generate', ['--output' => $this->outputDirectory.'/openapi.json'])
        ->expectsOutputToContain('JSON error')->assertFailed();
});

it('resolves relative output paths from the application root', function () {
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('ensureDirectoryExists')->once()->with(dirname(base_path('artifacts/openapi.json')));
    $files->shouldReceive('replace')->once()->with(base_path('artifacts/openapi.json'), Mockery::type('string'));
    app()->instance(Filesystem::class, $files);
    $this->artisan('documentator:generate', ['--output' => 'artifacts/openapi.json'])->assertSuccessful();
});

it('overrides an invalid configured format without resolving it', function () {
    config(['documentator.format' => 'MissingFormat']);
    $output = $this->outputDirectory.'/openapi.json';

    $this->artisan('documentator:generate', ['--output' => $output, '--format' => 'simple'])->assertSuccessful();

    assertValidOpenApi(json_decode(file_get_contents($output)));
    expect(config('documentator.format'))->toBe('MissingFormat');
});

it('preserves the existing specification when JSON serialization fails', function () {
    $output = $this->outputDirectory.'/openapi.json';
    (new Filesystem)->ensureDirectoryExists($this->outputDirectory);
    file_put_contents($output, '{"previous":true}');
    config(['documentator.info.description' => INF]);

    $this->artisan('documentator:generate', ['--output' => $output])->assertFailed();

    expect(file_get_contents($output))->toBe('{"previous":true}');
});

abstract class DocumentatorAbstractFormat extends SimpleFormat {}

it('reports an unresolvable custom format as a command failure', function () {
    $this->artisan('documentator:generate', [
        '--output' => $this->outputDirectory.'/openapi.json',
        '--format' => DocumentatorAbstractFormat::class,
    ])->expectsOutputToContain('Unable to resolve format')->assertFailed();
});
