<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\Driver\StaticPHPDriver;

class MetadataLoader implements EventSubscriber
{
    /**
     * @var string
     */
    private $proxyDirectory;

    /**
     * @var StaticPHPDriver
     */
    private $staticPHPDriver;

    public function __construct(
        string $proxyDirectory,
        StaticPHPDriver $staticPHPDriver
    ) {
        $this->proxyDirectory = $proxyDirectory;
        $this->staticPHPDriver = $staticPHPDriver;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        /** @var ClassMetadataInfo<object> $metadata */
        $metadata = $event->getClassMetadata();
        $reflection = $metadata->getReflectionClass();
        $className = $reflection->getName();

        foreach ($metadata->getFieldNames() as $fieldName) {
            $typeOfField = $metadata->getTypeOfField($fieldName);
            $mapping = $metadata->getFieldMapping($fieldName);

            if ($typeOfField !== 'json') {
                continue;
            }

            if (! ($mapping['options']['string-array'] ?? false)) {
                continue;
            }

            // remove exist metadata for field via reflection
            $metadataReflection = new \ReflectionClass($metadata);

            $propertyFieldMappingsReflection = $metadataReflection->getProperty('fieldMappings');
            $propertyFieldMappingsReflection->setAccessible(true);
            $fieldMappings = $propertyFieldMappingsReflection->getValue($metadata);
            unset($fieldMappings[$fieldName]);
            $propertyFieldMappingsReflection->setValue($metadata, $fieldMappings);

            $propertyFieldNamesReflection = $metadataReflection->getProperty('fieldNames');
            $propertyFieldNamesReflection->setAccessible(true);
            $fieldNames = $propertyFieldNamesReflection->getValue($metadata);
            unset($fieldNames[\array_search($fieldName, $fieldNames)]);
            $propertyFieldNamesReflection->setValue($metadata, $fieldNames);

            $propertyColumnNamesReflection = $metadataReflection->getProperty('columnNames');
            $propertyColumnNamesReflection->setAccessible(true);
            $columnNames = $propertyColumnNamesReflection->getValue($metadata);
            unset($columnNames[$fieldName]);
            $propertyFieldNamesReflection->setValue($metadata, $columnNames);

            // create proxy relation entity
            $fqcnClassName = 'ORMArrayProxy\\' . 'Proxy_' . sha1($className . '_' . $fieldName);

            try {
                $reflectionClass = new \ReflectionClass($fqcnClassName);
                $fileExists = file_exists($reflectionClass->getFileName());
            } catch (\ReflectionException $e) {
                $fileExists = false;
            }

            if (!$fileExists) {
                $this->dumpProxyClass(
                    $fqcnClassName,
                    $className,
                    $fieldName,
                    $metadata->getTableName() . '_' . $fieldName,
                    $metadata->getIdentifier()[0]
                );
            }

            // refresh metadata cache with new proxy entity
            $metadataFactory = $event->getEntityManager()->getMetadataFactory();
            if (!$metadataFactory->hasMetadataFor($fqcnClassName)) {
                // TODO metadata is correctly loaded here but not persisted
                //      e.g.:
                //          rm -rf var/cache
                //          bin/console doctrine:schema:update --dump-sql
                //          # metadata is not shown
                //          bin/console doctrine:schema:update --dump-sql
                //          # metadata is now shown

                // $metadata = $metadataFactory->getMetadataFor($fqcnClassName);

                $configuration = $event->getEntityManager()->getConfiguration();
                $metadataDriver = $configuration->getMetadataDriverImpl();
                $reflectionService = $metadataFactory->getReflectionService();

                $metadata = new ClassMetadata($fqcnClassName, $configuration->getNamingStrategy());
                $metadata->initializeReflection($reflectionService);

                $metadataDriver->loadMetadataForClass(
                    $fqcnClassName,
                    $metadata
                );

                // reset StaticPHPDriver classNames
                $reflection = new \ReflectionClass($this->staticPHPDriver);
                if (!$reflection->hasProperty('classNames')) {
                    $reflection = $reflection->getParentClass();
                }
                $propertyClassNamesReflection = $reflection->getProperty('classNames');
                $propertyClassNamesReflection->setAccessible(true);
                $propertyClassNamesReflection->setValue($this->staticPHPDriver, null);
            }

            // add new relation
            $metadata->mapOneToMany([
                'fieldName' => $fieldName,
                'targetEntity' => $fqcnClassName,
                'mappedBy' => 'object',
            ]);
        }
    }

    private function dumpProxyClass(
        string $fqcnClassName,
        string $targetEntity,
        string $inversedBy,
        string $tableName,
        string $referenceColumn
    ): void {
        $classNameParts = explode('\\', $fqcnClassName);
        $className = $classNameParts[count($classNameParts) - 1];

        $classContent = $this->getClassContent();

        $classContent = (\str_replace('%CLASSNAME%', $className, $classContent));
        $classContent = (\str_replace('%TARGET_ENTITY%', $targetEntity, $classContent));
        $classContent = (\str_replace('%INVERSED_BY%', $inversedBy, $classContent));
        $classContent = (\str_replace('%TABLE_NAME%', $tableName, $classContent));
        $classContent = (\str_replace('%REFERENCE_COLUMN%', $referenceColumn, $classContent));
        $filePath = $this->proxyDirectory . DIRECTORY_SEPARATOR . $className . '.php';

        file_put_contents($filePath, $classContent);

        require_once($filePath);
    }

    private function getClassContent(): string
    {
        return <<<EOT
<?php

namespace ORMArrayProxy;

use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @internal
 */
class %CLASSNAME% implements \Stringable
{
    /**
     * @var null|string
     */
    public static \$targetEntity = '%TARGET_ENTITY%';

    /**
     * @var null|string
     */
    public static \$inversedBy = '%INVERSED_BY%';

    /**
     * @var null|string
     */
    public static \$tableName = '%TABLE_NAME%';

    /**
     * @var null|string
     */
    public static \$referenceColumn =  '%REFERENCE_COLUMN%';
    
    /**
     * @var object
     */
    protected \$object;

    /**
     * @var string
     */
    protected \$string = '';

    public function __toString(): string
    {
        return \$this->string;
    }

    public static function loadMetadata(ClassMetadata \$metadata)
    {
        \$builder = new ClassMetadataBuilder(\$metadata);

        \$builder->setTable(static::\$tableName);

        \$builder->createManyToOne(
            'object',
            static::\$targetEntity
        )
            ->inversedBy(static::\$inversedBy)
            ->addJoinColumn(
                'object_id',
                static::\$referenceColumn,
                true,
                false,
                'CASCADE'
            )
            ->makePrimaryKey()
            ->build();

        \$builder->createField('string', 'string')
            ->columnName('string')
            ->makePrimaryKey()
            ->build();

        \$builder->addIndex(['string'], 'idx_string');
        \$builder->addIndex(['string', 'object_id'], 'idx_object_string');
    }
}
EOT;
    }
}
