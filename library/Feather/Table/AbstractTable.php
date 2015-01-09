<?php
namespace Feather\Table;
#########################################################################
# File Name: AbstractTable.php
# Desc: 
# Author: liufeng
# Created Time: 2014年09月25日 星期四 15时11分36秒
#########################################################################

abstract class AbstractTable {

    const TYPE_DESC = 'DESC';

    const TYPE_ASC = 'ASC';
    
    //default configuration for the db connection,
    //其中is_persistent标识是否是长连接
    public static $defaultConfig = array(
        'host'          => '', 
        'port'          => 3306,
        'username'      => '', 
        'password'      => '', 
        'dbname'        => '', 
        'charset'       => 'utf8',
        'is_persistent' => false,
    );  

    protected $_config = array();

    //db connection
    protected $_connection = null;

    //最终执行的sql语句 
    protected $_query;

    // 查询语句 like 'fieldname' => 'value'
    protected $_where = array();

    // 排序条件
    protected $_orderBy = array(); 

    // 分组条件
    protected $_groupBy = array(); 

    // 绑定的值
    protected $_bindParams = array();

    //数据表名
    protected $_tableName = null;

    //表主键名
    protected $_primary = null;

    public $count = 0;

    // pdo最后执行语句的错误
    protected $_sthError;

    /**
    * Create a Db adapter instance
    *
    * @param array $config Database config
    */
    public function __construct($config) {

        $this->_config = array_merge(self::$defaultConfig, $config);
        $this->connect();
    }

    public function __toString() {
        return md5(serialize($this->_config));
    }

    /**
     * Close connection
     */
    public function __destruct(){
        if ($this->_connection){
            $this->_connection = null;
        }
    }

    /**
     * 重置域。值置为空，所有查询操作完成后调用此方法。 
     */
    protected function reset(){
        $this->_where = array();
        $this->_orderBy = array();
        $this->_groupBy = array(); 
        $this->_bindParams = array();
        $this->_query = null;
        $this->count = 0;
    }

    /**
     * 关闭链接
     */
    public function close(){
        $this->_connection = null;
    }

    abstract public function query($sql);

    abstract public function rawQuery ($query, $bindParams = null, $sanitize = true);

    abstract public function get($numRows = null, $columns = '*');

    abstract public function getOne($columns = '*');

    abstract public function insert($insertData);

    abstract public function update($tableData);

    abstract public function delete( $numRows = null);

    abstract public function orderBy($field, $direction = parent::TYPE_DESC);

    abstract public function groupBy($groupByField);

    abstract public function where($whereProp, $whereValue = null, $operator = null);

    abstract public function orWhere($whereProp, $whereValue = null, $operator = null);

    abstract public function beginTransaction();

    abstract public function commit();

    abstract public function rollback();

    abstract protected function _throwDbException();

    abstract public function getLastError();
} // END class
