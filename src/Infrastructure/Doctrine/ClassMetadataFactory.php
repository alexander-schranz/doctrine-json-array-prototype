<?php

namespace App\Infrastructure\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;

class ClassMetadataFactory extends DoctrineClassMetadataFactory
{
    private static $initializedAllMetadata = false;

    public function getAllMetadata(): array
    {
        if (!static::$initializedAllMetadata) {
            // TODO avoid duplicated call of getAllMetadata
            parent::getAllMetadata();

            static::$initializedAllMetadata = true;
        }

        return parent::getAllMetadata();
    }
}
