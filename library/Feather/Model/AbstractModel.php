<?php

namespace Feather\Model;

use Feather\Db\AbstractAdapter;

abstract class AbstractModel {

    protected $_tableName = '';
    protected $_primaryKey = '';

    protected $_adapter = null;

    public function __construct(AbstractAdapter $adapter) {
        $this->_adapter = $adapter;
    }

    public function insert($data) {
        $sql = 'insert into `'.$this->_tableName.'` set';
        $params = array();

        foreach($data as $k => $v) {
            $sql .= ' `'.$k.'` = ?,';
            $params[] = $v;
        }
        $sql = rtrim($sql, ',');
        return $this->_adapter->secureQuery($sql, $params);
    }

    public function deleteById($primaryId) {
        $sql = 'delete from `'.$this->_tableName.'` where '.$this->_primaryKey.' = ?';
        $params = array($primaryId);

        return $this->_adapter->secureQuery($sql, $params);
    }

    public function updateById($data, $primaryId) {
        $sql = 'update `'.$this->_tableName.'` set';
        $params = array();

        foreach($data as $k => $v) {
            $sql .= ' '.$k.' = ?,';
            $params[] = $v;
        }
        $sql = rtrim($sql, ',');
        $sql .= ' where '.$this->_primaryKey.' = ?';
        $params[] = $primaryId;

        return $this->_adapter->secureQuery($sql, $params);
    }

    public function findById($primaryId) {
        $sql = 'select * from `'.$this->_tableName.'` where '.$this->_primaryKey.' = ?';
        $params = array($primaryId);

        $result = $this->_adapter->secureQuery($sql, $params);
        if(empty($result)){
            return array();
        }else{
            return $result[0];
        }
    }

    public function selectForUpdate($primaryIds){
        $sql = 'select * from '.$this->_tableName.' where '.$this->_primaryKey.' in ('.$primaryIds.') for update';

        return $this->_adapter->query($sql);
    }

}// END OF CLASS
