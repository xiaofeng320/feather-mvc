<?php
namespace Feather\Table;
#########################################################################
# File Name: Mysqli.php
# Desc:基于Mysqli的常用功能封装。 
# Author: liufeng
# Created Time: 2014年12月08日 星期一 15时30分24秒
#########################################################################

class Mysqli extends AbstractTable {

    /**
     * 连接操作,连接是通过创建 mysqli的实例而建立的。
     * 如果有任何连接错误，将抛出一个 Exception 异常对象。
     */
    public function connect(){
        if (!empty($this->_connection)) {
            return;
        }   

        $config = $this->_config;
        $host = $config['host'];
        $port = $config['port'];
        $user = $config['username'];
        $password = $config['password'];
        $database = $config['dbname'];
        $charset = $config['charset'];

        $mysqli = new mysqli ($host, $user, $password, $database, $port);

        $mysqli->set_charset ('utf8');

        if (!$mysqli) {
            $this->_throwDbException();
        }   

        $this->_connection = $mysqli;

        return;
    }

    /**
    * 执行标准sql，返回一个array对象 
    * 示例:$db->query('SELECT name, color, calories FROM fruit ORDER BY name');
    *
    * @param string $sql
    * @return array 
    */
    public function query($sql) {
        $result = $this->_connection->query($sql);
        if ($result !== false) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        $this->_throwDbException();
    }

    /**
     * 示例： $db->rawQuery('SELECT * from person where id=?', Array (10));
     *
     * @param string $query      带占位符的sql语句 
     * @param array  $bindParams 传入数据 
     * @param bool   $sanitize   true时编码引号 
     *
     * @return array 
     */
    public function rawQuery ($query, $bindParams = null){
        $this->_query = $query;
        $this->_query = filter_var($query, FILTER_SANITIZE_STRING , FILTER_FLAG_NO_ENCODE_QUOTES);
        $sth = $this->_prepareQuery();

        if (is_array($bindParams) === true) {
            $flag = 1;
            foreach ($bindParams as $val) {
                $sth->bind_param($flag,$val);
                $flag++;
            }
        }

        $sth->execute();
        $res = $sth->fetchAll();
        $this->_sthError = $sth->error;
        $this->reset();

        return $res;
    }

    public function rawQuery ($query, $bindParams = null, $sanitize = true)
    {
        $this->_query = $query;
        if ($sanitize)
            $this->_query = filter_var ($query, FILTER_SANITIZE_STRING,
                                    FILTER_FLAG_NO_ENCODE_QUOTES);
        $stmt = $this->_prepareQuery();

        if (is_array($bindParams) === true) {
            $params = array(''); // Create the empty 0 index
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                array_push($params, $bindParams[$prop]);
            }

            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($params));

        }

        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->reset();

        return $this->_dynamicBindResults($stmt);
    }
    /**
     * select * 操作的简单封装 
     *
     * @param $numRows   分页参数
     *        like array(2,10),相当于limit 2,10
     * @param $columns 返回数据列，不传参数为获取所有列
     *        like array('name','age') 或 “name，age”
     *
     * @return array 
     */
    public function get($numRows = null, $columns = '*'){
        if (empty ($columns)){
            $columns = '*';
        }
        $column = is_array($columns) ? implode(', ', $columns) : $columns; 
        $this->_query = "SELECT $column FROM "  . $this->_tableName;
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $res = $stmt->fetch();
        $this->_sthError = $stmt->error;
        $this->reset();

        return $res;
    }

    /**
     * 对select * 操作的简单封装,查询一条记录 
     *
     * @param string  $columns 返回数据列，不传参数为获取所有列
     *        like array('name','age') 或 “name，age”
     *
     */
    public function getOne($columns = '*'){
        $res = $this->get (1, $columns);

        if (is_object($res))
            return $res;

        if (isset($res[0]))
            return $res[0];

        return null;
    }

    /**
     * 插入查询操作 
     *
     * @param array $insertData 插入数据库的数据.
     *
     * @return 操作影响行数>0 返回主键值 ，<0 返回false.
     */
    public function insert($insertData){

        $this->_query = "INSERT into " . $this->_tableName;
        $sth = $this->_buildQuery(null, $insertData);
        $sth->execute();
        $this->_sthError = $sth->errorCode();
        $this->reset();

        return $sth->rowCount()>0?$this->_connection->lastInsertId():false;
    }

    /**
     * 更新查询操作,需要先执行where()方法
     *
     * @param array $insertData 更新数据库的数据.
     *
     * @return boolean 是否更新成功
     */
    public function update($tableData){

        $this->_query = "UPDATE " . $this->_tableName ." SET ";

        $sth = $this->_buildQuery (null, $tableData);
        $status = $sth->execute();
        $this->reset();
        $this->_sthError = $sth->errorCode();
        $this->count = $sth->rowCount();

        return $status;
    }

    /**
     * 删除查询操作,需要先执行where()方法
     *
     * @param array $numRows 删除几条.
     *
     * @return boolean success. true or false.
     */
    public function delete( $numRows = null){

        $this->_query = "DELETE FROM " . $this->_tableName;

        $sth = $this->_buildQuery($numRows);
        $sth->execute();
        $this->_sthError = $sth->error;
        $this->reset();

        return ($sth->rowCount() > 0);
    }

    /**
     * 允许多次操作的 ORDER BY 操作.
     *
     * 示例 $db->orderBy('id','desc')->orderBy('name','asc');
     *
     * @param string $field 数据库表的列名.
     * @param string $direction 排序方式.
     *
     * @return PDO
     */
    public function orderBy($field, $direction = self::TYPE_DESC){
        $allowedDirection = Array ("ASC", "DESC");
        $direction = strtoupper (trim ($direction));

        if (empty($direction) || !in_array ($direction, $allowedDirection)){
            die ('Wrong order direction: '.$direction);
        }
        $this->_orderBy[$field] = $direction;
        return $this;
    } 

    /**
     * GROUP BY 操作.
     *
     * 示例 $MySqliDb->groupBy('id');
     *
     * @param string $groupByField 数据库表的列名.
     *
     * @return PDO
     */
    public function groupBy($groupByField){
        $this->_groupBy[] = $groupByField;
        return $this;
    } 
    /**
     * 允许多次拼接where操作.与操作。
     *
     * 示例 $db->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp 数据库表的列名 
     * @param mixed  $whereValue 数据库列的值
     * @param string $operator 操作方式
     *        例如  ‘between’,'in'
     *
     * @return PDO
     */
    public function where($whereProp, $whereValue = null, $operator = null){
        if ($operator){
            $whereValue = Array ($operator => $whereValue);
        }
        $this->_where[] = Array ("AND", $whereValue, $whereProp);
        return $this;
    }

    /**
     * 允许多次拼接where操作.或操作。
     *
     * 示例 $db->orWhere('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp 数据库表的列名 
     * @param mixed  $whereValue 数据库列的值
     * @param string $operator 操作方式
     *        例如  ‘between’,'in'
     *
     * @return PDO
     */
    public function orWhere($whereProp, $whereValue = null, $operator = null){
        if ($operator)
            $whereValue = Array ($operator => $whereValue);

        $this->_where[] = Array ("OR", $whereValue, $whereProp);
        return $this;
    }

    public function getLastError(){
        return $this->_sthError . " " . var_export($this->_connection->errorInfo(),true);
    }

    /**
     * 启动事务
     */
    public function beginTransaction() {
        $ret = $this->_connection->beginTransaction();
        if (!$ret) {
            throw new Exception('Begin transaction failed');       
        }
    }

    /**
     * 提交事务
     */
    public function commit() {
        $ret = $this->_connection->commit();
        if (!$ret) {
            throw new Exception('Transaction commit failed');
        }
    }

    /**
     * 回滚事务
     */
    public function rollback() {
        $ret = $this->_connection->rollBack();
        if (!$ret) {
            throw new Exception('Transaction rollback failed');
        }
    }

    /**
     * 生成sql语句，并预加载。
     *
     * @param int   $numRows 分页 
     * @param array $tableData 返回的数据项
     *
     * @return PDO Returns the $sth object.
     */
    protected function _buildQuery($numRows = null, $tableData = null){

        $this->_buildTableData ($tableData);
        $this->_buildWhere();
        $this->_buildGroupBy();
        $this->_buildOrderBy();
        $this->_buildLimit ($numRows);

        // Prepare query
        $sth = $this->_prepareQuery();

        // Bind parameters to statement if any
        if (count ($this->_bindParams) > 0){
            $flag = 1;
            foreach($this->_bindParams as $param){
                $sth->bindValue($flag,$param);
                $flag++;
            }
        }
        return $sth;
    }




    /**
     * insert和update操作的sql拼装函数。
     */
    protected function _buildTableData ($tableData){
        if (!is_array ($tableData)){
            return;
        }
        $isInsert = strpos ($this->_query, 'INSERT');
        $isUpdate = strpos ($this->_query, 'UPDATE');

        if ($isInsert !== false) {
            $this->_query .= '(`' . implode(array_keys($tableData), '`, `') . '`)';
            $this->_query .= ' VALUES(';
        }

        foreach ($tableData as $column => $value) {
            if ($isUpdate !== false)
                $this->_query .= "`" . $column . "` = ";

            // Simple value
            if (!is_array ($value)) {
                $this->_bindParam ($value);
                $this->_query .= '?, ';
                continue;
            }
        }
        $this->_query = rtrim($this->_query, ', ');
        if ($isInsert !== false){
            $this->_query .= ')';
        }
    }

    /**
     * 查询条件sql语句拼装。 
     */
    protected function _buildWhere (){
        if (empty ($this->_where)){
            return;
        }
        $this->_query .= ' WHERE ';

        // 干掉第一个 AND/OR
        $this->_where[0][0] = '';
        foreach ($this->_where as $cond) {
            list ($concat, $wValue, $wKey) = $cond;

            $this->_query .= " " . $concat ." " . $wKey;

            // Empty value (raw where condition in wKey)
            if ($wValue === null){
                continue;
            }
            // Simple = comparison
            if (!is_array ($wValue)){
                $wValue = Array ('=' => $wValue);
            }
            $key = key ($wValue);
            $val = $wValue[$key];
            switch (strtolower ($key)) {
                case 'not in':
                case 'in':
                    $comparison = ' ' . $key . ' (';
                    if (is_object ($val)) {
                        $comparison .= $this->_buildPair ("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            $this->_bindParam ($v);
                        }
                    }
                    $this->_query .= rtrim($comparison, ',').' ) ';
                    break;
                case 'not between':
                case 'between':
                    $this->_query .= " $key ? AND ? ";
                    $this->_bindParams ($val);
                    break;
                default:
                    $this->_query .= $this->_buildPair ($key, $val);
            }
        }
    }

    protected function _buildGroupBy(){
        if (empty ($this->_groupBy)){
            return;
        }

        $this->_query .= " GROUP BY ";
        foreach ($this->_groupBy as $key => $value)
            $this->_query .= $value . ", ";

        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    protected function _buildOrderBy(){
        if (empty ($this->_orderBy)){
            return;
        }
        $this->_query .= " ORDER BY ";
        foreach ($this->_orderBy as $prop => $value)
            $this->_query .= $prop . " " . $value . ", ";

        $this->_query = rtrim ($this->_query, ', ') . " ";
    }

    protected function _buildLimit($numRows){
        if (!isset ($numRows)){
            return;
        }
        if (is_array ($numRows))
            $this->_query .= ' LIMIT ' . (int)$numRows[0] . ', ' . (int)$numRows[1];
        else
            $this->_query .= ' LIMIT ' . (int)$numRows;
    }


    /**
     * 生成预处理PDOStatement
     *
     * @return PDOStatement 
     */
    protected function _prepareQuery(){
        if (!$stmt = $this->_connection->prepare($this->_query)) {
            trigger_error("Problem preparing query ($this->_query) " . $this->_connection->error, E_USER_ERROR);
        }
        return $stmt;
    }


    /**
     * 绑定参数到域_bindParams中，_bindParams[0] 存放属性标识符 
     *
     * @param string Variable value
     */
    protected function _bindParam($value){
        array_push ($this->_bindParams, $value);
    }

    /**
     * @param Array Variable with values
     */
    protected function _bindParams ($values){
        foreach ($values as $value)
            $this->_bindParam ($value);
    }

    /**
     * Helper function to add variables into bind parameters array and will return
     * its SQL part of the query according to operator in ' $operator ?' or
     * ' $operator ($subquery) ' formats
     *
     * @param Array Variable with values
     */
    protected function _buildPair ($operator, $value){
        if (!is_object($value)) {
            $this->_bindParam ($value);
            return ' ' . $operator. ' ? ';
        }

        return ;
    }

    protected function _throwDbException() {
        if ($this->_connection) {
            throw new Exception($this->_connection->errorInfo(),
                $this->_connection->errorCode());
        } else {
            throw new Exception(" mysql connection is error",
                10086);
        }
    }

} // END class
