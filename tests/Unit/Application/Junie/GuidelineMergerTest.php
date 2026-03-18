<?php

namespace Tests\Unit\Application\Junie;

use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use RuntimeException;
use Support\JunieGuidelines\GuidelineMerger;
use Support\JunieGuidelines\IncludeExpander;
use Support\JunieGuidelines\PathResolver;
use Tests\TestCase;

class GuidelineMergerTest extends TestCase
{
    private GuidelineMerger $merger;
    private IncludeExpander|MockInterface $includeExpander;
    private PathResolver|MockInterface $pathResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->includeExpander = $this->mock(IncludeExpander::class);
        $this->pathResolver = $this->mock(PathResolver::class);
        $this->merger = new GuidelineMerger($this->includeExpander, $this->pathResolver);
    }

    public function test_merge_throws_exception_if_file_missing(): void
    {
        $path = '/root/missing.md';
        File::shouldReceive('exists')->with($path)->andReturn(false);

        $this->includeExpander->expects('resetWarnings');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Local guidelines file could not be found: {$path}");

        $this->merger->mergeFromLocalFile($path);
    }

    public function test_merge_returns_empty_and_warns_if_file_empty(): void
    {
        $path = '/root/empty.md';
        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn('   ');

        $this->includeExpander->expects('resetWarnings');

        $result = $this->merger->mergeFromLocalFile($path);

        $this->assertEquals('', $result);
        $this->assertContains("Local guidelines file is empty: {$path}", $this->merger->warnings());
    }

    public function test_merge_successful(): void
    {
        $path = '/root/local.md';
        $content = 'Local content';
        $mergedContent = 'Merged content';

        File::shouldReceive('exists')->with($path)->andReturn(true);
        File::shouldReceive('get')->with($path)->andReturn($content);

        $this->includeExpander->expects('resetWarnings');
        $this->pathResolver->expects('normalizePath')->with($path)->andReturn($path);

        $this->includeExpander->expects('expand')
            ->with($content, '/root', [$path])
            ->andReturn($mergedContent);

        $this->includeExpander->expects('warnings')->andReturn(['Some warning']);

        $result = $this->merger->mergeFromLocalFile($path);

        $this->assertEquals($mergedContent, $result);
        $this->assertContains('Some warning', $this->merger->warnings());
    }
}
