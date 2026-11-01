<?php

use Jurager\Documentator\Parsers\PhpDocParser;

beforeEach(function () {
    $this->parser = app(PhpDocParser::class);
});

it('parses metadata multiline descriptions flags and all parameter locations', function () {
    $doc = $this->parser->parse(<<<'DOC'
/**
 * Default title
 * Introductory text.
 * @summary Explicit title
 * @description
 * First line.
 * Second line.
 * @group Widgets
 * @resource widgets
 * @deprecated
 * @unauthenticated
 * @queryParam search string Search across all widgets. Example: "desk"
 * @bodyParam enabled boolean required Enabled. Example: false
 * @urlParam widget integer optional Identifier. Example: 0
 * @header X-Client string optional Client name.
 * @response {"ok":true}
 * @response 404 Not found
 */
DOC);
    expect($doc)->toMatchArray(['summary' => 'Explicit title', 'group' => 'Widgets', 'resource' => 'widgets', 'deprecated' => true, 'authenticated' => false])
        ->and($doc['description'])->toBe("Introductory text.\nFirst line.\nSecond line.")
        ->and($doc['queryParams'][0])->toMatchArray(['description' => 'Search across all widgets.', 'example' => 'desk'])
        ->and($doc['bodyParams'][0]['example'])->toBeFalse()
        ->and($doc['urlParams'][0]['example'])->toBe(0)
        ->and(json_encode($doc['responses']))->toBe('[{"status":200,"content":{"ok":true}},{"status":404,"content":"Not found"}]');
});

it('expands presets once and keeps explicit overrides regardless of declaration order', function (bool $first) {
    $lines = ['@queryParam page[size] integer required Custom page size.', '@preset pagination', '@preset pagination', '@preset missing', '@preset accept-language'];
    $doc = $this->parser->parse("/**\n * ".implode("\n * ", $first ? array_reverse($lines) : $lines)."\n */");
    expect($doc['queryParams'])->toHaveCount(2)
        ->and(collect($doc['queryParams'])->firstWhere('name', 'page[size]')['description'])->toBe('Custom page size.')
        ->and($doc['headers'][0]['name'])->toBe('Accept-Language');
})->with([false, true]);

it('parses authenticated endpoints and unknown tags without inventing values', function () {
    expect($this->parser->parse("/**\n * @authenticated\n * @unknown value\n */"))->toBe(['authenticated' => true])
        ->and($this->parser->parse("/**\n */"))->toBe([]);
});

it('parses single line PHPDoc and preserves Markdown emphasis', function () {
    expect($this->parser->parse('/** A summary. */')['summary'])->toBe('A summary.')
        ->and($this->parser->parse("/**\n * Summary.\n * **Details**\n */")['description'])->toBe('**Details**');
});
