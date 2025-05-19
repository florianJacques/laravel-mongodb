<?php

namespace MongoDB\Laravel\Relations;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

use MongoDB\Laravel\Eloquent\InteractsWithBson;

use function array_map;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 */
class RefTo extends BelongsTo
{
    use InteractsWithBson;

    /**
     * The reference type
     *
     * @var string|null
     */
    protected $referenceType;

    /**
     * @inheritDoc
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel $child
     * @param string|null $referenceType
     * @param string $foreignKey
     * @param string $ownerKey
     * @param string $relationName
     */
    public function __construct(Builder $query, Model $child, $referenceType, $foreignKey, $ownerKey, $relationName)
    {
        $this->referenceType = $this->getReferenceType($referenceType);

        parent::__construct($query, $child, $foreignKey, $ownerKey, $relationName);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->ownerKey;
    }

    /**
     * @inheritDoc
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $key = $this->getOwnerKeyName();

            $this->query->where($key, '=', $this->getReference($this->getForeignKeyFrom($this->child)));
        }
    }

    /**
     * @inheritDoc
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->getOwnerKeyName();

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);
        $eagerModelKeys = array_map(function ($key) {
            return $this->getReference($key);
        }, $this->getEagerModelKeys($models));

        $this->whereInEager($whereIn, $key, $eagerModelKeys);
    }

    /**
     * @inheritDoc
     * @return TDeclaringModel
     */
    public function associate($model)
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        $this->child->setAttribute($this->foreignKey, $this->getReference($ownerKey));

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } else {
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return TDeclaringModel
     */
    public function dissociate()
    {
        unset($this->child->{$this->getForeignKeyName()});

        return $this->child->unsetRelation($this->relationName);
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param string $key
     *
     * @return string
     */
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->getForeignKeyName();
    }
}
