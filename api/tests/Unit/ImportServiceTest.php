<?php

namespace Tests\Unit;

use App\Support\Import\ImportService;
use RuntimeException;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    public function test_it_parses_csv_rows_with_normalized_headers(): void
    {
        $service = new ImportService();
        $path = sys_get_temp_dir().'/import-service-test.csv';

        file_put_contents($path, "Company Name,Email\nAcme,ops@acme.test\n");

        $rows = $service->parseFile($path);

        $this->assertCount(1, $rows);
        $this->assertSame('Acme', $rows[0]['company_name']);
        $this->assertSame('ops@acme.test', $rows[0]['email']);

        @unlink($path);
    }

    public function test_it_validates_required_fields(): void
    {
        $service = new ImportService();

        $errors = $service->validateRow(['name' => '', 'email' => 'bad-email'], ['name', 'email']);

        $this->assertNotEmpty($errors);
        $this->assertContains('Missing required field [name]', $errors);
        $this->assertContains('Invalid email format', $errors);
    }

    public function test_it_throws_for_unknown_type_config(): void
    {
        $service = new ImportService();

        $this->expectException(RuntimeException::class);
        $service->getTypeConfig('unknown-type');
    }
}
