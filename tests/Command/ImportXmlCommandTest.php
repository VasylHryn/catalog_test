<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ImportXmlCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;


/**
 * Tests for XML import command
 *
 * @covers \App\Command\ImportXmlCommand
 */
class ImportXmlCommandTest extends TestCase
{
    private CommandTester $commandTester;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $command = new ImportXmlCommand();
        $this->commandTester = new CommandTester($command);
    }

    /**
     * Test successful import with valid XML
     */
    public function testSuccessfulImport(): void
    {
        $validXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shop>
    <offers>
        <offer id="1">
            <name>Test Product</name>
            <price>100</price>
            <description>Test Description</description>
            <param name="Brand">Test Brand</param>
            <param name="Color">Red</param>
        </offer>
    </offers>
</shop>
XML;

        $testFile = sys_get_temp_dir() . '/valid_catalog.xml';
        file_put_contents($testFile, $validXml);

        $this->commandTester->execute(['--file' => $testFile]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import completed successfully', $output);

        unlink($testFile);
    }

    /**
     * Test import with non-existent file
     */
    public function testImportWithMissingFile(): void
    {
        $this->commandTester->execute(['--file' => 'nonexistent.xml']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('XML file not found', $output);
    }

    /**
     * Test import with malformed XML
     */
    public function testImportWithInvalidXmlStructure(): void
    {
        $invalidXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shop>
    <offers>
        <offer id="1">
            <invalid>Test</invalid>
XML;

        $testFile = sys_get_temp_dir() . '/invalid_catalog.xml';
        file_put_contents($testFile, $invalidXml);

        $this->commandTester->execute(['--file' => $testFile]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import failed: Failed to parse XML node', $output);

        unlink($testFile);
    }

    /**
     * Test import with invalid product data
     */
    public function testImportWithInvalidProductData(): void
    {
        $invalidProductXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shop>
    <offers>
        <offer id="1">
            <name></name>
            <price>invalid</price>
        </offer>
    </offers>
</shop>
XML;

        $testFile = sys_get_temp_dir() . '/invalid_product.xml';
        file_put_contents($testFile, $invalidProductXml);

        $this->commandTester->execute(['--file' => $testFile]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import completed successfully! Total items: 0', $output);

        unlink($testFile);
    }

    /**
     * Test import with empty offers list
     */
    public function testImportWithEmptyOffers(): void
    {
        $emptyXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shop>
    <offers>
    </offers>
</shop>
XML;

        $testFile = sys_get_temp_dir() . '/empty_catalog.xml';
        file_put_contents($testFile, $emptyXml);

        $this->commandTester->execute(['--file' => $testFile]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import completed successfully! Total items: 0', $output);

        unlink($testFile);
    }

    /**
     * Test import with zero product ID
     */
    public function testImportWithZeroId(): void
    {
        $xmlWithZeroId = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shop>
    <offers>
        <offer id="000">
            <name>Test Product</name>
            <price>100</price>
            <description>Test Description</description>
            <param name="Brand">Test Brand</param>
        </offer>
    </offers>
</shop>
XML;

        $testFile = sys_get_temp_dir() . '/zero_id_catalog.xml';
        file_put_contents($testFile, $xmlWithZeroId);

        $this->commandTester->execute(['--file' => $testFile]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Import completed successfully', $output);

        unlink($testFile);
    }
}