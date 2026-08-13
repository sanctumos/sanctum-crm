<?php
/**
 * Layout helper unit tests — renderPageHeader
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/includes/layout.php';

class LayoutTest
{
    public function runAllTests(): void
    {
        echo "Running LayoutTest...\n";
        $this->testRenderPageHeaderTitleAndActions();
        $this->testRenderPageHeaderSubtitleOptional();
        echo "LayoutTest: all passed\n";
    }

    public function testRenderPageHeaderTitleAndActions(): void
    {
        echo "  Testing renderPageHeader title + actions... ";
        ob_start();
        renderPageHeader('Contacts', '', '<button type="button">Add</button>');
        $html = ob_get_clean();
        if (strpos($html, 'page-header') === false) {
            throw new Exception('missing page-header');
        }
        if (strpos($html, 'page-header__title') === false || strpos($html, '<h1>Contacts</h1>') === false) {
            throw new Exception('missing title markup');
        }
        if (strpos($html, 'page-header__actions') === false || strpos($html, '>Add</button>') === false) {
            throw new Exception('missing actions');
        }
        echo "PASS\n";
    }

    public function testRenderPageHeaderSubtitleOptional(): void
    {
        echo "  Testing subtitle rendering and escaping... ";
        ob_start();
        renderPageHeader('Deals', 'A & B <script>', '');
        $html = ob_get_clean();
        if (strpos($html, 'subtitle') === false) {
            throw new Exception('missing subtitle');
        }
        if (strpos($html, 'A &amp; B') === false) {
            throw new Exception('subtitle not escaped');
        }
        if (strpos($html, '<script>') !== false) {
            throw new Exception('script not escaped');
        }
        if (strpos($html, 'page-header__actions') !== false) {
            throw new Exception('empty actions should omit actions block');
        }
        echo "PASS\n";
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    (new LayoutTest())->runAllTests();
}
