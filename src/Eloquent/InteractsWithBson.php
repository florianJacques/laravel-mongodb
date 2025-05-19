<?php

namespace MongoDB\Laravel\Eloquent;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;

trait InteractsWithBson
{
    private static $referenceTypeAliases = [
        'oid' => ObjectId::class,
        'binary' => Binary::class,
    ];

    /**
     * @param $type
     * @return mixed|string
     */
    protected function getReferenceType($type): mixed
    {
        if (array_key_exists($type, self::$referenceTypeAliases)) {
            return self::$referenceTypeAliases[$type];
        }

        return $type;
    }

    /**
     * @param $value
     * @return mixed|Binary|ObjectId
     */
    protected function getReference($value): mixed
    {
        /** Bson objectId */
        if ($this->referenceType === ObjectId::class) {
            return $value instanceof ObjectId ? $value : new ObjectId($value);
        }

        /** Bson Binary */
        if ($this->referenceType === Binary::class) {
            if ($value instanceof Binary) {
                return $value;
            }

            if (is_string($value) && strlen($value) === 16) {
                return new Binary($value, Binary::TYPE_UUID);
            }

            return new Binary(hex2bin(str_replace('-', '', $value)), Binary::TYPE_UUID);
        }

        return $value;
    }
}
