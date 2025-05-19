<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Illuminate\Support\Str;
use MongoDB\Driver\Exception\LogicException;
use MongoDB\Laravel\Eloquent\Model as DocumentModel;
use MongoDB\Laravel\Relations\RefTo;

use function debug_backtrace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Cross-database relationships between SQL and MongoDB.
 * Use this trait in SQL models to define relationships with MongoDB models.
 */
trait HasReference
{
    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param class-string $related
     * @param string|null $refType
     * @param string|null  $foreignKey
     * @param string|null  $ownerKey
     * @param string|null  $relation
     *
     * @return \MongoDB\Laravel\Relations\RefTo
     */
    public function refTo($related, $referenceType = null, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ($relation === null) {
            $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        }

        // Check if it is a relation with an original model.
        if (! DocumentModel::isDocumentModel($related)) {
            throw new LogicException('Related model must be a document model.');
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if ($foreignKey === null) {
            $foreignKey = Str::snake($relation) . '_id';
        }

        $instance = new $related();

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new RefTo($query, $this, $referenceType, $foreignKey, $ownerKey, $relation);
    }
}
