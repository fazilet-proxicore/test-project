<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Services\Email\EmailRenderer;
use Tests\TestCase;
use Tests\Traits\InteractsWithEmailSnapshots;

class EmailSnapshotTest extends TestCase
{
    use InteractsWithEmailSnapshots;

    #[Test]
    public function test_template_matches_snapshot()
    {
        $renderer = new EmailRenderer();
        $dto = $renderer->renderView('emails.test-template', ['name' => 'John Doe'], 'Test Subject');

        $this->assertHtmlMatchesSnapshot('test-template-john-doe', $dto->content_html);
    }
}
