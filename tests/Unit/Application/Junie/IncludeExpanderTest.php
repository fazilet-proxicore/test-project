<?php

namespace Tests\Unit\Application\Junie;

use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Support\JunieGuidelines\IncludeExpander;
use Support\JunieGuidelines\PathResolver;
use Symfony\Component\Finder\SplFileInfo;
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
        $content = 'Hello world';
        $this->assertEquals($content, $this->expander->expand($content, '/root'));
    }

    public function test_expand_single_include(): void
    {
        $content = 'Before {$includeFile=foo.md$} After';
        $resolvedPath = '/root/foo.md';

        $this->pathResolver->expects('resolvePath')->with('foo.md', '/root')->andReturn($resolvedPath);
        $this->pathResolver->expects('normalizePath')->with($resolvedPath)->andReturn($resolvedPath);

        File::shouldReceive('exists')->with($resolvedPath)->andReturn(true);
        File::shouldReceive('get')->with($resolvedPath)->andReturn('Included content');

        $this->assertEquals('Before Included content After', $this->expander->expand($content, '/root'));
    }

    public function test_circular_include_detection(): void
    {
        $content = 'Include me {$includeFile=self.md$}';
        $path = '/root/self.md';

        $this->pathResolver->expects('resolvePath')->with('self.md', '/root')->andReturn($path);
        $this->pathResolver->expects('normalizePath')->with($path)->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn($content);

        // First call expands once, then it hits visitedFiles and stops
        $result = $this->expander->expand($content, '/root', [$path]);

        $this->assertEquals('Include me ', $result);
        $this->assertContains("Circular include skipped: {$path}", $this->expander->warnings());
    }

    public function test_complex_circular_include_chain(): void
    {
        $fileAPath = '/root/fileA.md';
        $fileBPath = '/root/fileB.md';

        $fileAContent = 'A includes {$includeFile=fileB.md$}';
        $fileBContent = 'B includes {$includeFile=fileA.md$}';

        // Mocks for fileA
        $this->pathResolver->expects('resolvePath')->with('fileB.md', '/root')->andReturn($fileBPath);
        $this->pathResolver->expects('normalizePath')->with($fileBPath)->andReturn($fileBPath);

        // Mocks for fileB
        $this->pathResolver->expects('resolvePath')->with('fileA.md', '/root')->andReturn($fileAPath);
        $this->pathResolver->expects('normalizePath')->with($fileAPath)->andReturn($fileAPath);

        File::shouldReceive('exists')->with($fileAPath)->andReturn(true);
        File::shouldReceive('get')->with($fileAPath)->andReturn($fileAContent);

        File::shouldReceive('exists')->with($fileBPath)->andReturn(true);
        File::shouldReceive('get')->with($fileBPath)->andReturn($fileBContent);

        // Expanding fileA
        $result = $this->expander->expand($fileAContent, '/root', [$fileAPath]);

        // fileA -> fileB -> (fileA skipped)
        // fileA Content: 'A includes' + expanded fileB
        // fileB Content: 'B includes' + (skipped fileA)
        // Expecting: 'A includes B includes'
        $this->assertEquals('A includes B includes', $result);
        $this->assertContains("Circular include skipped: {$fileAPath}", $this->expander->warnings());
    }

    public function test_missing_file_warning(): void
    {
        $content = 'Missing {$includeFile=missing.md$}';
        $path = '/root/missing.md';

        $this->pathResolver->expects('resolvePath')->with('missing.md', '/root')->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(false);

        $this->assertEquals('Missing ', $this->expander->expand($content, '/root'));
        $this->assertContains("Included file not found: {$path}", $this->expander->warnings());
    }

    public function test_empty_file_warning(): void
    {
        $content = 'Empty {$includeFile=empty.md$}';
        $path = '/root/empty.md';

        $this->pathResolver->expects('resolvePath')->with('empty.md', '/root')->andReturn($path);
        $this->pathResolver->expects('normalizePath')->with($path)->andReturn($path);

        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn('   ');

        $this->assertEquals('Empty ', $this->expander->expand($content, '/root'));
        $this->assertContains("Included file is empty: {$path}", $this->expander->warnings());
    }

    public function test_expand_folder_include(): void
    {
        $content = 'Folder: {$includeFolder=guides$}';
        $resolvedFolder = '/root/guides';
        $file1Mock = $this->mock(SplFileInfo::class);
        $file2Mock = $this->mock(SplFileInfo::class);

        $this->pathResolver->expects('resolveFolderPath')->with('guides', '/root')->andReturn($resolvedFolder);
        File::shouldReceive('isDirectory')->with($resolvedFolder)->andReturn(true);

        $file1Mock->shouldReceive('getFilename')->andReturn('1.md');
        $file1Mock->shouldReceive('getExtension')->andReturn('md');
        $file1Mock->shouldReceive('getRealPath')->andReturn('/root/guides/1.md');

        $file2Mock->shouldReceive('getFilename')->andReturn('2.md');
        $file2Mock->shouldReceive('getExtension')->andReturn('md');
        $file2Mock->shouldReceive('getRealPath')->andReturn('/root/guides/2.md');

        File::shouldReceive('files')->with($resolvedFolder)->andReturn([$file2Mock, $file1Mock]);

        $this->pathResolver->expects('normalizePath')->with('/root/guides/1.md')->andReturn('/root/guides/1.md');
        $this->pathResolver->expects('normalizePath')->with('/root/guides/2.md')->andReturn('/root/guides/2.md');

        File::shouldReceive('exists')->with('/root/guides/1.md')->andReturn(true);
        File::shouldReceive('exists')->with('/root/guides/2.md')->andReturn(true);

        File::shouldReceive('get')->with('/root/guides/1.md')->andReturn('Guide 1');
        File::shouldReceive('get')->with('/root/guides/2.md')->andReturn('Guide 2');

        $expected = "Folder: Guide 1\n\nGuide 2";
        $this->assertEquals($expected, $this->expander->expand($content, '/root'));
    }
}
