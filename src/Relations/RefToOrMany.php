<?php

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\ComparesRelatedModels;
use Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\Laravel\Eloquent\InteractsWithBson;
use function Illuminate\Support\enum_value;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @template TResult
 * @extends Relation<TRelatedModel, TDeclaringModel, TResult>
 */
abstract class RefToOrMany extends Relation
{
    use ComparesRelatedModels,
        InteractsWithDictionary,
        SupportsDefaultModels,
        InteractsWithBson;

    /**
     * The child model instance of the relation.
     *
     * @var TDeclaringModel
     */
    protected $child;

    /**
     * The reference type
     *
     * @var string|null
     */
    protected $referenceType;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The associated key on the parent model.
     *
     * @var string
     */
    protected $ownerKey;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * @param Builder $query
     * @param Model $child
     * @param $referenceType
     * @param $foreignKey
     * @param $ownerKey
     * @param $relationName
     */
    public function __construct(Builder $query, Model $child, $referenceType, $foreignKey, $ownerKey, $relationName)
    {
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;
        $this->foreignKey = $foreignKey;
        $this->child = $child;
        $this->referenceType = $this->getReferenceType($referenceType);

        parent::__construct($query, $child);
    }

    /**
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->ownerKey;
    }

    /**
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->getForeignKeyFrom($this->child);
    }

    /**
     * @return void
     */
    public function touch()
    {
        if (! is_null($this->getParentKey())) {
            parent::touch();
        }
    }

    /**
     * @param Model $model
     * @return mixed
     */
    protected function getRelatedKeyFrom(Model $model)
    {
        return $model->{$this->ownerKey};
    }

    /**
     * @param array $models
     * @param $relation
     * @return array|Model[]
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * @param Model $parent
     * @return Model
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance();
    }

    /**
     * @param Model $model
     * @return mixed
     */
    protected function getForeignKeyFrom(Model $model)
    {
        $foreignKey = $model->{$this->foreignKey};

        return enum_value($foreignKey);
    }

    /**
     * @param Builder $query
     * @param Builder $parentQuery
     * @param $columns
     * @return Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return $query;
    }

    /**
     * @return Model
     */
    public function getChild()
    {
        return $this->child;
    }

    /**
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * @return string
     */
    public function getOwnerKeyName()
    {
        return $this->ownerKey;
    }

    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * @return string
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->foreignKey;
    }
}
