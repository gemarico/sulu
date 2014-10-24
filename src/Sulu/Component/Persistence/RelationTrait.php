<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Persistence;

use Traversable;

/**
 * Offers methods for easier handling of relations
 * @package Sulu\Component\Persistence
 */
trait RelationTrait
{
    /**
     * This method processes a put request (delete non-existing entities, update existing entities, add new
     * entries), and let the single actions be modified by callbacks
     *
     * @param Traversable $entities The list of entities to work on
     * @param array $requestEntities The entities as retrieved from the request
     * @param callable $getId Return id of entity
     * @param callable $add
     * @param callable $update
     * @param callable $delete
     * @return bool
     */
    public function processSubEntities(
        $entities,
        array $requestEntities,
        callable $getId,
        callable $add = null,
        callable $update = null,
        callable $delete = null
    )
    {
        // define a matching function
        $matchFunction = function($requestEntities, $entity, &$matchedEntry, &$matchedKey) use ($getId) {
            $this->findMatch($requestEntities, $getId($entity), $matchedEntry, $matchedKey);
        };

        return $this->compareData($entities, $requestEntities, $matchFunction, $add, $update, $delete);
    }

    /**
     * Compares entities with data array and calls the given callbacks
     *
     * @param Traversable $entities The list of entities to work on
     * @param array $requestEntities The entities as retrieved from the request
     * @param callable $compare return true if data matches entity
     * @param callable $add
     * @param callable $update
     * @param callable $delete
     * @return bool
     */
    public function compareEntitiesWithData(
        $entities,
        array $requestEntities,
        callable $compare,
        callable $add = null,
        callable $update = null,
        callable $delete = null
    )
    {
        // define a matching function
        $matchFunction = function($requestEntities, $entity, &$matchedEntry, &$matchedKey) use ($compare) {
            $this->findMatchByCallback($requestEntities, $entity, $compare, $matchedEntry, $matchedKey);
        };

        return $this->compareData($entities, $requestEntities, $matchFunction, $add, $update, $delete);
    }

    /**
     * Tries to find an given id in a given set of entities. Returns the entity itself and its key with the
     * $matchedEntry and $matchKey parameters.
     *
     * @param array $requestEntities The set of entities to search in
     * @param integer $id The id to search
     * @param array $matchedEntry
     * @param string $matchedKey
     */
    protected function findMatch($requestEntities, $id, &$matchedEntry, &$matchedKey)
    {
        $matchedEntry = null;
        $matchedKey = null;
        if (!empty($requestEntities)) {
            foreach ($requestEntities as $key => $entity) {
                if (isset($entity['id']) && $entity['id'] == $id) {
                    $matchedEntry = $entity;
                    $matchedKey = $key;
                    break;
                }
            }
        }
    }

    /**
     * @param $requestEntities
     * @param $entity
     * @param $compare
     * @param $matchedEntry
     * @param $matchedKey
     */
    protected function findMatchByCallback($requestEntities, $entity, $compare, &$matchedEntry, &$matchedKey)
    {
        $matchedEntry = null;
        $matchedKey = null;
        if (!empty($requestEntities)) {
            foreach ($requestEntities as $key => $data) {
                if ($compare($entity, $data)) {
                    $matchedEntry = $entity;
                    $matchedKey = $key;
                    break;
                }
            }
        }
    }

    /**
     * function compares entities with data of array and makes callback
     *
     * @param $entities
     * @param array $requestEntities
     * @param callable $compare
     * @param callable $add
     * @param callable $update
     * @param callable $delete
     * @return bool
     */
    public function compareData(
        $entities,
        array $requestEntities,
        callable $compare = null,
        callable $add = null,
        callable $update = null,
        callable $delete = null
    )
    {
        $success = true;

        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $matchedEntry = null;
                $matchedKey = null;

                // find match callback
                $compare($requestEntities, $entity, $matchedEntry, $matchedKey);

                if ($matchedEntry == null && $delete != null) {
                    // delete entity if it is not listed anymore
                    $delete($entity);
                } elseif ($update != null) {
                    // update entity if it is matched
                    $success = $update($entity, $matchedEntry);
                    if (!$success) {
                        break;
                    }
                }

                // Remove done element from array
                if ($matchedKey !== null) {
                    unset($requestEntities[$matchedKey]);
                }
            }
        }

        // The entity which have not been delete or updated have to be added
        if (!empty($requestEntities) && $add != null) {
            foreach ($requestEntities as $entity) {
                if (!$success) {
                    break;
                }
                $success = $add($entity);
            }
        }

        return $success;
    }
} 
