<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Consent\ConsentServiceAudit;
use ItechWorld\SuluTailwindThemeBundle\Consent\ThirdPartyServices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The service keys typed while the block field was free text.
 *
 * They keep working on the site, but the field now offers a list, and shows
 * empty for a key the list lacks. The check names those keys so the project
 * declares them before an editor saves over them.
 */
#[CoversClass(ConsentServiceAudit::class)]
final class ConsentServiceAuditTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE pa_page_dimension_contents (templateData TEXT NULL)');
        $this->connection->executeStatement('CREATE TABLE sn_snippet_dimension_contents (templateData TEXT NULL)');
    }

    #[Test]
    public function itNamesTheKeysTheAdminDoesNotOfferWithTheirRowCount(): void
    {
        $this->insert('pa_page_dimension_contents', ['blocks' => [
            ['type' => 'iframe', 'consentService' => 'calendly'],
            ['type' => 'code', 'consentService' => 'calendly'],
            ['type' => 'iframe', 'consentService' => 'youtube'],
            ['type' => 'iframe', 'consentService' => ''],
            ['type' => 'iframe', 'consentService' => 'embed'],
        ]]);
        $this->insert('pa_page_dimension_contents', ['blocks' => [['columns' => [['consentService' => 'typeform']]]]]);
        $this->insert('sn_snippet_dimension_contents', ['blocks' => [['consentService' => 'calendly']]]);
        $this->insert('sn_snippet_dimension_contents', null);

        self::assertSame(['calendly' => 2, 'typeform' => 1], $this->audit()->findUnknownKeys());
    }

    #[Test]
    public function aDeclaredProjectServiceIsNotReported(): void
    {
        $this->insert('pa_page_dimension_contents', ['blocks' => [['consentService' => 'calendly']]]);

        self::assertSame([], $this->audit(['calendly' => ['label' => 'Calendly']])->findUnknownKeys());
    }

    /**
     * @param array<string, array<string, string|null>> $projectServices The `consent.services` setting
     */
    private function audit(array $projectServices = []): ConsentServiceAudit
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);

        return new ConsentServiceAudit($entityManager, new ThirdPartyServices($projectServices));
    }

    /**
     * @param array<string, mixed>|null $data The template data of the row
     */
    private function insert(string $table, ?array $data): void
    {
        $this->connection->insert($table, ['templateData' => null === $data ? null : json_encode($data)]);
    }
}
