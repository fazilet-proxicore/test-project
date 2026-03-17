<?php

namespace Tests\Unit\Application\Junie;

use App\Application\Junie\IncludeExpander;
use App\Application\Junie\PathResolver;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class IncludeExpanderTest extends TestCase
{
    private IncludeExpander $expander;
    private PathResolver|MockInterface $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathResolver = $this->mock(PathResolver::class);
        $this->expander = new IncludeExpander($this->pathResolver);
    }

    public function test_expand_simple_text_no_includes(): void
    {
        $content = "Hello world";
        $this->assertEquals($content, $this->expander->expand($content, '/root'));
    }

    public function test_expand_single_include(): void
    {
        $content = "Before {\$includeFile=foo.md\$} After";
        $resolvedPath = '/root/foo.md';

        $this->pathResolver->expects('isWildcardPath')->with('foo.md')->andReturn(false);
        $this->pathResolver->expects('resolvePath')->with('foo.md', '/root')->andReturn($resolvedPath);
        $this->pathResolver->expects('normalizePath')->with($resolvedPath)->andReturn($resolvedPath);

        File::shouldReceive('exists')->with($resolvedPath)->andReturn(true);
        File::shouldReceive('get')->with($resolvedPath)->andReturn('Included content');

        $this->assertEquals("Before Included content After", $this->expander->expand($content, '/root'));
    }

    public function test_expand_wildcard_include(): void
    {
        $content = "List: {\$includeFile=docs/*.md\$}";
        $globPath = '/root/docs/*.md';
        $file1 = '/root/docs/a.md';
        $file2 = '/root/docs/b.md';

        $this->pathResolver->expects('isWildcardPath')->with('docs/*.md')->andReturn(true);
        $this->pathResolver->expects('resolveGlobPath')->with('docs/*.md', '/root')->andReturn($globPath);

        File::shouldReceive('glob')->with($globPath)->andReturn([$file2, $file1]); // Unsorted

        File::shouldReceive('exists')->with($file1)->andReturn(true);
        File::shouldReceive('exists')->with($file2)->andReturn(true);

        $this->pathResolver->expects('normalizePath')->with($file1)->andReturn($file1);
        $this->pathResolver->expects('normalizePath')->with($file2)->andReturn($file2);

        File::shouldReceive('get')->with($file1)->andReturn('Content A');
        File::shouldReceive('get')->with($file2)->andReturn('Content B');

        // Sorted by filename: a.md, then b.md
        $expected = "List: Content A\n\nContent B";
        $this->assertEquals($expected, $this->expander->expand($content, '/root'));
    }

    public function test_circular_include_detection(): void
    {
        $content = "Include me {\$includeFile=self.md\$}";
        $path = '/root/self.md';

        $this->pathResolver->expects('isWildcardPath')->with('self.md')->andReturn(false);
        $this->pathResolver->expects('resolvePath')->with('self.md', '/root')->andReturn($path);
        $this->pathResolver->expects('normalizePath')->with($path)->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn($content);

        // First call expands once, then it hits visitedFiles and stops
        $result = $this->expander->expand($content, '/root', [$path]);

        $this->assertEquals("Include me ", $result);
        $this->assertContains("Circular include skipped: {$path}", $this->expander->warnings());
    }

    public function test_missing_file_warning(): void
    {
        $content = "Missing {\$includeFile=missing.md\$}";
        $path = '/root/missing.md';

        $this->pathResolver->expects('isWildcardPath')->with('missing.md')->andReturn(false);
        $this->pathResolver->expects('resolvePath')->with('missing.md', '/root')->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(false);

        $this->assertEquals("Missing ", $this->expander->expand($content, '/root'));
        $this->assertContains("Included file not found: {$path}", $this->expander->warnings());
    }

    public function test_empty_file_warning(): void
    {
        $content = "Empty {\$includeFile=empty.md\$}";
        $path = '/root/empty.md';

        $this->pathResolver->expects('isWildcardPath')->with('empty.md')->andReturn(false);
        $this->pathResolver->expects('resolvePath')->with('empty.md', '/root')->andReturn($path);
        $this->pathResolver->expects('normalizePath')->with($path)->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn('   ');

        $this->assertEquals("Empty ", $this->expander->expand($content, '/root'));
        $this->assertContains("Included file is empty: {$path}", $this->expander->warnings());
    }
}
