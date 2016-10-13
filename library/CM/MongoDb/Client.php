<?php

class CM_MongoDb_Client extends CM_Class_Abstract {

    /** @var CM_MongoDb_Client|null $_client */
    private $_client = null;

    /** @var array */
    private $_config;

    /**
     * @param array $config
     */
    public function __construct(array $config) {
        $defaults = [
            'options'       => [],
            'driverOptions' =>
                [
                    'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
                ],
        ];
        $config = array_merge($config, $defaults);
        $this->_config = $config;
    }

    /**
     * @param string $collection
     * @param string $index
     * @return array
     * @throws CM_MongoDb_Exception
     */
    public function deleteIndex($collection, $index) {
        $result = $this->_getCollection($collection)->dropIndex($index);
        $this->_checkResultForErrors($result);
        return $result;
    }

    /**
     * @return string[]
     */
    public function listCollectionNames() {
        return \Functional\invoke($this->_getDatabase()->listCollections(), 'getName');
    }

    /**
     * @param string     $collection
     * @param array      $object
     * @param array|null $options
     * @return mixed insertId
     * @throws CM_MongoDb_Exception
     */
    public function insert($collection, array $object, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Insert `{$collection}`: " . CM_Params::jsonEncode($object));
        $result = $this->_getCollection($collection)->insertOne($object, $options);
        $this->_checkResultForErrors($result);
        return $result->getInsertedId();
    }

    /**
     * @param string     $collection
     * @param array[]    $objectList
     * @param array|null $options
     * @return mixed[] insertIds
     * @throws CM_MongoDb_Exception
     */
    public function batchInsert($collection, array $objectList, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Batch Insert `{$collection}`: " . CM_Params::jsonEncode($objectList));
        $dataList = \Functional\map($objectList, function (array $object) {
            return $object;
        });
        $result = $this->_getCollection($collection)->insertMany($dataList, $options);
        $this->_checkResultForErrors($result);
        return $result->getInsertedIds();
    }

    /**
     * @param string $name
     * @param array  $options
     * @return array
     */
    public function createCollection($name, array $options = null) {
        $options = (array) $options;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "create collection {$name}: " . CM_Params::jsonEncode($options));
        return $this->_getDatabase()->createCollection($name, $options);
    }

    /**
     * @param string $collection
     * @param array  $keys
     * @param array  $options
     * @return string
     * @throws CM_MongoDb_Exception
     */
    public function createIndex($collection, array $keys, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "create index on {$collection}: " .
            CM_Params::jsonEncode($keys) . ' ' . CM_Params::jsonEncode($options));
        $result = $this->_getCollection($collection)->createIndex($keys, $options);
        return $result;
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array|null $update
     * @param array|null $projection
     * @param array|null $options
     * @return array|null
     */
    public function findOneAndUpdate($collection, array $criteria, array $update = null, array $projection = null, array $options = null) {
        $options = (array) $options;
        if (null !== $projection) {
            $options['projection'] = $projection;
        }
        if (!empty($options['new'])) {
            $options['returnDocument'] = \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        }
        $result = $this->_getCollection($collection)->findOneAndUpdate($criteria, $update, $options);
        return (null !== $result) ? (array) $result : null;
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array|null $replace
     * @param array|null $projection
     * @param array|null $options
     * @return array|null
     */
    public function findOneAndReplace($collection, array $criteria, array $replace = null, array $projection = null, array $options = null) {
        $options = (array) $options;
        if (null !== $projection) {
            $options['projection'] = $projection;
        }
        if (!empty($options['new'])) {
            $options['returnDocument'] = \MongoDB\Operation\FindOneAndReplace::RETURN_DOCUMENT_AFTER;
        }
        $result = $this->_getCollection($collection)->findOneAndReplace($criteria, $replace, $options);
        return (null !== $result) ? (array) $result : null;
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array|null $projection
     * @param array|null $options
     * @return array|null
     */
    public function findOneAndDelete($collection, array $criteria, array $projection = null, array $options = null) {
        $options = (array) $options;
        if (null !== $projection) {
            $options['projection'] = $projection;
        }
        $result = $this->_getCollection($collection)->findOneAndDelete($criteria, $options);
        return (null !== $result) ? (array) $result : null;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $projection
     * @param array|null $aggregation
     * @return array|null
     */
    public function findOne($collection, array $criteria = null, array $projection = null, array $aggregation = null) {
        $criteria = (array) $criteria;
        $projection = (array) $projection;
        if ($aggregation) {
            array_push($aggregation, ['$limit' => 1]);
            $resultSet = $this->find($collection, $criteria, $projection, $aggregation);
            $resultSet->rewind();
            $result = $resultSet->current();
        } else {
            $result = $this->_getCollection($collection)->findOne($criteria, $projection);
            CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "findOne `{$collection}`: " .
                CM_Params::jsonEncode(['projection' => $projection, 'criteria' => $criteria]));
        }

        return $result;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $projection
     * @param array|null $aggregation
     * @param array|null $options
     * @return MongoDB\Driver\Cursor
     *
     * When using aggregation, $criteria and $projection, if defined, automatically
     * function as `$match` and `$project` operator respectively at the front of the pipeline
     */
    public function find($collection, array $criteria = null, array $projection = null, array $aggregation = null, array $options = null) {
        $batchSize = null;
        $defaultOptions = [];
        if (isset(self::_getConfig()->batchSize)) {
            $batchSize = (int) self::_getConfig()->batchSize;
            $defaultOptions = ['batchSize' => $batchSize];
        }
        $options = array_merge($defaultOptions, (array) $options);
        $criteria = (array) $criteria;
        $projection = (array) $projection;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "find `{$collection}`: " .
            CM_Params::jsonEncode(['projection' => $projection, 'criteria' => $criteria, 'aggregation' => $aggregation]));
        $collection = $this->_getCollection($collection);
        if ($aggregation) {
            $pipeline = $aggregation;
            if ($projection) {
                array_unshift($pipeline, ['$project' => $projection]);
            }
            if ($criteria) {
                array_unshift($pipeline, ['$match' => $criteria]);
            }
            if (!isset($options['useCursor'])) {
                $options['useCursor'] = true;
            }
            $resultCursor = $collection->aggregate($pipeline, $options);
        } else {
            if ($projection) {
                $options['projection'] = $projection;
            }
            $resultCursor = $collection->find($criteria, $options);
        }
        return $resultCursor;
    }

    /**
     * @param $collection
     * @return \MongoDB\Model\IndexInfoIterator
     */
    public function getIndexInfo($collection) {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "indexInfo {$collection}");
        return $this->_getCollection($collection)->listIndexes();
    }

    /**
     * @param string $collection
     * @param array  $index
     * @return bool
     */
    public function hasIndex($collection, array $index) {
        $indexInfo = $this->getIndexInfo($collection);
        return \Functional\some($indexInfo, function (\MongoDB\Model\IndexInfo $indexInfo) use ($index) {
            return array_keys($index) === array_keys($indexInfo->getKey()) && $index == $indexInfo->getKey();
        });
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $aggregation
     * @param int|null   $limit
     * @param int|null   $offset
     * @return int
     */
    public function count($collection, array $criteria = null, array $aggregation = null, $limit = null, $offset = null) {
        $criteria = (array) $criteria;
        $limit = (int) $limit;
        $offset = (int) $offset;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "count `{$collection}`: " .
            CM_Params::jsonEncode(['criteria' => $criteria, 'aggregation' => $aggregation]));
        if ($aggregation) {
            $pipeline = $aggregation;
            if ($criteria) {
                array_unshift($pipeline, ['$match' => $criteria]);
            }
            if ($offset) {
                array_push($pipeline, ['$skip' => $offset]);
            }
            if ($limit) {
                array_push($pipeline, ['$limit' => $limit]);
            }
            array_push($pipeline, ['$group' => ['_id' => null, 'count' => ['$sum' => 1]]]);
            array_push($pipeline, ['$project' => ['_id' => 0, 'count' => 1]]);
            $result = $this->_getCollection($collection)->aggregate($pipeline);
            if (!empty($result['result'])) {
                return $result['result'][0]['count'];
            }
            return 0;
        } else {
            $count = $this->_getCollection($collection)->count($criteria);
            if ($offset) {
                $count -= $offset;
            }
            if ($limit) {
                $count = min($count, $limit);
            }
            return max(0, $count);
        }
    }

    /**
     * @param string $collection
     * @return array
     * @throws CM_MongoDb_Exception
     */
    public function drop($collection) {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "drop `{$collection}`");
        $result = $this->_getCollection($collection)->drop();
        $this->_checkResultForErrors($result);
        return $result;
    }

    /**
     * @return array
     */
    public function dropDatabase() {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "drop database {$this->_getDatabaseName()}");
        return $this->_getDatabase()->drop();
    }

    /**
     * @return bool
     */
    public function databaseExists() {
        return \Functional\contains($this->_listDatabaseNames(), $this->_getDatabaseName());
    }

    /**
     * @param string $collection
     * @return boolean
     */
    public function existsCollection($collection) {
        return \Functional\contains($this->listCollectionNames(), (string) $collection);
    }

    /**
     * @param string|MongoId $id
     * @return boolean
     */
    public function isValidObjectId($id) {
        return MongoId::isValid($id);
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array      $newObject
     * @param array|null $options
     * @return MongoCursor
     * @throws CM_MongoDb_Exception
     */
    public function update($collection, array $criteria, array $newObject, array $options = null) {
        $options = (array) $options;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Update `{$collection}`: " .
            CM_Params::jsonEncode(['criteria' => $criteria, 'newObject' => $newObject]));
        $result = $this->_getCollection($collection)->update($criteria, $newObject, $options);
        $this->_checkResultForErrors($result);
        return is_array($result) ? $result['n'] : $result;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $options
     * @return mixed
     * @throws CM_MongoDb_Exception
     */
    public function remove($collection, array $criteria = null, array $options = null) {
        $criteria = $criteria ?: array();
        $options = $options ?: array();
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Remove `{$collection}`: " . CM_Params::jsonEncode($criteria));
        $result = $this->_getCollection($collection)->remove($criteria, $options);
        $this->_checkResultForErrors($result);
        return is_array($result) ? $result['n'] : $result;
    }

    /**
     * @param string|null $id
     * @return MongoId
     */
    public function getObjectId($id = null) {
        return new MongoId($id);
    }

    /**
     * @param array|boolean|MongoDB\InsertOneResult|MongoDB\InsertManyResult|MongoDB\DeleteResult|MongoDB\UpdateResult $result
     * @throws CM_MongoDb_Exception
     */
    protected function _checkResultForErrors($result) {
        if ($result instanceof MongoDB\InsertOneResult) {
            if (!$result->isAcknowledged()) {
                throw new CM_MongoDb_Exception('Operation not acknowledged');
            }
        } elseif ($result instanceof MongoDB\InsertManyResult) {
            if (!$result->isAcknowledged()) {
                throw new CM_MongoDb_Exception('Operation not acknowledged');
            }
        } elseif ($result instanceof MongoDB\DeleteResult) {
            if (!$result->isAcknowledged()) {
                throw new CM_MongoDb_Exception('Operation not acknowledged');
            }
        } elseif ($result instanceof MongoDB\UpdateResult) {
            if (!$result->isAcknowledged()) {
                throw new CM_MongoDb_Exception('Operation not acknowledged');
            }
        } elseif (true !== $result && empty($result['ok'])) {
            throw new CM_MongoDb_Exception('Cannot perform mongodb operation', null, ['result' => $result]);
        }
    }

    /**
     * @return MongoDB\Client
     */
    protected function _getClient() {
        if (null === $this->_client) {
            $this->_client = new MongoDB\Client($this->_config['server'], $this->_config['options'], $this->_config['driverOptions']);
        }
        return $this->_client;
    }

    /**
     * @return string[]
     */
    protected function _listDatabaseNames() {
        $databases = $this->_getClient()->listDatabases();
        return \Functional\invoke($databases, 'getName');
    }

    /**
     * @return string
     */
    protected function _getDatabaseName() {
        return CM_Bootloader::getInstance()->getDataPrefix() . $this->_config['db'];
    }

    /**
     * @return MongoDB\Database
     * @throws CM_Exception_Nonexistent
     */
    protected function _getDatabase() {
        return $this->_getClient()->selectDatabase($this->_getDatabaseName());
    }

    /**
     * @param string $collection
     * @return \MongoDB\Collection
     */
    protected function _getCollection($collection) {
        $collection = (string) $collection;
        return $this->_getDatabase()->selectCollection($collection);
    }
}
