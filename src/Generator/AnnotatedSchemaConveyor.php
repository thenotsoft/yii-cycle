<?php

namespace Yiisoft\Yii\Cycle\Generator;

use Cycle\Annotated;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Finder\Finder;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Yii\Cycle\Exception\EmptyEntityPathsException;

final class AnnotatedSchemaConveyor extends SchemaConveyor
{
    /** @var string[] */
    protected array $entityPaths = [];

    protected int $tableNaming = Annotated\Entities::TABLE_NAMING_SINGULAR;

    protected bool $isAddedAnnotated = false;

    public function setTableNaming(int $type): void
    {
        $this->tableNaming = $type;
    }

    public function getTableNaming(): int
    {
        return $this->tableNaming;
    }

    /**
     * @param string[] $paths
     */
    public function addEntityPaths(array $paths): void
    {
        $this->entityPaths = array_merge($this->entityPaths, $paths);
    }

    public function getGenerators(): array
    {
        $this->addAnnotatedGenerators();
        return parent::getGenerators();
    }

    /**
     * Add some generators in this conveyor into the INDEX stage
     * Added generators will search for entity classes and read their annotations
     */
    protected function addAnnotatedGenerators(): void
    {
        if ($this->isAddedAnnotated) {
            return;
        }
        // autoload annotations
        AnnotationRegistry::registerLoader('class_exists');

        $this->isAddedAnnotated = true;
        $classLocator = $this->getEntityClassLocator();

        // register embeddable entities
        $this->conveyor[SchemaConveyor::STAGE_INDEX][] = new Annotated\Embeddings($classLocator);
        // register annotated entities
        $this->conveyor[SchemaConveyor::STAGE_INDEX][] = new Annotated\Entities($classLocator, null, $this->tableNaming);
        // add @Table(columns) declarations
        $this->conveyor[SchemaConveyor::STAGE_INDEX][] = Annotated\MergeColumns::class;
        // add @Table(indexes) declarations
        $this->conveyor[SchemaConveyor::STAGE_RENDER][] = Annotated\MergeIndexes::class;
    }

    protected function getEntityClassLocator(): ClassLocator
    {
        $aliases = $this->container->get(Aliases::class);
        $list = [];
        foreach ($this->entityPaths as $path) {
            $list[] = $aliases->get($path);
        }

        if (!count($list)) {
            throw new EmptyEntityPathsException();
        }

        $finder = (new Finder())
            ->files()
            ->in($list);

        return new ClassLocator($finder);
    }
}
