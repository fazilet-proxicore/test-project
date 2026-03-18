<?php

namespace Tests\Unit\Application\Junie;

use Illuminate\Support\Facades\File;
use Support\JunieGuidelines\PathResolver;
use Tests\TestCase;

class PathResolverTest extends TestCase
{
    private PathResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PathResolver();
    }

    public function test_is_absolute_path(): void
    {
        $this->assertTrue($this->resolver->isAbsolutePath('/foo/bar'));
        $this->assertTrue($this->resolver->isAbsolutePath('C:\\foo\\bar'));
        $this->assertTrue($this->resolver->isAbsolutePath('d:\\baz'));
        $this->assertFalse($this->resolver->isAbsolutePath('foo/bar'));
        $this->assertFalse($this->resolver->isAbsolutePath('./foo/bar'));
        $this->assertFalse($this->resolver->isAbsolutePath('../foo/bar'));
    }

    public function test_normalize_path(): void
    {
        // realpath only works for existing paths, we'll test it doesn't crash
        $path = __FILE__;
        $this->assertEquals(realpath($path), $this->resolver->normalizePath($path));

        $nonExistent = '/non/existent/path/123';
        $this->assertEquals($nonExistent, $this->resolver->normalizePath($nonExistent));
    }

    public function test_resolve_path_absolute(): void
    {
        $this->assertEquals('/absolute/path.md', $this->resolver->resolvePath('/absolute/path.md', '/relative/to'));
    }

    public function test_resolve_path_relative_exists(): void
    {
        File::shouldReceive('exists')
            ->with('/relative/to' . DIRECTORY_SEPARATOR . 'foo.md')
            ->andReturn(true);

        // normalizePath uses realpath, but for mock we can just assume it returns what it's given if it doesn't exist on disk
        // or we can mock realpath if we could, but we can't easily.
        // Let's use a path that actually exists for realpath to work or just rely on the fallback in normalizePath.

        $result = $this->resolver->resolvePath('foo.md', '/relative/to');
        $this->assertEquals('/relative/to' . DIRECTORY_SEPARATOR . 'foo.md', $result);
    }

    public function test_resolve_path_relative_missing_fallback_to_base_path(): void
    {
        File::shouldReceive('exists')
            ->with('/relative/to' . DIRECTORY_SEPARATOR . 'foo.md')
            ->andReturn(false);

        // base_path() will be called. In Laravel tests, base_path() works.
        $expected = base_path('foo.md');

        $result = $this->resolver->resolvePath('foo.md', '/relative/to');
        $this->assertEquals($expected, $result);
    }

    public function test_resolve_folder_path_absolute(): void
    {
        $this->assertEquals('/absolute/folder', $this->resolver->resolveFolderPath('/absolute/folder/', '/relative/to'));
    }

    public function test_resolve_folder_path_relative_exists(): void
    {
        File::shouldReceive('isDirectory')
            ->with('/relative/to' . DIRECTORY_SEPARATOR . 'guides')
            ->andReturn(true);

        $result = $this->resolver->resolveFolderPath('guides', '/relative/to');
        $this->assertEquals('/relative/to' . DIRECTORY_SEPARATOR . 'guides', $result);
    }

    public function test_resolve_folder_path_relative_missing_fallback_to_base_path(): void
    {
        File::shouldReceive('isDirectory')
            ->with('/relative/to' . DIRECTORY_SEPARATOR . 'guides')
            ->andReturn(false);

        $expected = base_path('guides');

        $result = $this->resolver->resolveFolderPath('guides', '/relative/to');
        $this->assertEquals($expected, $result);
    }
}
