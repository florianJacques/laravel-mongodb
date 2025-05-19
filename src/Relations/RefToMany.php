<?php

namespace MongoDB\Laravel\Relations;


use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Support\Arr;

use function array_map;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 */
class RefToMany extends RefToOrMany
{
    /** @inheritDoc */
    public function getResults()
    {
        return ! is_null($this->getParentKey())
            ? $this->query->get()
            : $this->related->newCollection();
    }

    /** @inheritDoc */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * @inheritDoc
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $key = $this->getOwnerKeyName();

            $modelKeys = array_map(function ($key) {
                return $this->getReference($key);
            }, Arr::wrap($this->getForeignKeyFrom($this->child)));

            $this->query->whereIn($key, $modelKeys);
        }
    }

    /**
     * @inheritDoc
     * @return void
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
    public function getEagerModelKeys(array $models)
    {
        $keys = [];

        foreach ($models as $model) {
            if (! is_null($value = $this->getForeignKeyFrom($model))) {
                $keys = array_merge($keys, Arr::wrap($value));
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * @param array $models
     * @param EloquentCollection $results
     * @param $relation
     * @return array|Model[]
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $this->getDictionaryKey($this->getRelatedKeyFrom($result));
            $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $foreignKeys = Arr::wrap($this->getForeignKeyFrom($model));

            $related = [];
            foreach ($foreignKeys as $key) {
                $dictKey = $this->getDictionaryKey($key);
                if (isset($dictionary[$dictKey])) {
                    $related[] = $dictionary[$dictKey];
                }
            }

            if (!empty($related)) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($related)
                );
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
        $ownerKeys = Arr::wrap($ownerKey);

        $ownerKeys = array_unique(array_map(function ($key) {
            return $this->getDictionaryKey($key);
        }, $ownerKeys));

        sort($ownerKeys);

        $ownerKeys = array_values(array_map(function ($key) {
            return $this->getReference($key);
        }, $ownerKeys));

        $this->child->setAttribute($this->foreignKey, $ownerKeys);

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } else {
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    /**
     * @param $ids
     * @return int
     */
    public function dissociate($ids = [])
    {
        $ids = array_map(function ($key) {
            return $this->getDictionaryKey($key);
        }, $this->getIdsArrayFrom($ids));

        $foreignIds = array_map(function ($key) {
            return $this->getDictionaryKey($key);
        }, Arr::wrap($this->getForeignKeyFrom($this->child)));

        foreach ($foreignIds as $i => $record) {
            if (in_array($record, $ids)) {
                unset($foreignIds[$i]);
            }
        }

        $modelKeys = array_map(function ($key) {
            return $this->getReference($key);
        }, $foreignIds);

        if (empty($modelKeys)) {
            unset($this->child->{$this->getForeignKeyName()});
            $this->child->unsetRelation($this->relationName);
        } else {
            $this->child->setAttribute($this->foreignKey, $modelKeys);
            $this->child->setRelation($this->relationName, $modelKeys);
        }

        return count($modelKeys);
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
     * @param $ids
     * @return array|mixed
     */
    protected function getIdsArrayFrom($ids)
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as &$id) {
            if ($id instanceof Model) {
                $id = $id->getKey();
            }
        }

        return $ids;
    }
}
