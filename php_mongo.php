<?php

/**
 * Class PHPMongo (Based on http://github.com/ibwhite/simplemongophp)
 *
 * @author PauRamon
 * @version 0.1
 *
 * Copyright 2010 Pau Ramon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 **/

class PHPMongo {

    // Holds the connection
    static private $_conn;
    static private $_database;

    static public function connect($settings){
        self::$_database = $settings['db'];
        self::$_conn = new Mongo("mongodb://${settings['user']}:${settings['password']}@${settings['uri']}:${settings['port']}/${settings['db']}");
    }

    /**
     * Returns a MongoId from a string, MongoId, array, or Dbo object
     *
     * @param mixed $obj
     * @return MongoId
     **/
     static function ObjectID($obj) {
        if ($obj instanceof MongoId) {
            return $obj;
        }
        if (is_string($obj)) {
            return new MongoId($obj);
        }
        if (is_array($obj)) {
            return $obj['_id'];
        }
        return new MongoId($obj->_id);
    }

    /**
     * Returns true if the value passed appears to be a Mongo database reference
     *
     * @param mixed $obj
     * @return boolean
     **/
    static function isRef($value) {
        if (!is_array($value)) {
            return false;
        }
        return MongoDBRef::isRef($value);
    }

    /**
     * Returns a Mongo database reference created from a collection and an id
     *
     * @param string $collection
     * @param mixed $id
     * @return array
     **/
    static function createRef($collection, $id) {
        return array('$ref' => $collection, '$id' => self::ObjectID($id));
    }

    /**
     * Returns the Mongo object array that a database reference points to
     *
     * @param array $dbref
     * @return array
     **/
    static function getRef($dbref) {
        $db = self::$_conn->selectDB(self::$_database);
        return $db->getDBRef($dbref);
    }

    /**
     * Recursively expands any database references found in an array of references,
     * and returns the expanded object.
     *
     * @param mixed $value
     * @return mixed
     **/
    static function expandRefs($value) {
        if (is_array($value)) {
            if (self::isRef($value)) {
                return self::getRef($value);
            } else {
                foreach ($value as $k => $v) {
                    $value[$k] = self::expandRefs($v);
                }
            }
        }
        return $value;
    }

    /**
     * Returns a database cursor for a Mongo find() query.
     *
     * Pass the query and options as array objects (this is more convenient than the standard
     * Mongo API especially when caching)
     *
     * $options may contain:
     *   fields - the fields to retrieve
     *   sort - the criteria to sort by
     *   limit - the number of objects to return
     *   skip - the number of objects to skip
     *
     * @param string $collection
     * @param array $query
     * @param array $options
     * @return MongoCursor
     **/
    static function find($collection, $query = array(), $options = array()) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        $fields = isset($options['fields']) ? $options['fields'] : array();
        $result = $col->find($query, $fields);
        if (isset($options['sort'])) {
            $result->sort($options['sort']);
        }
        if (isset($options['limit'])) {
            $result->limit($options['limit']);
        }
        if (isset($options['skip'])) {
            $result->skip($options['skip']);
        }
        return $result;
    }

    /**
     * Just like find, but return the results as an array (of arrays)
     *
     * @param string $collection
     * @param array $query
     * @param array $options
     * @return array
     **/
    static function finda($collection, $query = array(), $options = array()) {
        $result = self::find($collection, $query, $options);
        $array = array();
        foreach ($result as $val) {
            $array[] = $val;
        }
        return $array;
    }

    /**
     * Do a find() but return an array populated with one field value only
     *
     * @param string $collection
     * @param string $field
     * @param array $query
     * @param array $options
     * @return array
     **/
    static function findField($collection, $field, $query = array(), $options = array()) {
        $options['fields'] = array($field => 1);
        $result = self::find($collection, $query, $options);
        $array = array();
        foreach ($result as $val) {
            $array[] = $val[$field];
        }
        return $array;
    }

    /**
     * Do a find() returned as an associative array mapping one field to another
     *
     * @param string $collection
     * @param string $key_field
     * @param string $value_field
     * @param array $query
     * @param array $options
     * @return array
     **/
    static function findAssoc($collection, $key_field, $value_field, $query = array(), $options = array()) {
        $options['fields'] = array($key_field => 1, $value_field => 1);
        $result = self::find($collection, $query, $options);
        $array = array();
        foreach ($result as $val) {
            $array[$val[$key_field]] = $val[$value_field];
        }
        return $array;
    }

    /**
     * Find a single object -- like Mongo's findOne() but you can pass an id as a shortcut
     *
     * @param string $collection
     * @param mixed $id
     * @param array $options
     * @return array
     **/
    static function findOne($collection, $id, $options=array()) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        $fields = isset($options['fields']) ? $options['fields'] : array();
        if (!is_array($id)) {
            $id = array('_id' => self::ObjectID($id));
        }
        return $col->findOne($id, $fields);
    }

    /**
     * Count the number of objects matching a query in a collection (or all objects)
     *
     * @param string $collection
     * @param array $query
     * @return integer
     **/
    static function count($collection, $query = array()) {
        try{
            $col = self::$_conn->selectCollection(self::$_database, $collection);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
        if ($query) {
            $res = $col->find($query);
            return $res->count();
        } else {
            return $col->count();
        }
    }

    /**
     * Save a Mongo object -- just a simple shortcut for MongoCollection's save()
     *
     * @param string $collection
     * @param array $data
     * @return boolean
     **/
    static function save($collection, $data) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->save($data);
    }

    /**
     * Shortcut for MongoCollection's insert() method
     *
     * @param string $collection
     * @param array $a
     * @param array $options
     * @return boolean
     **/
    static function insert($collection, $a, $options = array()) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->insert($a, $options);
    }

    /**
     * Shortcut for MongoCollection's update() method
     *
     * @param string $collection
     * @param array $criteria
     * @param array $newobj
     * @param boolean $upsert
     * @return boolean
     **/
    static function update($collection, $criteria, $newobj, $options=array()) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->update($criteria, $newobj, $options);
    }

    /**
     * Shortcut for MongoCollection's findAndModify() method
     *
     * @param string $collection
     * @param array $query
     * @param array $update
     * @param array $options
     * @return doc
     **/
    static function findAndModify($collection, $query, $update, $options=array()) {
        $db = self::$_conn->selectDB(self::$_database);
        $result = $db->command(array_merge(array(
            'findandmodify' => $collection
          , 'query' => $query
          , 'update' => $update
        ), $options));
        return $result['value'];
    }

    /**
     * Shortcut for MongoCollection's remove() method, with the option of passing an id string
     *
     * @param string $collection
     * @param array $criteria
     * @param boolean $just_one
     * @return boolean
     **/
    static function remove($collection, $criteria, $just_one = false) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        if (!is_array($criteria)) {
            $criteria = array('_id' => self::ObjectID($criteria));
        }
        return $col->remove($criteria, $just_one);
    }

    /**
     * Shortcut for MongoCollection's drop() method
     *
     * @param string $collection
     * @return boolean
     **/
    static function drop($collection) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->drop();
    }

    /**
     * Shortcut for MongoCollection's batchInsert() method
     *
     * @param string $collection
     * @param array $array
     * @return boolean
     **/
    static function batchInsert($collection, $array) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->batchInsert($array);
    }

    /**
     * Shortcut for MongoCollection's ensureIndex() method
     *
     * @param string $collection
     * @param array $keys
     * @return boolean
     **/
    static function ensureIndex($collection, $keys) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->ensureIndex($keys);
    }

    /**
     * Ensure a unique index (there is no direct way to do this in the MongoCollection API now)
     *
     * @param string $collection
     * @param array $keys
     * @return boolean
     **/
    static function ensureUniqueIndex($collection, $keys) {
        $name_parts = array();
        foreach ($keys as $k => $v) {
            $name_parts[] = $k;
            $name_parts[] = $v;
        }
        $name = implode('_', $name_parts);
        $col = self::$_conn->selectCollection(self::$_database, 'system.indexes');
        $col->save(array('ns' => self::$_database . ".$collection",
                         'key' => $keys,
                         'name' => $name,
                         'unique' => true));
    }

    /**
     * Shortcut for MongoCollection's getIndexInfo() method
     *
     * @param string $collection
     * @return array
     **/
    static function getIndexInfo($collection) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->getIndexInfo();
    }

    /**
     * Shortcut for MongoCollection's deleteIndexes() method
     *
     * @param string $collection
     * @return boolean
     **/
    static function deleteIndexes($collection) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->deleteIndexes();
    }

    /**
     * Returns a database cursor for a Mongo MapReduce() query.
     *
     * @param string $collection
     * @param string $map
     * @param string $reduce
     * @return MongoCursor
     **/
    static function mapReduce($collection, $map, $reduce) {
        $db = self::$_conn->selectDB(self::$_database);
        $map = new MongoCode($map);
        $reduce = new MongoCode($reduce);
        $result = $db->command(array(
            "mapreduce" => $collection,
            "map" => $map,
            "reduce" => $reduce
        ));
        return $db->selectCollection($result['result'])->find();
    }

    /**
     * Returns an array holding he results from a distinct query
     *
     * @param string $collection
     * @param string $key
     * @param array $query
     * @return array
     **/
    static function distinct($collection, $key, $query=array()) {
        $db = self::$_conn->selectDB(self::$_database);
        return $db->command(array('distinct' => $collection, 'key' => $key, 'query' => $query));
    }

    /**
     * Returns an array holding he results from a group query
     *
     * @param string $collection
     * @param array $keys
     * @param array $initial
     * @param string $reduce
     * @param array $options
     * @return array
     **/
    static function group($collection, $keys, $initial, $reduce, $options= array()) {
        $col = self::$_conn->selectCollection(self::$_database, $collection);
        return $col->group($keys, $initial, $reduce, $options);
    }
}
