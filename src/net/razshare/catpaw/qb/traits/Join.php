<?php
namespace net\razshare\catpaw\qb\traits;

use net\razshare\catpaw\tools\helpers\Factory;
use net\razshare\catpaw\qb\tools\Column;
use net\razshare\catpaw\qb\tools\columncallbacks\ColumnHelper;
use net\razshare\catpaw\qb\tools\CoreEntity;
use net\razshare\catpaw\qb\tools\Repository;
use net\razshare\catpaw\qb\tools\QueryConst;
use net\razshare\catpaw\qb\tools\QueryBuilder;
use net\razshare\catpaw\tools\helpers\Entity;

trait Join{
    //private $joins = [];
    /**
     * Add a JOIN operation to the query
     * @param Repository $repository the table to join with
     * @return QueryBuilder the QueryBuilder
     */
    public function join(string $classname, string $as = null, string $type = QueryConst::JOIN):QueryBuilder{
        $entity = Factory::make($classname);
        if($as === null)
            $as = $entity->tableName();
        $this->add($type);
        $name = $entity->tableName();
        //$this->joins[$name] = $repository;
        $this->add($name);
        if($as !== null){
            $this->add(QueryConst::AS);
            $this->add($as);
            $columnsRef = &$entity->getEntityAliasColumns(Entity::FOR_SELECT);
            $columns = $columnsRef;
            foreach($columns as $key => &$column){
                $columnsRef[$as.QueryConst::PERIOD.$key] = $column; 
            }
            //$this->alias[$as] = $name;
        }
        return $this;
    }

    /**
     * Add a LEFT JOIN operation to the query
     * @param Repository $repository the table to join with
     * @return QueryBuilder the QueryBuilder
     */
    public function leftJoin(string $classname, string $as = null):QueryBuilder{
        return $this->join($classname,$as,QueryConst::LEFT_JOIN);
    }

    /**
     * Add a LEFT JOIN operation to the query
     * @param Repository $repository the table to join with
     * @return QueryBuilder the QueryBuilder
     */
    public function rightJoin(string $classname, string $as = null):QueryBuilder{
        return $this->join($classname,$as,QueryConst::RIGHT_JOIN);
    }

    /**
     * Add a LEFT JOIN operation to the query
     * @param Repository $repository the table to join with
     * @return QueryBuilder the QueryBuilder
     */
    public function innerJoin(string $classname, string $as = null):QueryBuilder{
        return $this->join($classname,$as,QueryConst::INNER_JOIN);
    }


    /**
     * Specify the behavior of the query when a column is encountered
     * @param string $columnName the name of the column
     * @param callable $callback a function that will be called and is expected to return an Operation, 
     * such as Like,Equal,GreaterThan,GreaterThanEqual,LesserThan,LesserThanEqual,Between.
     * This callback function will also be passed the Column as a parameter, which can be used to make new Operations.
     * Example: 
     *  return 
     *       $this
     *       ->build()
     *       ->select($this->columns)
     *       ->from($this)->as("A")
     *       ->join($otherEntity)->as("B")
     *       ->column("username",function(Column &$column){
     *           return $column->like("test_account_name");
     *       })
     *       ->run();
     * @return QueryBuilder the QueryBuilder
     */
    public function on(CoreEntity $entity, string $columnName,int $operationCode, ...$args):QueryBuilder{
        return $this->on__($entity, false,$columnName,$operationCode, $args);
    }

    /**
     * Add another clause to the current "ON" group.
     * @return QueryBuilder the QueryBuilder
     */
    public function andOn(CoreEntity $entity, string $columnName,int $operationCode, ...$args):QueryBuilder{
        return $this->on__($entity,true,$columnName,$operationCode, $args);
    }

    private function on__(CoreEntity &$entity, bool $and, string $columnName,int $operationCode, array $args):QueryBuilder{
        switch($operationCode){
            case Column::EQUALS:
                $callback = ColumnHelper::equals($args[0]);
            break;
            case Column::GREATER_THAN:
                $callback = ColumnHelper::greaterThan($args[0]);
            break;
            case Column::LESSER_THAN:
                $callback = ColumnHelper::lesserThan($args[0]);
            break;
            case Column::GREATER_THAN_EQUALS:
                $callback = ColumnHelper::greaterThanEquals($args[0]);
            break;
            case Column::LESSER_THAN_EQUALS:
                $callback = ColumnHelper::lesserThanEquals($args[0]);
            break;
            case Column::BETWEEN:
                $callback = ColumnHelper::between($args[0],$args[1]);
            break;
            case Column::LIKE:
                $callback = ColumnHelper::like($args[0]);
            break;
        }

        if(!$and) $this->add(QueryConst::ON);
        $this->add($columnName);
        $operation = $callback->run($entity->getEntityColumns(Entity::FOR_SELECT)[$columnName]);
        $this->add($operation->toString());
        return $this;
    }

    
}
