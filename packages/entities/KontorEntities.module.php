<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Entities\Application\EntityBuilderService;
use Kontor\Entities\Application\EntityRecordService;
use Kontor\Entities\Application\EntityRelationService;
use Kontor\Entities\Application\EntitySchemaService;
use Kontor\Entities\Application\EntityViewService;
use Kontor\Entities\Health\EntitiesHealthCheck;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityViewRepository;
use Kontor\Entities\Infrastructure\API\CustomEntityResource;
use Kontor\Entities\Migrations\Migration0001CreateDefinitionsTable;
use Kontor\Entities\Migrations\Migration0002CreateFieldsTable;
use Kontor\Entities\Migrations\Migration0003CreateRecordsTable;
use Kontor\Entities\Migrations\Migration0004CreateViewsTable;

/**
 * KontorEntities bootstrap module (kontor.md Substage 7.3). Third
 * component of Stage 7. Depends only on kontor/core — the "relations"
 * milestone reuses Core's own RelationRepository directly rather than a
 * parallel table.
 */
class KontorEntities extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Custom Entities',
            'summary' => 'Entity builder, fields, relations, views, permissions, API exposure.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorEntities',
            'icon' => 'cube',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-entities-definition-manage' => 'Create and edit custom entity definitions',
                'kontor-entities-record-view' => 'View custom entity records',
                'kontor-entities-record-manage' => 'Create and edit custom entity records',
                'kontor-entities-view-manage' => 'Create and edit saved views',
            ],
        ];
    }

    private ?EntityDefinitionRepository $definitionRepository = null;
    private ?EntityFieldRepository $fieldRepository = null;
    private ?EntityRecordRepository $recordRepository = null;
    private ?EntityViewRepository $viewRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        if ($this->wire()->modules->isInstalled('KontorAPI')) {
            /** @var KontorAPI $api */
            $api = $this->wire()->modules->get('KontorAPI');
            foreach ($this->apiResourceFields() as $entityKey => $fields) {
                $api->resourceRegistry()->register(new CustomEntityResource(
                    $entityKey,
                    $fields,
                    $this->definitionRepository(),
                    $this->recordRepository(),
                    $this->records(),
                ));
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function apiResourceFields(): array
    {
        $resources = [];
        foreach ($this->definitionRepository()->activeExposed() as $definition) {
            foreach ($this->fieldRepository()->forDefinition($definition->uid->toString()) as $field) {
                $resources[$definition->entityKey][$field->fieldKey] = $field->fieldType;
            }
            $resources[$definition->entityKey] ??= [];
        }

        return $resources;
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorEntities', $language, $strings);
        }
    }

    public function definitionRepository(): EntityDefinitionRepository
    {
        return $this->definitionRepository ??= new EntityDefinitionRepository($this->pdo(), $this->organizations());
    }

    public function fieldRepository(): EntityFieldRepository
    {
        return $this->fieldRepository ??= new EntityFieldRepository($this->pdo(), $this->organizations());
    }

    public function recordRepository(): EntityRecordRepository
    {
        return $this->recordRepository ??= new EntityRecordRepository($this->pdo(), $this->organizations());
    }

    public function viewRepository(): EntityViewRepository
    {
        return $this->viewRepository ??= new EntityViewRepository($this->pdo(), $this->organizations());
    }

    public function builder(): EntityBuilderService
    {
        return new EntityBuilderService($this->definitionRepository(), $this->fieldRepository());
    }

    public function records(): EntityRecordService
    {
        return new EntityRecordService($this->definitionRepository(), $this->fieldRepository(), $this->recordRepository());
    }

    public function views(): EntityViewService
    {
        return new EntityViewService($this->viewRepository(), $this->recordRepository());
    }

    public function relations(): EntityRelationService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return new EntityRelationService($kontor->container()->get(RelationRepository::class));
    }

    public function schema(): EntitySchemaService
    {
        return new EntitySchemaService($this->definitionRepository(), $this->fieldRepository());
    }

    public function healthCheck(): EntitiesHealthCheck
    {
        return new EntitiesHealthCheck($this->pdo(), $this->recordRepository());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateDefinitionsTable(),
            new Migration0002CreateFieldsTable(),
            new Migration0003CreateRecordsTable(),
            new Migration0004CreateViewsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('entities', self::getModuleInfo()['version'], 'entities');
        $components->enable('entities');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('entities', self::getModuleInfo()['version'], 'entities');
        $components->enable('entities');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Custom Entities module removed. Entity definitions and records were kept intact.'));
    }
}
