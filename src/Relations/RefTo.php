<?php

namespace MongoDB\Laravel\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

use function array_map;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 */
class RefTo extends RefToOrMany
{
    /** @inheritDoc */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /** @inheritDoc */
    public function getResults()
    {
        if (is_null($this->getForeignKeyFrom($this->child))) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
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
     */
    public function addEagerConstraints(array $models)
    {
        $key = $this->getOwnerKeyName();

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);
        $eagerModelKeys = array_map(function ($key) {
            return $this->getReference($key);
        }, $this->getEagerModelKeys($models));

        $this->whereInEager($whereIn, $key, $eagerModelKeys);
    }

    /**
     * @param array $models
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = [];

        foreach ($models as $model) {
            if (! is_null($value = $this->getForeignKeyFrom($model))) {
                $keys[] = $value;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @param array $models
     * @param EloquentCollection $results
     * @param $relation
     * @return array
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $attribute = $this->getDictionaryKey($this->getRelatedKeyFrom($result));

            $dictionary[$attribute] = $result;
        }

        foreach ($models as $model) {
            $attribute = $this->getDictionaryKey($this->getForeignKeyFrom($model));

            if (isset($dictionary[$attribute])) {
                $model->setRelation($relation, $dictionary[$attribute]);
            }
        }

        return $models;
    }

    /**
     * @param $model
     * @return Model
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
}
